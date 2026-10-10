<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Models\SpjDocument;
use App\Models\SpjPackage;
use App\Models\User;
use App\Services\OperationalAuditService;
use App\Services\SchoolDatabaseManager;
use App\Services\SpjDocumentLifecycleService;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingGateService;
use App\Services\SpjNumberingOrderService;
use App\Services\SpjPackageValidationService;
use App\Services\SpjSourceReconciliationService;
use App\Support\ActiveSpjContext;
use Illuminate\Console\Command;

/**
 * Pilot penyelesaian rekonsiliasi paket NUMBERED (arah ACCEPT_SOURCE).
 *
 * Alur resmi per paket: batalkan dokumen SPJ MAIN individual (histori
 * CANCELLED abadi) → resolve ACCEPT_SOURCE → tetapkan nomor baru.
 * Default dry-run; mutasi hanya dengan --execute.
 */
class ResolveNumberedReconciliation extends Command
{
    protected $signature = 'spj:resolve-numbered-reconciliation
        {--school= : NPSN sekolah}
        {--year-id= : ID tahun anggaran aktif}
        {--fund-source-id= : ID sumber dana aktif}
        {--package=* : ID paket SPJ}
        {--resolution=ACCEPT_SOURCE : ACCEPT_SOURCE atau KEEP_OVERLAY}
        {--reason= : Alasan pembatalan/reissue}
        {--admin= : ID user administrator pelaksana}
        {--execute : Jalankan mutasi (tanpa flag ini = dry-run)}';

    protected $description = 'Selesaikan rekonsiliasi paket NUMBERED via cancel individual + resolve + nomor baru (pilot).';

    public function handle(
        SchoolDatabaseManager $schools,
        SpjDocumentLifecycleService $lifecycle,
        SpjSourceReconciliationService $reconciliation,
        SpjDocumentNumberService $numbers,
        SpjPackageValidationService $validator,
        SpjNumberingGateService $gate,
        SpjNumberingOrderService $order,
        OperationalAuditService $audit,
    ): int {
        $npsn = (string) $this->option('school');
        $yearId = (int) $this->option('year-id');
        $fundSourceId = (int) $this->option('fund-source-id');
        $packageIds = collect($this->option('package'))->map(fn ($id): int => (int) $id)->sort()->values()->all();
        $resolution = strtoupper((string) $this->option('resolution'));
        $reason = trim((string) $this->option('reason'));
        $adminId = (int) $this->option('admin');
        $execute = (bool) $this->option('execute');

        if ($npsn === '' || $yearId <= 0 || $fundSourceId <= 0 || $packageIds === []) {
            $this->error('Wajib: --school=NPSN --year-id=ID --fund-source-id=ID --package=ID (bisa berulang).');

            return self::FAILURE;
        }
        if (! in_array($resolution, [SpjSourceReconciliationService::ACCEPT_SOURCE, SpjSourceReconciliationService::KEEP_OVERLAY], true)) {
            $this->error('Resolution hanya ACCEPT_SOURCE atau KEEP_OVERLAY.');

            return self::FAILURE;
        }
        if ($reason === '') {
            $this->error('Alasan pembatalan/reissue wajib diisi (--reason).');

            return self::FAILURE;
        }

        $school = School::query()->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error("Sekolah NPSN {$npsn} tidak ditemukan di database pusat.");

            return self::FAILURE;
        }
        $admin = User::query()->find($adminId);
        if (! $admin || ! $admin->isAdministrator()) {
            $this->error('Pelaksana harus user administrator yang valid (--admin).');

            return self::FAILURE;
        }

        $schools->activate($school);
        // Service container memakai session-context; selaraskan agar gate
        // penomoran dan validasi melihat konteks aktif yang sama.
        session([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $yearId,
            'active_fund_source_id' => $fundSourceId,
        ]);
        $context = new ActiveSpjContext(
            schoolIdOverride: $school->id,
            fiscalYearIdOverride: $yearId,
            fundSourceIdOverride: $fundSourceId,
            actorIdOverride: $admin->id,
            administratorOverride: true,
        );

        $this->info(($execute ? 'EKSEKUSI' : 'DRY-RUN')." | sekolah={$school->name} | pkgs=".implode(',', $packageIds)." | resolution={$resolution}");

        foreach ($packageIds as $packageId) {
            $this->line("--- paket {$packageId} ---");
            $package = SpjPackage::query()->with(['transaction', 'documents'])->find($packageId);
            if (! $package || ! $package->transaction) {
                $this->error('Paket/transaksi tidak ditemukan.');

                return self::FAILURE;
            }
            if (! $context->matchesTransaction($package->transaction)) {
                $this->error('Paket di luar konteks sekolah/tahun/sumber dana yang diminta.');

                return self::FAILURE;
            }
            if ($package->status !== 'NUMBERED') {
                $this->error("Status paket {$package->status}, pilot ini hanya untuk NUMBERED.");

                return self::FAILURE;
            }
            $report = $reconciliation->forTransaction($package->transaction->load('spjPackage'));
            if (! $report['requires_reconciliation']) {
                $this->error('Transaksi tidak memerlukan rekonsiliasi.');

                return self::FAILURE;
            }
            $latest = $report['latest'];
            if ($latest === null || $latest->changes === []) {
                $this->error('Hanya diff nilai bisnis yang ditangani pilot ini.');

                return self::FAILURE;
            }
            $mainDoc = $package->documents->firstWhere(fn (SpjDocument $doc): bool => $doc->document_type === 'SPJ' && $doc->scope_key === 'MAIN' && $doc->status === 'NUMBERED');
            if (! $mainDoc) {
                $this->error('Dokumen SPJ MAIN bernomor tidak ditemukan.');

                return self::FAILURE;
            }
            if ($blocker = $gate->periodBlocker($package)) {
                $this->error("Period blocker: {$blocker}");

                return self::FAILURE;
            }

            $oldNumber = $mainDoc->document_number;
            $this->line("Batalkan {$oldNumber} → resolve {$resolution} (event #{$latest->id}, ".count($latest->changes).' field berubah) → nomor baru.');

            if (! $execute) {
                continue;
            }

            $lifecycle->cancel($mainDoc, $admin->id, $reason);
            $audit->record($package->transaction->fiscal_year_id, 'SPJ_DOCUMENT', $mainDoc->id, 'BATALKAN_NOMOR', "Nomor {$oldNumber} dibatalkan untuk reissue sumber. Alasan: {$reason}");

            $package->refresh();
            $result = $reconciliation->resolve(
                $package->transaction->load('spjPackage'),
                $resolution,
                "Pilot reissue {$oldNumber}: {$reason}",
                $admin->id,
                (int) $latest->id,
            );
            $audit->record($package->transaction->fiscal_year_id, 'TRANSACTION', $package->transaction->id, 'RESOLUSI_REKONSILIASI', "Rekonsiliasi {$result['label']} untuk reissue {$oldNumber}.");

            $package = SpjPackage::query()->with(SpjPackage::FULL_EAGER_LOAD)->find($packageId);
            if ($blocker = $gate->issuanceBlocker($package, 'SPJ')) {
                $this->error("Issuance blocker: {$blocker}");

                return self::FAILURE;
            }
            if ($issues = $validator->validateForNumbering($package)) {
                $this->error('Validasi penomoran gagal: '.collect($issues)->pluck('message')->implode(' '));

                return self::FAILURE;
            }
            if ($blocker = $order->singleNumberingBlocker($package, ['SPJ'])) {
                $this->error("Order blocker: {$blocker}");

                return self::FAILURE;
            }

            $assignResult = $numbers->assignAutomaticNumbers($package, $school->school_code ?: $school->npsn, $school->npsn, ['SPJ']);
            $package->refresh();
            $audit->record($package->transaction->fiscal_year_id, 'SPJ_PACKAGE', $package->id, 'TETAPKAN_NOMOR', "Nomor SPJ {$package->document_number} ditetapkan sebagai pengganti {$oldNumber}.");
            $this->info("OK paket {$packageId}: {$oldNumber} → {$package->document_number} (created={$assignResult['created']}, skipped={$assignResult['skipped']})");
        }

        $this->info($execute ? 'Selesai.' : 'Dry-run selesai. Ulangi dengan --execute untuk mutasi.');

        return self::SUCCESS;
    }
}
