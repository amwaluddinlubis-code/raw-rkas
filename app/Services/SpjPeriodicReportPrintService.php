<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjPeriodicReportUseCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class SpjPeriodicReportPrintService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjPeriodicReportUseCase $reports,
        private readonly RkasReportService $rkas,
        private readonly ArkasMirrorBudgetService $budgets,
        private readonly SpjPeriodicReportRegistry $registry,
    ) {}

    /** @var array{bank:float,cash:float,total:float} */
    private array $bkuClosing = ['bank' => 0.0, 'cash' => 0.0, 'total' => 0.0];

    /** @return array<string,mixed>|null */
    public function build(string $scope, string $reportKey, ?int $period): ?array
    {
        $payload = $this->reports->payload($scope, $reportKey, $period);

        if ($payload === null || ! $payload['summary']['ready']) {
            return null;
        }

        /** @var Collection<int,Transaction> $transactions */
        $transactions = $payload['transactions'];
        $school = $this->context->school();
        $year = FiscalYear::query()->with('fundSource')->findOrFail($this->context->fiscalYearId());
        $profile = DB::connection('school')
            ->table('school_profiles')
            ->where('fiscal_year_id', $year->id)
            ->first();

        $presentation = $this->presentation($reportKey);
        [$columns, $rows] = $this->table($presentation, $transactions, $scope, $period);
        $summary = $payload['summary'];

        $extra = [];
        if (in_array($presentation, ['bku_ledger', 'cash_ledger', 'tax'], true)) {
            $extra['bkuClosing'] = $this->bkuClosing($summary);
            $extra['bkuPeriod'] = $this->bkuPeriodLabel($summary, (int) ($year->year ?: now()->year));
            if ($presentation === 'tax') {
                // BKU menghitung baris saat merakit ledger; pajak memakai
                // jumlah baris hasil akhirnya untuk blok identitas.
                $this->bkuLineCount = count($rows);
            }
            $extra['bkuMeta'] = $this->bkuMeta(
                $summary,
                (string) ($year->fundSource?->name ?? $year->fund_source ?? '-'),
                (int) ($year->year ?: now()->year)
            );
        }
        if ($presentation === 'rekap_bosp') {
            $extra['rekapBosp'] = $this->rekapBospData($scope, $period);
            $extra['rekapTitle'] = $this->rekapBospTitle($summary, (int) ($year->year ?: now()->year));
            $extra['rekapSnpRows'] = BospRekapStandardMapper::standardRows();
            $extra['rekapSubPrograms'] = BospRekapStandardMapper::subPrograms();
        }
        if ($presentation === 'bpk_bos') {
            $extra['bpkData'] = $this->bpkData($scope, $period);
            $extra['bpkTitle'] = $this->bpkTitle($summary, (int) ($year->year ?: now()->year));
            $extra['bpkSchoolName'] = $this->bpkSchoolName($school->name ?? '');
            $extra['bpkSchoolAddress'] = mb_strtoupper(trim('DESA '.(string) $school->desa.' KECAMATAN '.(string) $school->district));
        }
        if ($presentation === 'bos_a1') {
            $extra['bosA1'] = $this->bosA1Data($transactions);
            $extra['bosA1Title'] = $this->bosA1Title($summary);
        }
        if ($presentation === 'k7b') {
            $extra['k7b'] = $this->k7bData($scope, $period, $summary);
        }
        if ($presentation === 'k7c') {
            $extra['k7c'] = $this->k7cData($scope, $period, $summary);
        }
        if ($presentation === 'k7') {
            $extra['k7'] = $this->k7Data($scope, $period, $transactions, $summary);
        }
        if ($presentation === 'k2') {
            $extra['k2'] = $this->k2Data($scope, $period);
            $extra['k2Title'] = $this->bosA1Title($summary);
        }
        if ($presentation === 'rpd') {
            $extra['rpd'] = $this->rpdData($scope, $period);
            $extra['rpdTitle'] = $this->bosA1Title($summary);
        }
        if ($presentation === 'sptjm_doc') {
            $extra['sptjm'] = $this->sptjmData($scope, $period, $summary);
        }

        return [
            ...$payload,
            ...$extra,
            'school' => $school,
            'year' => $year,
            'profile' => $profile,
            'fundSource' => $year->fundSource?->name ?? $year->fund_source ?? '-',
            'presentation' => $presentation,
            'columns' => $columns,
            'rows' => $rows,
            'statement' => $this->statement($reportKey, (string) $summary['period_label']),
            'orientation' => $this->orientation($presentation),
            'paper' => $this->paper($presentation),
            'fileName' => $this->fileName($payload['report']['label'], (string) $summary['period_label']),
            'generatedAt' => now(),
        ];
    }

    private function presentation(string $reportKey): string
    {
        return match ($reportKey) {
            'bku' => 'bku_ledger',
            'buku_pembantu_kas' => 'cash_ledger',
            'buku_pembantu_bank' => 'bank_ledger',
            'buku_pembantu_pajak' => 'tax',
            'rekapitulasi_pengeluaran_dana_bos' => 'rekap_bosp',
            'bpk_bos' => 'bpk_bos',
            'bos_a1' => 'bos_a1',
            'k7b' => 'k7b',
            'k7c' => 'k7c',
            'format_k7' => 'k7',
            'format_k2' => 'k2',
            'rpd_bos' => 'rpd',
            'sptjm' => 'sptjm_doc',
            'rekap_belanja_modal_barang_jasa', 'rekap_bmd',
            'rekap_belanja_dana_bos' => 'account_summary',
            'lampiran_berita_acara_rekonsiliasi' => 'transaction_recap',
            default => 'statement',
        };
    }

    /** @return array{0:list<array{key:string,label:string,type:string}>,1:list<array<string,mixed>>} */
    private function table(string $presentation, Collection $transactions, string $scope = '', ?int $period = null): array
    {
        return match ($presentation) {
            'bku_ledger' => [$this->bkuLedgerColumns(), $this->bkuLedgerRows($scope, $period)],
            'ledger' => [$this->ledgerColumns(), $this->ledgerRows($transactions)],
            'cash_ledger' => [$this->bkuLedgerColumns(), $this->bkuLedgerRows($scope, $period, true)],
            'bank_ledger' => [$this->ledgerColumns(), $this->ledgerRows($this->bankTransactions($transactions))],
            'tax' => [$this->taxColumns(), $this->taxRows($transactions)],
            'rekap_bosp' => [$this->rekapBospColumns(), $this->rekapBospRows($scope, $period)],
            'bpk_bos' => [[], []],
            'k7b' => [[], []],
            'k7c' => [[], []],
            'k7' => [[], []],
            'k2' => [[], []],
            'rpd' => [[], []],
            'sptjm_doc' => [[], []],
            'account_summary' => [$this->accountColumns(), $this->accountRows($transactions)],
            'transaction_recap' => [$this->recapColumns(), $this->recapRows($transactions)],
            'bos_a1' => [[], []],
            default => [$this->accountColumns(), $this->accountRows($transactions)],
        };
    }

    /**
     * Laporan BPK Dinas (REKAP REALISASI PENGGUNAAN DANA BOS): DATA SEKOLAH +
     * DATA REALISASI KEUANGAN (saldo awal, pendapatan operasional, belanja
     * operasional a–g, belanja modal KIB A–F, saldo akhir) + tanda tangan.
     *
     * Aturan nilai (diverifikasi terhadap 6 contoh resmi 2025):
     * - belanja per kategori dari KODE_REKENING (bukan uraian);
     * - Terima Dana BOS distinct (bukti+tanggal+nominal) karena ARKAS
     *   menduplikasi baris terima per revisi anggaran;
     * - Beban Bunga Bank = total bunga bank periode (pass-through);
     * - Setor Tunai ikut penerimaan bila datanya ada.
     *
     * @return array<string,mixed>
     */
    private function bpkData(string $scope, ?int $period): array
    {
        $months = $this->scopeMonths($scope, $period);
        $year = (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year);
        $fundSourceId = $this->context->fundSourceId() ?? 0;

        $data = [
            'openingBank' => 0.0, 'openingCash' => 0.0, 'openingDate' => '',
            'terimaRows' => [], 'terimaTotal' => 0.0, 'terimaReguler' => 0.0, 'terimaKinerja' => 0.0,
            'bungaRows' => [], 'bungaTotal' => 0.0,
            'setorTotal' => 0.0,
            'op' => ['persediaan' => 0.0, 'perjalanan' => 0.0, 'pemeliharaan' => 0.0, 'koran' => 0.0, 'makan' => 0.0, 'jasa' => 0.0],
            'opReguler' => ['persediaan' => 0.0, 'perjalanan' => 0.0, 'pemeliharaan' => 0.0, 'koran' => 0.0, 'makan' => 0.0, 'jasa' => 0.0],
            'modal' => ['A' => 0.0, 'B' => 0.0, 'C' => 0.0, 'D' => 0.0, 'E' => 0.0, 'F' => 0.0],
            'modalReguler' => ['A' => 0.0, 'B' => 0.0, 'C' => 0.0, 'D' => 0.0, 'E' => 0.0, 'F' => 0.0],
            'bebanBunga' => 0.0,
            'finalDate' => '',
        ];

        if ($months !== []) {
            $firstMonth = min($months);
            $data['openingDate'] = sprintf('%02d-%02d-%04d', 1, $firstMonth, $year);

            $placeholders = implode(',', array_fill(0, count($months), '?'));
            $records = DB::connection('school')->table('arkas_mirror_kas_umum')
                ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
                ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
                ->orderByRaw("json_extract(payload, '\$.TANGGAL_TRANSAKSI')")
                ->orderBy('id')
                ->get(['payload'])
                ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER));

            $receivedKeys = [];
            $lastDate = '';
            foreach ($records as $payload) {
                $rek = (string) ($payload['REK_BKU'] ?? '');
                $amount = (float) ($payload['JUMLAH'] ?? 0);
                $date = (string) ($payload['TANGGAL_TRANSAKSI'] ?? '');
                if ($date !== '') {
                    $lastDate = max($lastDate, $date);
                }
                $isReguler = $this->bpkIsReguler($payload, $fundSourceId);

                // Varian "Sisa" ARKAS (mis. "Saldo Awal Bank Sisa")
                // diperlakukan sama dengan bentuk dasarnya, mengikuti
                // aturan bulan-pertama bkuLedgerRows().
                $baseRek = $this->bkuBaseRek($rek);
                if (in_array($baseRek, ['Saldo Awal Bank', 'Saldo Awal Tunai'], true) && (int) substr($date, 5, 2) === $firstMonth) {
                    if ($baseRek === 'Saldo Awal Bank') {
                        $data['openingBank'] += $amount;
                    } else {
                        $data['openingCash'] += $amount;
                    }

                    continue;
                }
                if ($rek === 'Terima Dana BOS') {
                    $key = (string) ($payload['NO_BUKTI'] ?? '').'|'.$date.'|'.$amount;
                    if (! isset($receivedKeys[$key])) {
                        $receivedKeys[$key] = true;
                        $data['terimaTotal'] += $amount;
                        if ($isReguler) {
                            $data['terimaReguler'] += $amount;
                        } else {
                            $data['terimaKinerja'] += $amount;
                        }
                        $data['terimaRows'][] = ['date' => $date, 'uraian' => (string) ($payload['URAIAN'] ?? ''), 'amount' => $amount, 'reguler' => $isReguler];
                    }

                    continue;
                }
                if ($rek === 'Bunga Bank') {
                    $data['bungaTotal'] += $amount;
                    $month = (int) substr($date, 5, 2);
                    $data['bungaRows'][] = [
                        'date' => $date,
                        'amount' => $amount,
                        'label' => $month >= 1 && $month <= 12
                            ? 'Bulan '.Carbon::create($year, $month, 1)->translatedFormat('F Y')
                            : $date,
                        'endDate' => $month >= 1 && $month <= 12
                            ? Carbon::create($year, $month, 1)->endOfMonth()->format('d-m-Y')
                            : $date,
                    ];

                    continue;
                }
                if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'BELANJA') {
                    continue;
                }
                $category = $this->bpkBelanjaCategory((string) ($payload['KODE_REKENING'] ?? ''));
                if ($category === null) {
                    continue;
                }
                [$group, $key] = $category;
                $data[$group][$key] += $amount;
                if ($isReguler) {
                    $data[$group.'Reguler'][$key] += $amount;
                }
            }

            $data['bebanBunga'] = $data['bungaTotal'];
            $data['finalDate'] = $lastDate !== '' ? Carbon::parse($lastDate)->format('d-m-Y') : '';
        }

        $data = $this->bpkTotals($data);
        $data['closingBank'] = 0.0;
        $data['closingCash'] = 0.0;
        if ($months !== []) {
            $nextStart = Carbon::create($year, max($months), 1)->addMonth()->format('Y-m-d');
            foreach (DB::connection('school')->table('arkas_mirror_kas_umum')
                ->whereRaw("json_extract(payload, '\$.TANGGAL_TRANSAKSI') = ?", [$nextStart])
                ->get(['payload']) as $row) {
                $payload = array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER);
                // Varian "Sisa" ARKAS diperlakukan sama dengan bentuk dasarnya.
                $closingBaseRek = $this->bkuBaseRek((string) ($payload['REK_BKU'] ?? ''));
                if ($closingBaseRek === 'Saldo Awal Bank') {
                    $data['closingBank'] += (float) ($payload['JUMLAH'] ?? 0);
                } elseif ($closingBaseRek === 'Saldo Awal Tunai') {
                    $data['closingCash'] += (float) ($payload['JUMLAH'] ?? 0);
                }
            }
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function bpkTotals(array $data): array
    {
        $opTotal = array_sum($data['op']) + $data['bebanBunga'];
        $modalTotal = array_sum($data['modal']);
        $masuk = $data['terimaTotal'] + $data['bungaTotal'] + $data['setorTotal'];
        $keluar = $opTotal + $modalTotal;
        $opening = $data['openingBank'] + $data['openingCash'];
        $data['opTotal'] = $opTotal;
        $data['modalTotal'] = $modalTotal;
        $data['masukTotal'] = $masuk;
        $data['keluarTotal'] = $keluar;
        $data['closing'] = $opening + $masuk - $keluar;

        return $data;
    }

    /**
     * Kecocokan sumber dana baris kas mirror dengan konteks aktif.
     * Baris tanpa ID_REF_SUMBER_DANA (data lama) tetap lolos agar tidak
     * hilang dari laporan.
     */
    private function kasMatchesFund(array $payload, int $fundSourceId): bool
    {
        $fund = (int) ($payload['ID_REF_SUMBER_DANA'] ?? 0);

        return $fund <= 0 || $fund === $fundSourceId;
    }

    private function bpkIsReguler(array $payload, int $fundSourceId): bool
    {
        return $this->kasMatchesFund($payload, $fundSourceId);
    }

    /**
     * Kategori belanja Dinas dari kode rekening (diverifikasi terhadap contoh
     * resmi triwulan/semester/tahunan). Kembali [grup, kunci] atau null bila
     * tak terpetakan (tidak dihitung di kategori agar total teraudit).
     *
     * @return array{0:string,1:string}|null
     */
    private function bpkBelanjaCategory(string $rekening): ?array
    {
        $code = strtoupper(trim($rekening, '.'));

        if (str_starts_with($code, '5.1.02.04')) {
            return ['op', 'perjalanan'];
        }
        if ($code === '5.1.02.02.01.0063') {
            return ['op', 'koran'];
        }
        if ($code === '5.1.02.01.01.0052') {
            return ['op', 'makan'];
        }
        if (in_array($code, ['5.1.02.02.01.0013', '5.1.02.02.01.0031'], true) || str_starts_with($code, '5.1.02.02.04')) {
            return ['op', 'jasa'];
        }
        if (str_starts_with($code, '5.1.02.03')) {
            return ['op', 'pemeliharaan'];
        }
        if (str_starts_with($code, '5.2.02')) {
            return ['modal', 'B'];
        }
        if (str_starts_with($code, '5.2.05') || str_starts_with($code, '5.2.')) {
            return ['modal', 'E'];
        }
        if (str_starts_with($code, '5.1.') || str_starts_with($code, '5.2.')) {
            return ['op', 'persediaan'];
        }

        return null;
    }

    /** @return Collection<int,Transaction> */
    private function cashTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => $transaction->payment_method === 'tunai')
            ->values();
    }

    /** @return Collection<int,Transaction> */
    private function bankTransactions(Collection $transactions): Collection
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => in_array($transaction->payment_method, ['transfer_bank', 'siplah'], true))
            ->values();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function ledgerColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
            ['key' => 'recipient', 'label' => 'Penerima', 'type' => 'text'],
            ['key' => 'gross', 'label' => 'Pengeluaran Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Dibayarkan', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function ledgerRows(Collection $transactions): array
    {
        return $transactions->values()->map(fn (Transaction $transaction, int $index): array => [
            'no' => $index + 1,
            'date' => $transaction->sourceDateString('transaction_date', 'd-m-Y') ?? '-',
            'evidence' => $transaction->sourceValue('no_bukti') ?: '-',
            'description' => $transaction->sourceValue('description') ?: '-',
            'account' => trim(($transaction->sourceValue('account_code') ?: '').' '.($transaction->sourceValue('account_name') ?: '')) ?: '-',
            'recipient' => $transaction->sourceValue('recipient_name') ?: '-',
            'gross' => (float) $transaction->sourceValue('gross_amount'),
            'tax' => (float) $transaction->sourceValue('tax_total'),
            'net' => (float) $transaction->sourceValue('net_amount'),
        ])->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function bkuLedgerColumns(): array
    {
        return [
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'activity', 'label' => 'Kode Kegiatan', 'type' => 'nowrap'],
            ['key' => 'account', 'label' => 'Kode Rekening', 'type' => 'nowrap'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'stacked'],
            ['key' => 'incoming', 'label' => 'Penerimaan', 'type' => 'money'],
            ['key' => 'outgoing', 'label' => 'Pengeluaran', 'type' => 'money'],
            ['key' => 'balance', 'label' => 'Saldo', 'type' => 'money'],
        ];
    }

    /**
     * Buku Kas Umum resmi dari baris mirror kas_umum (bukan agregat
     * transaksi): mencakup penerimaan BOS, belanja, pasangan Terima/Setor
     * pajak, tarik tunai/pergeseran, bunga/pajak bank, dan saldo awal —
     * dengan saldo berjalan seperti dokumen BKU ARKAS.
     *
     * Aturan sisi kas/bank mengikuti REK_BKU terverifikasi data nyata:
     * Tarik Tunai hanya mengurangi bank; Pergeseran Tunai menambah tunai;
     * Kas Keluar mengurangi tunai; Kas Keluar Non Tunai mengurangi bank;
     * Terima/Setor pajak saling meniadakan di sisi tunai.
     *
     * @return list<array<string,mixed>>
     */
    private function bkuLedgerRows(string $scope, ?int $period, bool $cashOnly = false): array
    {
        $months = $this->scopeMonths($scope, $period);
        if ($months === []) {
            return [];
        }

        $year = (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year);
        $placeholders = implode(',', array_fill(0, count($months), '?'));

        $records = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
            ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
            ->orderByRaw("json_extract(payload, '\$.TANGGAL_TRANSAKSI')")
            ->orderBy('id')
            ->get(['source_key', 'payload'])
            ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER))
            ->values();

        // Konteks sumber dana aktif: baris milik dana lain (mis. Afirmasi
        // di tengah BKU Reguler) disaring di sini agar tidak tampil ganda
        // dan tidak menggeser saldo berjalan. Baris tanpa
        // ID_REF_SUMBER_DANA (data lama) tetap lolos seperti aturan BPK.
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null) {
            $records = $records->filter(
                fn (array $payload): bool => $this->kasMatchesFund($payload, $fundSourceId)
            )->values();
        }

        // Baris backfill MIG-* (spj:backfill-mirror-from-local) yang sudah
        // dilengkapi baris PAJAK kanonis sync susulan dibuang di sini agar
        // tidak tampil sebagai baris kembar beruraian kosong; shortfall
        // murni (tanpa padanan kanonis) tetap dihitung sebagai Terima.
        // Cerminan dedup bayangan ArkasMirrorResolver::transactionSource.
        $records = $this->bkuDedupBackfillTax($records);

        $firstMonth = min($months);
        $openingBank = 0.0;
        $openingCash = 0.0;
        foreach ($records as $payload) {
            if ((int) substr((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), 5, 2) !== $firstMonth) {
                continue;
            }
            // Varian "Sisa" ARKAS (mis. "Saldo Awal Bank Sisa") diperlakukan
            // sama dengan bentuk dasarnya agar tidak hilang dari saldo.
            match ($this->bkuBaseRek((string) ($payload['REK_BKU'] ?? ''))) {
                'Saldo Awal Bank' => $openingBank += (float) ($payload['JUMLAH'] ?? 0),
                'Saldo Awal Tunai' => $openingCash += (float) ($payload['JUMLAH'] ?? 0),
                default => null,
            };
        }

        $bank = $openingBank;
        $cash = $openingCash;
        $rows = [];
        $totalIn = 0.0;
        $totalOut = 0.0;
        $this->bkuLineCount = 0;
        $kegiatanCache = [];
        $chainMaps = $this->bkuActivityChainMaps($records);
        $rkasMap = DB::connection('school')->table('arkas_rkas_items')
            ->pluck('activity_code', 'source_rapbs_id')
            ->mapWithKeys(fn ($code, $key): array => [strtoupper(trim((string) $key)) => (string) $code])
            ->all();
        $operatorMaps = $this->bkuOperatorMaps($records);
        $evidenceByKas = [];
        foreach ($records as $payload) {
            $kasKey = (string) ($payload['ID_KAS_UMUM'] ?? '');
            if ($kasKey !== '' && (string) ($payload['NO_BUKTI'] ?? '') !== '') {
                $evidenceByKas[$kasKey] = (string) $payload['NO_BUKTI'];
            }
        }

        $openingReks = $cashOnly ? ['Saldo Awal Tunai'] : ['Saldo Awal Bank', 'Saldo Awal Tunai'];
        $openingLines = [];
        foreach ($records as $payload) {
            if ((int) substr((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), 5, 2) === $firstMonth
                && in_array($this->bkuBaseRek((string) ($payload['REK_BKU'] ?? '')), $openingReks, true)) {
                $openingLines[] = $payload;
            }
        }
        foreach ($openingLines as $payload) {
            $amount = (float) ($payload['JUMLAH'] ?? 0);
            $totalIn += $amount;
            $rows[] = [
                'date' => $this->bkuDate($payload),
                'activity' => '-',
                'account' => '-',
                'evidence' => (string) ($payload['NO_BUKTI'] ?? '') ?: '-',
                'description' => $this->bkuDescription($payload, $operatorMaps),
                'incoming' => $amount,
                'outgoing' => 0.0,
                'balance' => $cashOnly ? $cash : $bank + $cash,
            ];
        }

        $kegiatanCache = [];
        $lines = [];
        $lineOrder = 0;
        foreach ($records as $payload) {
            $rek = (string) ($payload['REK_BKU'] ?? '');
            $baseRek = $this->bkuBaseRek($rek);
            if (in_array($baseRek, ['Saldo Awal Bank', 'Saldo Awal Tunai'], true)) {
                continue;
            }
            // Buku Pembantu Kas hanya mencatat sisi tunai.
            if ($cashOnly && ! in_array($baseRek, ['Pergeseran Tunai', 'Kas Keluar', 'Pajak Belanja Terima', 'Pajak Belanja Setor'], true)) {
                continue;
            }

            $amount = (float) ($payload['JUMLAH'] ?? 0);
            $incoming = 0.0;
            $outgoing = 0.0;

            match ($baseRek) {
                'Terima Dana BOS', 'Pajak Belanja Terima', 'Pergeseran Tunai', 'Bunga Bank' => [$incoming, $bank, $cash] = $this->bkuReceive($amount, $baseRek, $bank, $cash),
                'Kas Keluar', 'Kas Keluar Non Tunai', 'Pajak Belanja Setor', 'Tarik Tunai', 'Pajak Bunga' => [$outgoing, $bank, $cash] = $this->bkuSpend($amount, $baseRek, $bank, $cash),
                default => null,
            };

            $totalIn += $incoming;
            $totalOut += $outgoing;
            $parentKey = (string) ($payload['PARENT_ID_KAS_UMUM'] ?? '');
            $this->bkuLineCount++;
            $lines[] = [
                'order' => $lineOrder++,
                'rek' => $rek,
                'backfill' => $this->bkuIsBackfill($payload),
                'date' => $this->bkuDate($payload),
                'activity' => $this->bkuActivityCode($payload, $records, $kegiatanCache, $rkasMap, $chainMaps),
                'account' => (string) ($payload['KODE_REKENING'] ?? '') ?: '-',
                'evidence' => (string) ($payload['NO_BUKTI'] ?? ''),
                'parentEvidence' => $evidenceByKas[$parentKey] ?? '',
                'mirror' => $this->bkuMirrorUraian($payload),
                'mirrorUraian' => $this->bkuSourceUraian($payload),
                'description' => $this->bkuDescription($payload, $operatorMaps),
                'payment' => $this->bkuPaymentLine($payload, $operatorMaps),
                'item' => $this->bkuItemLine($payload, $operatorMaps),
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'balance' => $cashOnly ? $cash : $bank + $cash,
            ];
        }

        $rows = array_merge($rows, $this->bkuGroupedRows($lines));

        $rows[] = [
            'date' => '',
            'activity' => '',
            'account' => '',
            'evidence' => '',
            'description' => 'Jumlah',
            'incoming' => $totalIn,
            'outgoing' => $totalOut,
            'balance' => $cashOnly ? $cash : $bank + $cash,
        ];

        $this->bkuClosing = $cashOnly
            ? ['bank' => 0.0, 'cash' => $cash, 'total' => $cash]
            : ['bank' => $bank, 'cash' => $cash, 'total' => $bank + $cash];

        return $rows;
    }

    /** @return array{0:float,1:float,2:float} */
    private function bkuReceive(float $amount, string $rek, float $bank, float $cash): array
    {
        // Bunga bank masuk ke sisi bank seperti Terima Dana BOS.
        if ($rek === 'Terima Dana BOS' || $rek === 'Bunga Bank') {
            $bank += $amount;
        } else {
            $cash += $amount;
        }

        return [$amount, $bank, $cash];
    }

    /** @return array{0:float,1:float,2:float} */
    private function bkuSpend(float $amount, string $rek, float $bank, float $cash): array
    {
        // Pajak bunga dipotong dari sisi bank.
        if ($rek === 'Kas Keluar Non Tunai') {
            $bank -= $amount;
        } elseif ($rek === 'Tarik Tunai' || $rek === 'Pajak Bunga') {
            $bank -= $amount;
        } else {
            $cash -= $amount;
        }

        return [$amount, $bank, $cash];
    }

    private function bkuDate(array $payload): string
    {
        $raw = (string) ($payload['TANGGAL_TRANSAKSI'] ?? '');

        try {
            return $raw !== '' ? Carbon::parse($raw)->format('d-m-Y') : '-';
        } catch (\Throwable) {
            return '-';
        }
    }

    /**
     * Peta rantai kegiatan sekali query: periode → rapbs → ref_kode.
     * Menggantikan N query per baris pada laporan BKU.
     *
     * @param  Collection<int,array<string,mixed>>  $records
     * @return array{periode:array<string,string>,rapbs:array<string,string>,ref:array<string,string>}
     */
    private function bkuActivityChainMaps(Collection $records): array
    {
        $periodeIds = [];
        foreach ($records as $payload) {
            $id = (string) ($payload['ID_RAPBS_PERIODE'] ?? '');
            if ($id === '' && filled($payload['PARENT_ID_KAS_UMUM'] ?? null)) {
                $parent = $records->firstWhere('ID_KAS_UMUM', (string) $payload['PARENT_ID_KAS_UMUM']);
                $id = (string) (is_array($parent) ? ($parent['ID_RAPBS_PERIODE'] ?? '') : '');
            }
            if ($id !== '') {
                $periodeIds[$id] = true;
            }
        }

        $periodeMap = [];
        $rapbsIds = [];
        if ($periodeIds !== []) {
            $rows = DB::connection('school')->table('arkas_mirror_rapbs_periode')
                ->whereIn('source_key', array_keys($periodeIds))
                ->get(['source_key', 'payload']);
            foreach ($rows as $row) {
                $rapbsId = (string) (json_decode((string) ($row->payload ?? ''), true)['id_rapbs'] ?? '');
                $periodeMap[(string) $row->source_key] = $rapbsId;
                if ($rapbsId !== '') {
                    $rapbsIds[$rapbsId] = true;
                }
            }
        }

        $rapbsMap = [];
        $refKeys = [];
        if ($rapbsIds !== []) {
            $rows = DB::connection('school')->table('arkas_mirror_rapbs')
                ->whereIn('source_key', array_keys($rapbsIds))
                ->get(['source_key', 'payload']);
            foreach ($rows as $row) {
                $refKey = (string) (json_decode((string) ($row->payload ?? ''), true)['id_ref_kode'] ?? '');
                $rapbsMap[(string) $row->source_key] = $refKey;
                if ($refKey !== '') {
                    $refKeys[$refKey] = true;
                }
            }
        }

        $refMap = [];
        if ($refKeys !== []) {
            // source_key ref_kode berbentuk komposit
            // "id|tahun|sumber_dana|bentuk_pendidikan" sehingga whereIn
            // dengan id mentah tak pernah cocok; cocokkan prefix id.
            // Peta dikunci id_ref_kode mentah sesuai pemakaian di
            // bkuActivityCode().
            $query = DB::table('arkas_mirror_ref_kode');
            $query->where(function ($q) use ($refKeys): void {
                foreach (array_keys($refKeys) as $refId) {
                    $q->orWhere('source_key', 'like', $this->escapeLikePrefix((string) $refId).'|%');
                }
            });
            foreach ($query->get(['source_key', 'payload']) as $row) {
                $payload = json_decode((string) ($row->payload ?? ''), true) ?? [];
                $idRef = (string) (ArkasMirrorResolver::field($payload, ['ID_REF_KODE']) ?? '');
                if ($idRef !== '' && ! isset($refMap[$idRef])) {
                    $refMap[$idRef] = (string) (ArkasMirrorResolver::field($payload, ['ID_KODE']) ?? '');
                }
            }
        }

        return ['periode' => $periodeMap, 'rapbs' => $rapbsMap, 'ref' => $refMap];
    }

    /**
     * Kode kegiatan via rantai kas_umum → rapbs_periode → rapbs → ref_kode,
     * fallback ke snapshot RKAS (rkas_items.activity_code). Baris pajak
     * mengikuti induk belanjanya. Murni lookup array (nol query).
     *
     * @param  array<string,mixed>  $payload
     * @param  Collection<int,array<string,mixed>>  $records
     * @param  array<string,string>  $cache
     * @param  array<string,string>  $rkasMap
     * @param  array{periode:array<string,string>,rapbs:array<string,string>,ref:array<string,string>}  $chainMaps
     */
    private function bkuActivityCode(array $payload, Collection $records, array &$cache, array $rkasMap, array $chainMaps): string
    {
        $periodeId = (string) ($payload['ID_RAPBS_PERIODE'] ?? '');
        if ($periodeId === '' && filled($payload['PARENT_ID_KAS_UMUM'] ?? null)) {
            $parent = $records->firstWhere('ID_KAS_UMUM', (string) $payload['PARENT_ID_KAS_UMUM']);
            $periodeId = (string) (is_array($parent) ? ($parent['ID_RAPBS_PERIODE'] ?? '') : '');
        }
        if ($periodeId === '') {
            return '-';
        }
        if (isset($cache[$periodeId])) {
            return $cache[$periodeId];
        }

        $rapbsId = $chainMaps['periode'][$periodeId] ?? '';
        $code = $rapbsId !== '' ? ($rkasMap[strtoupper(trim($rapbsId))] ?? '') : '';
        if ($code === '' && $rapbsId !== '') {
            $refKey = $chainMaps['rapbs'][$rapbsId] ?? '';
            $code = $refKey !== '' ? ($chainMaps['ref'][$refKey] ?? '') : '';
        }

        return $cache[$periodeId] = $code !== '' ? $code : '-';
    }

    /** @return list<int> */
    private function scopeMonths(string $scope, ?int $period): array
    {
        return $this->registry->periodMonths($scope, $period);
    }

    /** @param array<string,mixed> $summary */
    private function bkuClosing(array $summary): array
    {
        $signedDate = '';
        $weekdayDate = '';
        try {
            if (filled($summary['date_to'] ?? null)) {
                $end = Carbon::parse((string) $summary['date_to']);
                $signedDate = $end->translatedFormat('d F Y');
                $weekdayDate = $end->translatedFormat('l d F Y');
            }
        } catch (\Throwable) {
            $signedDate = '';
            $weekdayDate = '';
        }

        return [
            ...$this->bkuClosing,
            'period_end' => (string) ($summary['date_to'] ?? ''),
            'period_label' => (string) ($summary['period_label'] ?? ''),
            'signed_date' => $signedDate,
            'weekday_date' => $weekdayDate,
        ];
    }

    /** @param array<string,mixed> $summary @return array{bulan:string,tahun:string} */
    private function bkuPeriodLabel(array $summary, int $year): array
    {
        $scope = (string) ($summary['scope'] ?? '');
        $period = (int) ($summary['period'] ?? 0);

        if ($scope === SpjPeriodicReportRegistry::SCOPE_MONTHLY && $period >= 1 && $period <= 12) {
            $bulan = mb_strtoupper(Carbon::create($year, $period, 1)->translatedFormat('F'));

            return ['bulan' => $bulan, 'tahun' => (string) $year];
        }

        return ['bulan' => mb_strtoupper((string) ($summary['period_label'] ?? '')), 'tahun' => (string) $year];
    }

    /**
     * @param  array<string,mixed>  $summary
     * @return array{periode:string,tanggal:string,jumlah_transaksi:int}
     */
    private function bkuMeta(array $summary, string $fundSource, int $year): array
    {
        $scope = (string) ($summary['scope'] ?? '');
        $period = (int) ($summary['period'] ?? 0);

        $periode = match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => $period >= 1 && $period <= 12
                ? ucwords(mb_strtolower(Carbon::create($year, $period, 1)->translatedFormat('F')))
                : (string) ($summary['period_label'] ?? ''),
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => $period >= 1 && $period <= 4 ? 'Triwulan '.$period : (string) ($summary['period_label'] ?? ''),
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => $period >= 1 && $period <= 2 ? 'Semester '.$period : (string) ($summary['period_label'] ?? ''),
            default => 'Tahun '.$year,
        };

        $tanggal = '-';
        try {
            $from = filled($summary['date_from'] ?? null) ? Carbon::parse((string) $summary['date_from'])->format('d-m-Y') : '';
            $to = filled($summary['date_to'] ?? null) ? Carbon::parse((string) $summary['date_to'])->format('d-m-Y') : '';
            if ($from !== '' && $to !== '') {
                $tanggal = $from.' s.d. '.$to;
            }
        } catch (\Throwable) {
            $tanggal = '-';
        }

        return [
            'sumber_dana' => $fundSource,
            'tahun' => (string) $year,
            'periode' => $periode,
            'tanggal' => $tanggal,
            'jumlah_transaksi' => $this->bkuLineCount,
        ];
    }

    /** Jumlah baris transaksi BKU (tanpa saldo awal & baris jumlah). */
    private int $bkuLineCount = 0;

    /**
     * Peta isian operator per baris mirror (sekali query): rincian item
     * (deskripsi operator) dan transaksi (uraian pembayaran operator).
     *
     * @param  Collection<int,array<string,mixed>>  $records
     * @return array{items:array<string,array{item:string,payment:string}>,transactions:array<string,string>}
     */
    private function bkuOperatorMaps(Collection $records): array
    {
        $keys = $records->map(fn ($payload): string => (string) ($payload['ID_KAS_UMUM'] ?? ''))->filter()->unique()->values();

        $items = $keys->isEmpty() ? collect() : DB::connection('school')->table('transaction_items')
            ->whereIn('source_item_id', $keys->all())
            ->get(['source_item_id', 'item_description', 'transaction_id']);

        $transactionIds = $items->pluck('transaction_id')->filter()->unique()->values();
        $kasIds = $keys->diff($items->pluck('source_item_id'))->values();

        $transactions = collect();
        if ($transactionIds->isNotEmpty() || $kasIds->isNotEmpty()) {
            $transactions = Transaction::query()
                ->where(fn ($query) => $query
                    ->whereIn('id', $transactionIds->all())
                    ->orWhereIn('id_kas_umum', $kasIds->all()))
                ->get(['id', 'id_kas_umum', 'payment_description'])
                ->keyBy('id');
        }
        $transactionsByKas = [];
        foreach ($transactions as $transaction) {
            if (filled($transaction->id_kas_umum)) {
                $transactionsByKas[(string) $transaction->id_kas_umum] = (string) ($transaction->payment_description ?? '');
            }
        }

        $itemMap = [];
        foreach ($items as $item) {
            $payment = (string) ($transactions->get($item->transaction_id)->payment_description ?? '');
            $itemMap[(string) $item->source_item_id] = [
                'item' => (string) ($item->item_description ?? ''),
                'payment' => $payment,
            ];
        }

        // Baris pajak mengikuti induk belanjanya untuk konteks operator.
        $parents = [];
        foreach ($records as $payload) {
            $parentKey = (string) ($payload['PARENT_ID_KAS_UMUM'] ?? '');
            if ($parentKey !== '' && ! isset($transactionsByKas[$parentKey])) {
                $parents[] = $parentKey;
            }
        }
        if ($parents !== []) {
            $extra = Transaction::query()->whereIn('id_kas_umum', array_values(array_unique($parents)))
                ->get(['id_kas_umum', 'payment_description']);
            foreach ($extra as $transaction) {
                $transactionsByKas[(string) $transaction->id_kas_umum] = (string) ($transaction->payment_description ?? '');
            }
        }

        return ['items' => $itemMap, 'transactions' => $transactionsByKas];
    }

    /**
     * Kelompokkan per nomor bukti tanpa tergantung urutan baris: satu baris
     * induk (payment description tebal + subtotal + saldo) diikuti rincian
     * item (nominal di ujung uraian, tanpa nominal/saldo), lalu baris Terima
     * pajak dan Setor pajak milik bukti tersebut (nomor bukti diisikan dari
     * induk, walau baris pajak tidak bersebelahan dengan belanjanya).
     * Berlaku seragam untuk satu maupun banyak rincian.
     *
     * @param  list<array<string,mixed>>  $lines
     * @return list<array<string,mixed>>
     */
    private function bkuGroupedRows(array $lines): array
    {
        // Baris backfill (shortfall murni) menempel pada grup bukti miliknya
        // sendiri bila tautan induknya tidak ada di mirror.
        $taxGroupOf = fn (array $line): string => $line['parentEvidence'] !== ''
            ? $line['parentEvidence']
            : (($line['backfill'] ?? false) ? (string) ($line['evidence'] ?? '') : '');

        $itemGroups = [];
        $taxGroups = [];
        foreach ($lines as $line) {
            // Varian "Sisa" memakai bentuk dasarnya agar ikut kelompok bukti.
            if (in_array($this->bkuBaseRek((string) ($line['rek'] ?? '')), ['Kas Keluar', 'Kas Keluar Non Tunai'], true) && $line['evidence'] !== '') {
                $itemGroups[$line['evidence']][] = $line;
            } elseif (str_starts_with($line['rek'], 'Pajak Belanja ') && $taxGroupOf($line) !== '') {
                $slot = str_starts_with($line['rek'], 'Pajak Belanja Terima') ? 'terima' : 'setor';
                $taxGroups[$taxGroupOf($line)][$slot][] = $line;
            }
        }

        $rows = [];
        $emitted = [];
        foreach ($lines as $line) {
            $bukti = $line['evidence'];
            $isItem = in_array($this->bkuBaseRek((string) ($line['rek'] ?? '')), ['Kas Keluar', 'Kas Keluar Non Tunai'], true) && $bukti !== '';
            $isTax = str_starts_with($line['rek'], 'Pajak Belanja ');

            if ($isItem) {
                if (! isset($emitted[$bukti])) {
                    $emitted[$bukti] = true;
                    $rows = array_merge($rows, $this->bkuGroupRows(
                        $bukti,
                        $itemGroups[$bukti],
                        $taxGroups[$bukti]['terima'] ?? [],
                        $taxGroups[$bukti]['setor'] ?? []
                    ));
                }

                continue;
            }

            if ($isTax && $taxGroupOf($line) !== '' && isset($itemGroups[$taxGroupOf($line)])) {
                continue;
            }

            $rows[] = $this->bkuSingleRow($line);
        }

        return $rows;
    }

    /**
     * @param  list<array<string,mixed>>  $items
     * @param  list<array<string,mixed>>  $terima
     * @param  list<array<string,mixed>>  $setor
     * @return list<array<string,mixed>>
     */
    private function bkuGroupRows(string $bukti, array $items, array $terima, array $setor): array
    {
        $rows = [];
        $all = array_merge($items, $terima, $setor);
        $first = $all[0];
        $chronologicalLast = $all[0];
        foreach ($all as $member) {
            if ($member['order'] > $chronologicalLast['order']) {
                $chronologicalLast = $member;
            }
        }
        // Induk memakai uraian non-kosong pertama segrup (bukan baris
        // pertama buta): payment operator dulu, lalu uraian sumber
        // (URAIAN/URAIAN_PAJAK), terakhir label REK. Mencegah parent
        // kosong atau generik saat baris pertama grup kebetulan
        // beruraian kosong.
        $parentLabel = '-';
        foreach ($all as $member) {
            if ((string) ($member['payment'] ?? '') !== '') {
                $parentLabel = (string) $member['payment'];

                break;
            }
        }
        if ($parentLabel === '-') {
            foreach ($all as $member) {
                if ((string) ($member['mirrorUraian'] ?? '') !== '') {
                    $parentLabel = (string) $member['mirrorUraian'];

                    break;
                }
            }
        }
        if ($parentLabel === '-') {
            foreach ($all as $member) {
                if ((string) ($member['mirror'] ?? '') !== '') {
                    $parentLabel = (string) $member['mirror'];

                    break;
                }
            }
        }
        $rows[] = [
            'row_class' => 'bku-parent',
            'date' => $first['date'],
            'activity' => $first['activity'],
            'account' => $first['account'],
            'evidence' => $bukti,
            'description' => '<div><strong>'.e($parentLabel).'</strong></div>',
            'incoming' => array_sum(array_column($items, 'incoming')),
            'outgoing' => array_sum(array_column($items, 'outgoing')),
            'balance' => $chronologicalLast['balance'],
        ];

        $child = function (array $line, bool $withAmount) use ($bukti): array {
            $nominal = $line['incoming'] != 0.0 ? $line['incoming'] : $line['outgoing'];
            $label = $line['item'] !== '' ? $line['item'] : ((string) ($line['mirror'] ?? '') !== '' ? (string) $line['mirror'] : (string) ($line['rek'] ?? '-'));
            if ($label === '') {
                $label = '-';
            }

            return [
                'date' => $line['date'],
                'activity' => $line['activity'],
                'account' => $line['account'],
                'evidence' => $bukti,
                'description' => '<div>- '.e($label)
                    .($withAmount && $nominal != 0.0 ? ' <span style="float:right">'.number_format($nominal, 0, ',', '.').'</span>' : '').'</div>',
                'incoming' => $withAmount ? '' : $line['incoming'],
                'outgoing' => $withAmount ? '' : $line['outgoing'],
                'balance' => '',
            ];
        };

        foreach ($items as $line) {
            $rows[] = $child($line, true);
        }
        foreach (array_merge($terima, $setor) as $line) {
            $rows[] = $child($line, false);
        }

        return $rows;
    }

    /** @param array<string,mixed> $line @return array<string,mixed> */
    private function bkuSingleRow(array $line): array
    {
        return [
            'date' => $line['date'],
            'activity' => $line['activity'],
            'account' => $line['account'],
            'evidence' => $line['evidence'] !== '' ? $line['evidence'] : '-',
            'description' => $line['description'],
            'incoming' => $line['incoming'],
            'outgoing' => $line['outgoing'],
            'balance' => $line['balance'],
        ];
    }

    /**
     * Sel uraian bertingkat (HTML aman): baris pertama uraian pembayaran
     * operator (abu-abu), baris kedua deskripsi isian operator; fallback
     * ke uraian mirror (URAIAN, lalu URAIAN_PAJAK, lalu REK_BKU) bila
     * keduanya kosong agar sel tidak pernah kosong.
     *
     * @param  array<string,mixed>  $payload
     * @param  array{items:array<string,array{item:string,payment:string}>,transactions:array<string,string>}  $maps
     */
    private function bkuDescription(array $payload, array $maps): string
    {
        $payment = $this->bkuPaymentLine($payload, $maps);
        $operator = $this->bkuItemLine($payload, $maps);
        $mirror = $this->bkuMirrorUraian($payload);

        $html = '';
        if ($payment !== '') {
            $html .= '<div class="bku-sub">'.e($payment).'</div>';
        }
        $html .= '<div>'.e($operator !== '' ? $operator : ($mirror !== '' ? $mirror : '-')).'</div>';

        return $html;
    }

    /**
     * Bentuk dasar REK_BKU tanpa sufiks " Sisa" ARKAS (mis.
     * "Kas Keluar Sisa" → "Kas Keluar") agar varian sisa diperlakukan
     * sama dengan bentuk dasarnya pada pengelompokan dan hitung saldo.
     */
    private function bkuBaseRek(string $rek): string
    {
        $normalized = trim($rek);

        if (str_ends_with($normalized, ' Sisa')) {
            return trim(substr($normalized, 0, -5));
        }

        return $normalized;
    }

    /**
     * Escape wildcard LIKE untuk pencocokan prefix source_key komposit
     * ("id|tahun|sumber_dana|bentuk_pendidikan").
     */
    private function escapeLikePrefix(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * Uraian sumber baris mirror: URAIAN dulu, lalu URAIAN_PAJAK (baris
     * pajak ARKAS menyimpan keterangannya di sana), lalu REK_BKU.
     *
     * @param  array<string,mixed>  $payload
     */
    private function bkuMirrorUraian(array $payload): string
    {
        $uraian = $this->bkuSourceUraian($payload);
        if ($uraian !== '') {
            return $uraian;
        }

        return trim((string) ($payload['REK_BKU'] ?? ''));
    }

    /**
     * Uraian sumber murni (tanpa fallback REK): URAIAN lalu URAIAN_PAJAK.
     *
     * @param  array<string,mixed>  $payload
     */
    private function bkuSourceUraian(array $payload): string
    {
        foreach (['URAIAN', 'URAIAN_PAJAK'] as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * Baris backfill lokal dikenali dari prefix ID kanonisnya (sama dengan
     * ArkasMirrorResolver::isBackfillRow).
     *
     * @param  array<string,mixed>  $payload
     */
    private function bkuIsBackfill(array $payload): bool
    {
        return str_starts_with(strtoupper(trim((string) ($payload['ID_KAS_UMUM'] ?? ''))), 'MIG-');
    }

    /**
     * Keranjang jenis pajak satu baris (sama dengan klasifikasi
     * ArkasMirrorResolver::taxBucket).
     *
     * @param  array<string,mixed>  $payload
     */
    private function bkuTaxBucket(array $payload): string
    {
        if ((int) ($payload['IS_PPN'] ?? 0) === 1) {
            return 'ppn';
        }
        if ((int) ($payload['IS_PPH21'] ?? 0) === 1) {
            return 'pph21';
        }
        if ((int) ($payload['IS_PPH22'] ?? 0) === 1) {
            return 'pph22';
        }
        if ((int) ($payload['IS_PPH23'] ?? 0) === 1) {
            return 'pph23';
        }
        if ((int) ($payload['IS_PPH4'] ?? 0) === 1) {
            return 'pph4';
        }
        if ((int) ($payload['IS_SSPD'] ?? 0) === 1) {
            return 'sspd';
        }

        return '__unknown';
    }

    private function bkuTaxBucketLabel(string $bucket): string
    {
        return match ($bucket) {
            'ppn' => 'PPN',
            'pph21' => 'PPh 21',
            'pph22' => 'PPh 22',
            'pph23' => 'PPh 23',
            'pph4' => 'PPh 4(2)',
            'sspd' => 'SSPD',
            default => 'Pajak',
        };
    }

    /**
     * Buang baris PAJAK backfill MIG-* yang sudah dilengkapi baris PAJAK
     * kanonis (kunci: bukti + keranjang + nominal sama persis) agar tidak
     * tampil ganda/blank; shortfall murni ditulis ulang sebagai Terima
     * agar tetap terhitung seperti agregat resolver.
     *
     * @param  Collection<int,array<string,mixed>>  $records
     * @return Collection<int,array<string,mixed>>
     */
    private function bkuDedupBackfillTax(Collection $records): Collection
    {
        $evidenceByKas = [];
        foreach ($records as $payload) {
            $kasKey = (string) ($payload['ID_KAS_UMUM'] ?? '');
            if ($kasKey !== '' && (string) ($payload['NO_BUKTI'] ?? '') !== '') {
                $evidenceByKas[$kasKey] = (string) $payload['NO_BUKTI'];
            }
        }

        $amountKey = fn (array $payload): string => number_format((float) ($payload['JUMLAH'] ?? 0), 2, '.', '');

        $canonicalKeys = [];
        foreach ($records as $payload) {
            if ($this->bkuIsBackfill($payload)) {
                continue;
            }
            if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'PAJAK') {
                continue;
            }
            $baseRek = $this->bkuBaseRek((string) ($payload['REK_BKU'] ?? ''));
            if (! str_starts_with($baseRek, 'Pajak Belanja ')) {
                continue;
            }
            $slot = str_starts_with($baseRek, 'Pajak Belanja Terima') ? 'terima' : 'setor';
            $parentKey = (string) ($payload['PARENT_ID_KAS_UMUM'] ?? '');
            $group = $evidenceByKas[$parentKey] ?? (string) ($payload['NO_BUKTI'] ?? '');
            if ($group === '') {
                continue;
            }
            $canonicalKeys[$group.'|'.$slot.'|'.$this->bkuTaxBucket($payload).'|'.$amountKey($payload)] = true;
        }

        return $records->filter(function (array $payload) use ($canonicalKeys, $amountKey): bool {
            if (! $this->bkuIsBackfill($payload)) {
                return true;
            }
            if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'PAJAK') {
                return true;
            }
            $group = (string) ($payload['NO_BUKTI'] ?? '');
            if ($group === '') {
                return true;
            }
            $bucket = $this->bkuTaxBucket($payload);

            return ! isset($canonicalKeys[$group.'|terima|'.$bucket.'|'.$amountKey($payload)]);
        })->values()->map(function (array $payload): array {
            // Shortfall murni: baris MIG-T-* hanya membawa ID + JUMLAH +
            // flag; lengkapi tampilan Terima agar terhitung dan terbaca.
            // Label "(data lokal)" menandai angka yang hanya ada di
            // database lokal (tanpa padanan baris PAJAK ARKAS).
            if ($this->bkuIsBackfill($payload)
                && strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) === 'PAJAK'
                && trim((string) ($payload['REK_BKU'] ?? '')) === '') {
                $payload['REK_BKU'] = 'Pajak Belanja Terima';
                if (trim((string) ($payload['URAIAN'] ?? '')) === '' && trim((string) ($payload['URAIAN_PAJAK'] ?? '')) === '') {
                    $payload['URAIAN'] = 'Terima '.$this->bkuTaxBucketLabel($this->bkuTaxBucket($payload)).' (data lokal)';
                }
            }

            return $payload;
        });
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array{items:array<string,array{item:string,payment:string}>,transactions:array<string,string>}  $maps
     */
    private function bkuPaymentLine(array $payload, array $maps): string
    {
        $key = (string) ($payload['ID_KAS_UMUM'] ?? '');
        $payment = ($maps['items'][$key] ?? null)['payment'] ?? null;
        if ($payment === null || $payment === '') {
            $lookupKey = (string) ($payload['PARENT_ID_KAS_UMUM'] ?? '') !== ''
                ? (string) $payload['PARENT_ID_KAS_UMUM']
                : $key;
            $payment = $maps['transactions'][$lookupKey] ?? '';
        }

        return (string) $payment;
    }

    /**
     * @param  array<string,mixed>  $payload
     * @param  array{items:array<string,array{item:string,payment:string}>,transactions:array<string,string>}  $maps
     */
    private function bkuItemLine(array $payload, array $maps): string
    {
        $key = (string) ($payload['ID_KAS_UMUM'] ?? '');

        return (string) (($maps['items'][$key] ?? null)['item'] ?? '');
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function taxColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'siplah', 'label' => 'Siplah', 'type' => 'text'],
            ['key' => 'ppn', 'label' => 'PPN', 'type' => 'money'],
            ['key' => 'pph21', 'label' => 'PPh 21', 'type' => 'money'],
            ['key' => 'pph22', 'label' => 'PPh 22', 'type' => 'money'],
            ['key' => 'pph23', 'label' => 'PPh 23', 'type' => 'money'],
            ['key' => 'pph4', 'label' => 'PPh 4(2)', 'type' => 'money'],
            ['key' => 'sspd', 'label' => 'SSPD', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Total Pajak', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function taxRows(Collection $transactions): array
    {
        return $transactions
            ->filter(fn (Transaction $transaction): bool => (float) $transaction->sourceValue('tax_total') > 0)
            ->values()
            ->map(fn (Transaction $transaction, int $index): array => [
                'no' => $index + 1,
                'date' => $transaction->sourceDateString('transaction_date', 'd-m-Y') ?? '-',
                'evidence' => $transaction->sourceValue('no_bukti') ?: '-',
                'description' => ($transaction->payment_description ?: null) ?? $transaction->sourceValue('description') ?: '-',
                'siplah' => $transaction->sourceValue('is_siplah') ? 'Ya' : 'Tidak',
                'ppn' => (float) $transaction->sourceValue('ppn'),
                'pph21' => (float) $transaction->sourceValue('pph21'),
                'pph22' => (float) $transaction->sourceValue('pph22'),
                'pph23' => (float) $transaction->sourceValue('pph23'),
                'pph4' => (float) $transaction->sourceValue('pph4'),
                'sspd' => (float) $transaction->sourceValue('sspd'),
                'tax' => (float) $transaction->sourceValue('tax_total'),
            ])->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function activityColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'code', 'label' => 'Kode Kegiatan', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Kegiatan', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Transaksi', 'type' => 'integer'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Realisasi Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function activityRows(Collection $transactions): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction): string => ($transaction->sourceValue('activity_code') ?: '-').'|'.($transaction->sourceValue('activity_name') ?: '-'))
            ->values()
            ->map(function (Collection $group, int $index): array {
                /** @var Transaction $first */
                $first = $group->first();

                return [
                    'no' => $index + 1,
                    'code' => $first->sourceValue('activity_code') ?: '-',
                    'name' => $first->sourceValue('activity_name') ?: '-',
                    'count' => $group->count(),
                    'gross' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
                    'tax' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('tax_total')),
                    'net' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('net_amount')),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function accountColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'code', 'label' => 'Kode Rekening', 'type' => 'text'],
            ['key' => 'name', 'label' => 'Nama Rekening', 'type' => 'text'],
            ['key' => 'count', 'label' => 'Transaksi', 'type' => 'integer'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Realisasi Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function accountRows(Collection $transactions): array
    {
        return $transactions
            ->groupBy(fn (Transaction $transaction): string => ($transaction->sourceValue('account_code') ?: '-').'|'.($transaction->sourceValue('account_name') ?: '-'))
            ->values()
            ->map(function (Collection $group, int $index): array {
                /** @var Transaction $first */
                $first = $group->first();

                return [
                    'no' => $index + 1,
                    'code' => $first->sourceValue('account_code') ?: '-',
                    'name' => $first->sourceValue('account_name') ?: '-',
                    'count' => $group->count(),
                    'gross' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('gross_amount')),
                    'tax' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('tax_total')),
                    'net' => (float) $group->sum(fn (Transaction $transaction): float => (float) $transaction->sourceValue('net_amount')),
                ];
            })
            ->sortBy('code')
            ->values()
            ->all();
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function rekapBospColumns(): array
    {
        // Grid 7x12 + footer dirender cabang khusus (bukan report-table generik).
        return [];
    }

    /** @return list<array<string,mixed>> */
    private function rekapBospRows(string $scope, ?int $period): array
    {
        return [];
    }

    /**
     * Grid + saldo REKAPITULASI REALISASI PENGGUNAAN DANA BOSP model ARKAS.
     *
     * Sel = jumlah BELANJA per (SNP, sub program) dari kode kegiatan baris
     * mirror. Penerimaan = Terima Dana BOS distinct (bukti+tanggal+nominal),
     * karena ARKAS menduplikasi baris terima per revisi anggaran.
     *
     * @return array{grid:array<int,array<int,float>>,rowTotals:array<int,float>,colTotals:array<int,float>,grand:float,unmapped:float,prev:float,received:float,used:float,closing:float}
     */
    private function rekapBospData(string $scope, ?int $period): array
    {
        $months = $this->scopeMonths($scope, $period);
        $year = (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year);

        $grid = [];
        for ($row = 1; $row <= 7; $row++) {
            for ($col = 1; $col <= 12; $col++) {
                $grid[$row][$col] = 0.0;
            }
        }

        $activityMap = $this->rekapActivityMap();
        $receivedKeys = [];
        $received = 0.0;
        $used = 0.0;
        $unmapped = 0.0;

        if ($months !== []) {
            $placeholders = implode(',', array_fill(0, count($months), '?'));
            $records = DB::connection('school')->table('arkas_mirror_kas_umum')
                ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
                ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
                ->orderByRaw("json_extract(payload, '\$.TANGGAL_TRANSAKSI')")
                ->orderBy('id')
                ->get(['payload'])
                ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER))
                ->values();

            $fundSourceId = $this->context->fundSourceId();
            if ($fundSourceId !== null) {
                $records = $records->filter(
                    fn (array $payload): bool => $this->kasMatchesFund($payload, $fundSourceId)
                )->values();
            }

            foreach ($records as $payload) {
                $amount = (float) ($payload['JUMLAH'] ?? 0);
                if (($payload['REK_BKU'] ?? '') === 'Terima Dana BOS') {
                    $key = (string) ($payload['NO_BUKTI'] ?? '').'|'.(string) ($payload['TANGGAL_TRANSAKSI'] ?? '').'|'.$amount;
                    if (! isset($receivedKeys[$key])) {
                        $receivedKeys[$key] = true;
                        $received += $amount;
                    }

                    continue;
                }
                if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'BELANJA') {
                    continue;
                }
                $used += $amount;
                $cell = BospRekapStandardMapper::cellForActivity($activityMap[(string) ($payload['ID_RAPBS'] ?? '')] ?? null);
                if ($cell === null) {
                    $unmapped += $amount;

                    continue;
                }
                $grid[$cell['row']][$cell['col']] += $amount;
            }
        }

        $prev = $this->rekapPreviousBalance($year, $months);

        $rowTotals = [];
        $colTotals = [];
        for ($col = 1; $col <= 12; $col++) {
            $colTotals[$col] = 0.0;
        }
        foreach ($grid as $row => $cols) {
            $rowTotals[$row] = array_sum($cols);
            foreach ($cols as $col => $value) {
                $colTotals[$col] += $value;
            }
        }

        return [
            'grid' => $grid,
            'rowTotals' => $rowTotals,
            'colTotals' => $colTotals,
            'grand' => array_sum($rowTotals),
            'unmapped' => $unmapped,
            'prev' => $prev,
            'received' => $received,
            'used' => $used,
            'closing' => $prev + $received - $used,
        ];
    }

    /**
     * ID_RAPBS -> kode kegiatan. Urutan: field langsung payload, tabel
     * legacy arkas_rkas_items, lalu ref_kode pusat.
     *
     * @return array<string,string>
     */
    private function rekapActivityMap(): array
    {
        $map = [];
        foreach (DB::connection('school')->table('arkas_mirror_rapbs')->get(['source_key', 'payload']) as $record) {
            $payload = array_change_key_case(json_decode((string) $record->payload, true) ?? [], CASE_UPPER);
            $code = trim((string) ($payload['KODE_KEGIATAN'] ?? ''), '.');
            if ($code !== '') {
                $map[(string) $record->source_key] = $code;
            }
        }

        if (Schema::connection('school')->hasTable('arkas_rkas_items')) {
            foreach (DB::connection('school')->table('arkas_rkas_items')->whereNotNull('activity_code')->get(['source_rapbs_id', 'activity_code']) as $row) {
                $code = trim((string) $row->activity_code, '.');
                if (! isset($map[(string) $row->source_rapbs_id]) && $code !== '') {
                    $map[(string) $row->source_rapbs_id] = $code;
                }
            }
        }

        $missingRef = [];
        foreach (DB::connection('school')->table('arkas_mirror_rapbs')->get(['source_key', 'payload']) as $record) {
            if (isset($map[(string) $record->source_key])) {
                continue;
            }
            $payload = array_change_key_case(json_decode((string) $record->payload, true) ?? [], CASE_UPPER);
            $refId = (string) ($payload['ID_REF_KODE'] ?? '');
            if ($refId !== '') {
                $missingRef[$refId][] = (string) $record->source_key;
            }
        }
        if ($missingRef !== [] && Schema::hasTable('arkas_mirror_ref_kode')) {
            // source_key ref_kode berbentuk komposit
            // "id|tahun|sumber_dana|bentuk_pendidikan" sehingga whereIn
            // dengan id mentah tak pernah cocok; cocokkan prefix id lalu
            // petakan kembali via ID_REF_KODE pada payload.
            $refQuery = DB::table('arkas_mirror_ref_kode');
            $refQuery->where(function ($q) use ($missingRef): void {
                foreach (array_keys($missingRef) as $refId) {
                    $q->orWhere('source_key', 'like', $this->escapeLikePrefix((string) $refId).'|%');
                }
            });
            foreach ($refQuery->orderBy('source_key')->get(['source_key', 'payload']) as $row) {
                $payload = json_decode((string) $row->payload, true) ?? [];
                $idRef = (string) (ArkasMirrorResolver::field($payload, ['ID_REF_KODE']) ?? '');
                if ($idRef === '' || ! isset($missingRef[$idRef])) {
                    continue;
                }
                $code = trim((string) (ArkasMirrorResolver::field($payload, ['ID_KODE']) ?? ''), '.');
                if ($code === '') {
                    continue;
                }
                foreach ($missingRef[$idRef] as $rapbsId) {
                    if (! isset($map[$rapbsId])) {
                        $map[$rapbsId] = $code;
                    }
                }
            }
        }

        return $map;
    }

    /**
     * Saldo periode sebelumnya: penerimaan - penggunaan sejak awal tahun
     * anggaran sampai sebelum bulan pertama periode (penerimaan distinct
     * seperti periode berjalan).
     */
    private function rekapPreviousBalance(int $year, array $months): float
    {
        if ($months === []) {
            return 0.0;
        }
        $firstMonth = min($months);
        if ($firstMonth <= 1) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, $firstMonth - 1, '?'));
        $received = 0.0;
        $used = 0.0;
        $receivedKeys = [];
        $records = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
            ->whereRaw(
                "CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})",
                range(1, $firstMonth - 1)
            )
            ->get(['payload'])
            ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER))
            ->values();

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null) {
            $records = $records->filter(
                fn (array $payload): bool => $this->kasMatchesFund($payload, $fundSourceId)
            )->values();
        }

        foreach ($records as $payload) {
            $amount = (float) ($payload['JUMLAH'] ?? 0);
            if (($payload['REK_BKU'] ?? '') === 'Terima Dana BOS') {
                $key = (string) ($payload['NO_BUKTI'] ?? '').'|'.(string) ($payload['TANGGAL_TRANSAKSI'] ?? '').'|'.$amount;
                if (! isset($receivedKeys[$key])) {
                    $receivedKeys[$key] = true;
                    $received += $amount;
                }

                continue;
            }
            if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) === 'BELANJA') {
                $used += $amount;
            }
        }

        return $received - $used;
    }

    /**
     * Judul resmi: PERIODE TANGGAL + label tahap/bulan/triwulan/semester/tahun.
     *
     * @param  array<string,mixed>  $summary
     * @return array{range:string,phase:string}
     */
    private function rekapBospTitle(array $summary, int $year): array
    {
        $range = '-';
        try {
            $from = filled($summary['date_from'] ?? null) ? Carbon::parse((string) $summary['date_from']) : null;
            $to = filled($summary['date_to'] ?? null) ? Carbon::parse((string) $summary['date_to']) : null;
            if ($from && $to) {
                $range = $from->translatedFormat('d F Y').' s/d '.$to->translatedFormat('d F Y');
            }
        } catch (\Throwable) {
            $range = '-';
        }

        $scope = (string) ($summary['scope'] ?? '');
        $period = (int) ($summary['period'] ?? 0);
        $phase = match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => $period >= 1 && $period <= 12
                ? mb_strtoupper(Carbon::create($year, $period, 1)->translatedFormat('F')).' TAHUN '.$year : 'TAHUN '.$year,
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => $period >= 1 && $period <= 4
                ? 'TRIWULAN '.$period.' TAHUN '.$year : 'TAHUN '.$year,
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => $period >= 1 && $period <= 2
                ? 'TAHAP '.$period.' TAHUN '.$year : 'TAHUN '.$year,
            default => 'TAHUN '.$year,
        };

        return ['range' => $range, 'phase' => $phase];
    }

    /**
     * Grid REKAPITULASI A-1: 8 program resmi x jenis belanja.
     *
     * Aturan terverifikasi terhadap keluaran resmi TW IV 2024 (seluruh
     * angka cocok per baris): baris = segmen pertama kode kegiatan
     * (01–08); kolom Pegawai = sub-program 12 honorarium.
     *
     * Belanja Modal dipisah dari kode rekening (2026-10-20): 5.2.02.*
     * masuk Peralatan dan Mesin, 5.2 lainnya (mis. 5.2.05 buku/aset
     * tetap lainnya) masuk Aset Tetap Lainnya BOS. Seluruh baris modal
     * juga menambah kolom Jumlah. Bila kode rekening kosong, dipakai
     * fallback lama: kegiatan 05.02.* atau uraian mengandung "buku"
     * dianggap Aset Tetap Lainnya.
     *
     * @return array{rows:array<int,array{no:int,program:string,pegawai:float,barang_jasa:float,modal_mesin:float,modal_aset:float,modal_jumlah:float,total:float}>,totals:array{pegawai:float,barang_jasa:float,modal_mesin:float,modal_aset:float,modal_jumlah:float,grand:float},unmapped:float,unmappedCount:int}
     */
    private function bosA1Data(Collection $transactions): array
    {
        $rows = [];
        foreach (BospRekapStandardMapper::a1Programs() as $index => $program) {
            $rows[$index] = [
                'no' => $index,
                'program' => $program,
                'pegawai' => 0.0,
                'barang_jasa' => 0.0,
                'modal_mesin' => 0.0,
                'modal_aset' => 0.0,
                'modal_jumlah' => 0.0,
                'total' => 0.0,
            ];
        }

        $unmapped = 0.0;
        $unmappedCount = 0;
        foreach ($transactions as $transaction) {
            $code = $transaction->sourceValue('activity_code');
            $row = BospRekapStandardMapper::a1RowForActivity($code);
            $gross = (float) $transaction->sourceValue('gross_amount');
            if ($row === null) {
                $unmapped += $gross;
                $unmappedCount++;

                continue;
            }
            $accountCode = trim((string) $transaction->sourceValue('account_code'));
            $modalColumn = $this->a1ModalColumn($accountCode);
            if (BospRekapStandardMapper::a1IsHonor($code)) {
                $rows[$row]['pegawai'] += $gross;
            } elseif ($modalColumn === 'modal_mesin') {
                $rows[$row]['modal_mesin'] += $gross;
                $rows[$row]['modal_jumlah'] += $gross;
            } elseif ($modalColumn === 'modal_aset'
                || ($accountCode === '' && $this->a1IsAsetTetapLainnya($code, (string) $transaction->sourceValue('item_description')))) {
                $rows[$row]['modal_aset'] += $gross;
                $rows[$row]['modal_jumlah'] += $gross;
            } else {
                $rows[$row]['barang_jasa'] += $gross;
            }
            $rows[$row]['total'] += $gross;
        }

        $totals = ['pegawai' => 0.0, 'barang_jasa' => 0.0, 'modal_mesin' => 0.0, 'modal_aset' => 0.0, 'modal_jumlah' => 0.0, 'grand' => 0.0];
        foreach ($rows as $row) {
            $totals['pegawai'] += $row['pegawai'];
            $totals['barang_jasa'] += $row['barang_jasa'];
            $totals['modal_mesin'] += $row['modal_mesin'];
            $totals['modal_aset'] += $row['modal_aset'];
            $totals['modal_jumlah'] += $row['modal_jumlah'];
            $totals['grand'] += $row['total'];
        }

        return ['rows' => $rows, 'totals' => $totals, 'unmapped' => $unmapped, 'unmappedCount' => $unmappedCount];
    }

    /**
     * Grid Format BOS K-2: rencana anggaran per 8 program resmi x triwulan.
     *
     * Data diambil dari RkasReportService::build('tahunan') — baris level
     * 'program' membawa rincian triwulan 'tw' (1..4) dari periode mirror
     * ARKAS. Baris program dipetakan ke 1..8 via
     * BospRekapStandardMapper::a1RowForActivity() dari segmen pertama kode
     * kegiatan.
     *
     * Keterbatasan: bila mirror belum punya rincian periode (seluruh 'tw'
     * nol) padahal total tahunan ada, total tersebut ditampilkan di kolom
     * triwulan berjalan dan nol di triwulan lain.
     *
     * Kolom kumulatif: laporan triwulan N hanya memuat anggaran sampai
     * triwulan N (TW 1..N); kolom triwulan setelahnya bernilai 0, dan
     * "Jumlah" adalah penjumlahan kolom yang tampil (kumulatif), bukan
     * pagu tahunan penuh. Semua baris program 1.1–1.8 tetap ditampilkan.
     *
     * @return array{programs:array<int,array{no:string,label:string,jumlah:float,tw:array<int,float>}>,totals:array{jumlah:float,tw:array<int,float>},quarter:int,quarter_label:string,month_range:string,has_data:bool}
     */
    private function k2Data(string $scope, ?int $period): array
    {
        $programs = [];
        foreach (BospRekapStandardMapper::a1Programs() as $index => $label) {
            $programs[$index] = [
                'no' => '1.'.$index,
                'label' => $label,
                'jumlah' => 0.0,
                'tw' => [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0],
            ];
        }

        $quarter = ($scope === SpjPeriodicReportRegistry::SCOPE_QUARTERLY && $period >= 1 && $period <= 4)
            ? $period
            : 1;
        $hasData = false;

        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null && $fundSourceId > 0) {
            $rkas = $this->rkas->build('tahunan', [
                'school_id' => $this->context->schoolId(),
                'fiscal_year_id' => $this->context->fiscalYearId(),
                'fund_source_id' => $fundSourceId,
                'revision' => '',
            ]);
            if (is_array($rkas)) {
                foreach ($rkas['lines'] ?? [] as $line) {
                    if (($line['level'] ?? '') !== 'program') {
                        continue;
                    }
                    $row = BospRekapStandardMapper::a1RowForActivity((string) ($line['activity_code'] ?? ''));
                    if ($row === null) {
                        continue;
                    }
                    $hasData = true;
                    $tw = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
                    foreach ([1, 2, 3, 4] as $q) {
                        $tw[$q] = (float) ($line['tw'][$q] ?? 0);
                    }
                    $jumlah = (float) ($line['jumlah'] ?? 0);
                    if (array_sum($tw) <= 0 && $jumlah > 0) {
                        $tw[$quarter] = $jumlah;
                    }
                    // Triwulan setelah periode terpilih belum dilaporkan:
                    // kolomnya tetap ada namun bernilai 0.
                    foreach ([1, 2, 3, 4] as $q) {
                        if ($q > $quarter) {
                            $tw[$q] = 0.0;
                        }
                    }
                    $programs[$row]['jumlah'] += array_sum($tw);
                    foreach ([1, 2, 3, 4] as $q) {
                        $programs[$row]['tw'][$q] += $tw[$q];
                    }
                }
            }
        }

        $totals = ['jumlah' => 0.0, 'tw' => [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0]];
        foreach ($programs as $program) {
            $totals['jumlah'] += $program['jumlah'];
            foreach ([1, 2, 3, 4] as $q) {
                $totals['tw'][$q] += $program['tw'][$q];
            }
        }

        return [
            'programs' => $programs,
            'totals' => $totals,
            'quarter' => $quarter,
            'quarter_label' => $this->k2QuarterLabel($quarter),
            'month_range' => $this->k2MonthRange($quarter, (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year)),
            'has_data' => $hasData,
        ];
    }

    /**
     * Data RINCIAN PENGGUNAAN DANA BOS (RPD BOS) resmi.
     *
     * Hierarki baris: "I PROGRAM SEKOLAH" -> 8 program (01.-08.) -> sub
     * (03.03.) -> kegiatan (03.03.14.) -> item rincian dengan Vol/Satuan/
     * Tarif Harga. Kolom "Jumlah" memakai alokasi periode terpilih dari
     * revisi disahkan terakhir; kolom bulan memakai realisasi BELANJA kas
     * umum per ID_RAPBS pada bulan triwulan berjalan.
     *
     * Jumlah/Vol/Satuan/Tarif mengikuti alokasi periode terpilih: pecahan
     * arkas_mirror_rapbs_periode pada snapshot (`periods`) yang bulannya
     * atau triwulannya cocok; jumlah dan volume dijumlahkan. Baris yang
     * punya pecahan periode terkoordinat tetapi tidak ada di periode
     * terpilih berjumlah nol dan tidak ditampilkan. Payload tahunan
     * arkas_mirror_rapbs (JUMLAH, VOLUME_TOTAL/VOLUME, SATUAN/UNIT,
     * HARGA_SATUAN) hanya
     * dipakai bila baris sama sekali tidak punya pecahan periode
     * terkoordinat. Tautan realisasi: kas_umum ID_RAPBS_PERIODE ->
     * rapbs_periode -> ID_RAPBS, fallback ID_RAPBS langsung dari baris
     * kas.
     *
     * @return array{rows:array<int,array{level:string,no:int,code:string,label:string,volume:float,unit:string,unit_price:float,jumlah:float,months:array<int,float>}>,totals:array{jumlah:float,months:array<int,float>},quarter:int,quarter_label:string,month_names:array<int,string>,month_range:string,has_data:bool}
     */
    private function rpdData(string $scope, ?int $period): array
    {
        $quarter = ($scope === SpjPeriodicReportRegistry::SCOPE_QUARTERLY && $period >= 1 && $period <= 4)
            ? $period
            : 1;
        $months = range((($quarter - 1) * 3) + 1, $quarter * 3);
        $year = (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year);
        $fundSourceId = (int) ($this->context->fundSourceId() ?? 0);

        $rows = [];
        $hasData = false;

        if ($fundSourceId > 0) {
            $revisions = $this->budgets->revisions($fundSourceId, $year);
            $latestApprovedId = collect($revisions)->where('status', 'approved')->last()['id'] ?? null;
            $snapshot = $this->budgets->snapshot($fundSourceId, $year, $latestApprovedId === null ? null : [$latestApprovedId]);
            /** @var Collection<int,array> $budgetRows */
            $budgetRows = $snapshot['rows'] ?? collect();

            $realization = $this->rpdRealization($months, $year);

            // Kumpulkan item per hierarki: program -> sub -> kegiatan.
            /** @var array<int,array{subs:array<string,array{label:string,activities:array<string,array{label:string,items:array<int,array<string,mixed>}>}>}>} $tree */
            $tree = [];
            foreach ($budgetRows as $row) {
                $amount = (float) ($row['amount'] ?? 0);
                if ($amount <= 0) {
                    continue;
                }
                $activityCode = trim((string) ($row['activity_code'] ?? ''), '.');
                $program = BospRekapStandardMapper::a1RowForActivity($activityCode);
                if ($program === null) {
                    continue;
                }
                $parts = array_values(array_filter(explode('.', $activityCode), fn (string $part): bool => $part !== ''));
                $subCode = count($parts) >= 2 ? $parts[0].'.'.$parts[1] : $parts[0];
                $subNo = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;

                $payload = is_array($row['payload'] ?? null) ? $row['payload'] : [];
                $rapbsId = (string) ($row['source_rapbs_id'] ?? '');
                $itemMonths = [];
                foreach ($months as $month) {
                    $itemMonths[$month] = (float) ($realization[$rapbsId][$month] ?? 0);
                }

                // Jumlah/Vol/Satuan/Tarif mengikuti alokasi periode terpilih
                // (arkas_mirror_rapbs_periode lewat snapshot `periods`).
                // Bila baris punya pecahan periode terkoordinat tetapi
                // tidak ada pada periode terpilih, nilainya nol/kosong —
                // bukan angka tahunan. Payload tahunan hanya fallback saat
                // baris sama sekali tidak punya pecahan periode terkoordinat.
                $periodAmount = 0.0;
                $periodVolume = 0.0;
                $periodUnit = '';
                $periodUnitPrice = 0.0;
                $hasCoordinatedPeriod = false;
                $hasPeriodMatch = false;
                foreach ((array) ($row['periods'] ?? []) as $period) {
                    if (! is_array($period)) {
                        continue;
                    }
                    $periodMonth = (int) ($period['__MONTH_NUMBER'] ?? 0);
                    $periodQuarter = (int) ($period['__QUARTER_NUMBER'] ?? 0);
                    if ($periodMonth > 0 || $periodQuarter > 0) {
                        $hasCoordinatedPeriod = true;
                    }
                    if (! in_array($periodMonth, $months, true) && $periodQuarter !== $quarter) {
                        continue;
                    }
                    $hasPeriodMatch = true;
                    $periodAmount += (float) (ArkasMirrorResolver::field($period, ['JUMLAH', 'AMOUNT', 'JUMLAH_TOTAL']) ?? 0);
                    $periodVolume += (float) (ArkasMirrorResolver::field($period, ['VOLUME', 'VOLUME_TOTAL']) ?? 0);
                    if ($periodUnit === '') {
                        $periodUnit = (string) (ArkasMirrorResolver::field($period, ['SATUAN', 'UNIT']) ?? '');
                    }
                    if ($periodUnitPrice <= 0.0) {
                        $periodUnitPrice = (float) (ArkasMirrorResolver::field($period, ['HARGA_SATUAN']) ?? 0);
                    }
                }
                $fallbackVolume = (float) (ArkasMirrorResolver::field($payload, ['VOLUME_TOTAL', 'VOLUME']) ?? 0);
                $fallbackUnit = (string) (ArkasMirrorResolver::field($payload, ['SATUAN', 'UNIT']) ?? '');
                $fallbackUnitPrice = (float) (ArkasMirrorResolver::field($payload, ['HARGA_SATUAN']) ?? 0);
                $itemVolume = $hasPeriodMatch ? $periodVolume : ($hasCoordinatedPeriod ? 0.0 : $fallbackVolume);
                $itemUnit = $hasPeriodMatch
                    ? ($periodUnit !== '' ? $periodUnit : $fallbackUnit)
                    : ($hasCoordinatedPeriod ? '' : $fallbackUnit);
                $itemUnitPrice = $hasPeriodMatch
                    ? ($periodUnitPrice > 0.0 ? $periodUnitPrice : $fallbackUnitPrice)
                    : ($hasCoordinatedPeriod ? 0.0 : $fallbackUnitPrice);
                $itemAmount = $hasPeriodMatch ? $periodAmount : ($hasCoordinatedPeriod ? 0.0 : $amount);
                // Baris tanpa alokasi pada periode terpilih tidak ditampilkan.
                if ($itemAmount <= 0) {
                    continue;
                }
                $hasData = true;

                $tree[$program] ??= ['subs' => []];
                $tree[$program]['subs'][$subCode] ??= [
                    'label' => BospRekapStandardMapper::k7SubProgramLabel($subNo),
                    'activities' => [],
                ];
                $tree[$program]['subs'][$subCode]['activities'][$activityCode] ??= [
                    'label' => trim((string) ($row['activity_name'] ?? '')) ?: $activityCode,
                    'items' => [],
                ];
                $tree[$program]['subs'][$subCode]['activities'][$activityCode]['items'][] = [
                    'uraian' => trim((string) ($row['description'] ?? '')) ?: trim((string) ($row['activity_name'] ?? '')),
                    'volume' => $itemVolume,
                    'unit' => $itemUnit,
                    'unit_price' => $itemUnitPrice,
                    'jumlah' => $itemAmount,
                    'months' => $itemMonths,
                ];
            }

            $programLabels = BospRekapStandardMapper::k7Programs();
            $totals = ['jumlah' => 0.0, 'months' => array_fill_keys($months, 0.0)];
            $headerRow = fn (string $level, string $code, string $label, float $jumlah, array $rowMonths): array => [
                'level' => $level,
                'no' => 0,
                'code' => $code,
                'label' => $label,
                'volume' => 0.0,
                'unit' => '',
                'unit_price' => 0.0,
                'jumlah' => $jumlah,
                'months' => $rowMonths,
            ];
            foreach ($programLabels as $programNo => $programLabel) {
                $programMonths = array_fill_keys($months, 0.0);
                $programJumlah = 0.0;
                $subRows = [];
                $subs = $tree[$programNo]['subs'] ?? [];
                uksort($subs, fn (string $a, string $b): int => strnatcasecmp($a, $b));
                foreach ($subs as $subCode => $sub) {
                    $subMonths = array_fill_keys($months, 0.0);
                    $subJumlah = 0.0;
                    $activityRows = [];
                    $activities = $sub['activities'];
                    uksort($activities, fn (string $a, string $b): int => strnatcasecmp($a, $b));
                    foreach ($activities as $activityCode => $activity) {
                        $activityMonths = array_fill_keys($months, 0.0);
                        $activityJumlah = 0.0;
                        $itemRows = [];
                        foreach ($activity['items'] as $item) {
                            $activityJumlah += $item['jumlah'];
                            foreach ($months as $month) {
                                $activityMonths[$month] += $item['months'][$month];
                            }
                            $itemRows[] = [
                                'level' => 'item',
                                'no' => 0,
                                'code' => $activityCode.'.',
                                'label' => $item['uraian'],
                                'volume' => $item['volume'],
                                'unit' => $item['unit'],
                                'unit_price' => $item['unit_price'],
                                'jumlah' => $item['jumlah'],
                                'months' => $item['months'],
                            ];
                        }
                        $subJumlah += $activityJumlah;
                        foreach ($months as $month) {
                            $subMonths[$month] += $activityMonths[$month];
                        }
                        $activityRows[] = $headerRow('activity', $activityCode.'.', $activity['label'], $activityJumlah, $activityMonths);
                        foreach ($itemRows as $itemRow) {
                            $activityRows[] = $itemRow;
                        }
                    }
                    $programJumlah += $subJumlah;
                    foreach ($months as $month) {
                        $programMonths[$month] += $subMonths[$month];
                    }
                    $subRows[] = $headerRow('sub', $subCode.'.', $sub['label'], $subJumlah, $subMonths);
                    foreach ($activityRows as $activityRow) {
                        $subRows[] = $activityRow;
                    }
                }
                $totals['jumlah'] += $programJumlah;
                foreach ($months as $month) {
                    $totals['months'][$month] += $programMonths[$month];
                }
                $rows[] = $headerRow('program', sprintf('%02d.', $programNo), $programLabel, $programJumlah, $programMonths);
                foreach ($subRows as $subRow) {
                    $rows[] = $subRow;
                }
            }
            // Nomor urut kolom No mengikuti urutan tampil.
            foreach ($rows as $index => $row) {
                $rows[$index]['no'] = $index + 1;
            }
        }

        $monthNames = RkasReportService::MONTH_NAMES;

        return [
            'rows' => $rows,
            'totals' => $totals,
            'quarter' => $quarter,
            'quarter_label' => $this->k2QuarterLabel($quarter),
            'month_names' => array_combine($months, array_map(fn (int $m): string => $monthNames[$m - 1], $months)),
            'month_range' => 'BULAN '.mb_strtoupper($monthNames[$months[0] - 1]).' S.D '.mb_strtoupper($monthNames[$months[2] - 1]).' '.$year,
            'has_data' => $hasData,
        ];
    }

    /**
     * Realisasi BELANJA kas umum per ID_RAPBS per bulan: [id_rapbs => [bulan => jumlah]].
     *
     * Tautan memakai rantai kanonis kas_umum.ID_RAPBS_PERIODE ->
     * rapbs_periode.ID_RAPBS, fallback ID_RAPBS langsung dari baris kas.
     *
     * @param  array<int>  $months
     * @return array<string,array<int,float>>
     */
    private function rpdRealization(array $months, int $year): array
    {
        $map = [];
        if ($months === []) {
            return $map;
        }

        $fundSourceId = $this->context->fundSourceId();
        $periodToRapbs = [];
        foreach (DB::connection('school')->table('arkas_mirror_rapbs_periode')->get(['payload']) as $record) {
            $payload = json_decode((string) $record->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            $periodId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS_PERIODE']) ?? '');
            $rapbsId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '');
            if ($periodId !== '' && $rapbsId !== '') {
                $periodToRapbs[$periodId] = $rapbsId;
            }
        }

        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $records = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
            ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
            ->get(['payload']);

        foreach ($records as $record) {
            $payload = array_change_key_case(json_decode((string) $record->payload, true) ?? [], CASE_UPPER);
            if ($fundSourceId !== null && ! $this->kasMatchesFund($payload, $fundSourceId)) {
                continue;
            }
            if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) !== 'BELANJA') {
                continue;
            }
            $periodId = (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS_PERIODE']) ?? '');
            $rapbsId = $periodToRapbs[$periodId] ?? (string) (ArkasMirrorResolver::field($payload, ['ID_RAPBS']) ?? '');
            if ($rapbsId === '') {
                continue;
            }
            try {
                $month = (int) Carbon::parse((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''))->month;
            } catch (\Throwable) {
                continue;
            }
            if (! in_array($month, $months, true)) {
                continue;
            }
            $map[$rapbsId] ??= array_fill_keys($months, 0.0);
            $map[$rapbsId][$month] += (float) ($payload['JUMLAH'] ?? 0);
        }

        return $map;
    }

    /**
     * Label triwulan gaya formulir resmi: "I ( Satu )".
     */
    private function k2QuarterLabel(int $quarter): string
    {
        $romans = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];
        $words = [1 => 'Satu', 2 => 'Dua', 3 => 'Tiga', 4 => 'Empat'];

        return ($romans[$quarter] ?? '-').' ( '.($words[$quarter] ?? '-').' )';
    }

    /**
     * Rentang bulan triwulan gaya formulir resmi:
     * "01 Januari  s.d 31 Maret 2026".
     */
    private function k2MonthRange(int $quarter, int $year): string
    {
        $months = RkasReportService::MONTH_NAMES;
        $first = (($quarter - 1) * 3) + 1;
        $last = $quarter * 3;
        $lastDay = Carbon::create($year, $last, 1)?->endOfMonth()->day ?? 30;

        return sprintf('%02d %s  s.d %02d %s %d', $first, $months[$first - 1], $lastDay, $months[$last - 1], $year);
    }

    /**
     * Deteksi Aset Tetap Lainnya BOS untuk A-1. Aturan terverifikasi dari
     * contoh resmi TW I 2026: belanja buku perpustakaan (kode 05.02.*)
     * masuk kolom Aset Tetap Lainnya BOS.
     */
    /**
     * Kolom Belanja Modal A-1 berdasarkan kode rekening: 5.2.02.* =
     * Peralatan dan Mesin, 5.2 lainnya = Aset Tetap Lainnya BOS.
     */
    private function a1ModalColumn(string $accountCode): ?string
    {
        if (! str_starts_with($accountCode, '5.2')) {
            return null;
        }

        return str_starts_with($accountCode, '5.2.02') ? 'modal_mesin' : 'modal_aset';
    }

    private function a1IsAsetTetapLainnya(?string $activityCode, string $description): bool
    {
        $code = trim(trim((string) $activityCode), '.');
        if (str_starts_with($code, '05.02')) {
            return true;
        }

        return (bool) preg_match('/\bbuku\b/i', $description);
    }

    /**
     * Rentang periode gaya kop resmi: "01 JANUARI - 31 MARET 2024".
     *
     * @return array{range:string,place:string,signed_date:string}
     */
    private function bosA1Title(array $summary): array
    {
        $range = '-';
        $signedDate = '-';
        try {
            $from = filled($summary['date_from'] ?? null) ? Carbon::parse((string) $summary['date_from']) : null;
            $to = filled($summary['date_to'] ?? null) ? Carbon::parse((string) $summary['date_to']) : null;
            if ($from && $to) {
                $range = mb_strtoupper($from->translatedFormat('d F')).' - '.mb_strtoupper($to->translatedFormat('d F Y'));
                $signedDate = $to->translatedFormat('d F Y');
            }
        } catch (\Throwable) {
            $range = '-';
            $signedDate = '-';
        }

        $school = $this->context->school();
        $place = trim((string) ($school->desa ?? '')) ?: trim((string) ($school->district ?? '')) ?: '-';

        return ['range' => $range, 'place' => $place, 'signed_date' => $signedDate];
    }

    /**
     * Data REGISTER PENUTUPAN KAS Formulir BOS-K7B.
     *
     * D = total penerimaan ledger (termasuk saldo awal), K = total
     * pengeluaran, A = saldo buku, B = saldo kas fisik (bank + tunai dari
     * BKU; rincian pecahan uang kertas/logam diisi manual saat opname
     * karena tidak tersedia di mirror).
     *
     * @param  array<string,mixed>  $summary
     * @return array{closing_date:string,weekday_date:string,prev_closing:string,total_in:float,total_out:float,book:float,bank:float,cash:float,securities:float,diff:float,paper:list<array{label:string,denom:float}>,coins:list<array{label:string,denom:float}>}
     */
    private function k7bData(string $scope, ?int $period, array $summary): array
    {
        $rows = $this->bkuLedgerRows($scope, $period);
        $total = end($rows);
        $totalIn = (float) (is_array($total) ? ($total['incoming'] ?? 0) : 0);
        $totalOut = (float) (is_array($total) ? ($total['outgoing'] ?? 0) : 0);
        $bank = (float) $this->bkuClosing['bank'];
        $cash = (float) $this->bkuClosing['cash'];
        $book = (float) (is_array($total) ? ($total['balance'] ?? $bank + $cash) : $bank + $cash);

        $closingDate = '-';
        $weekdayDate = '-';
        $prevClosing = '-';
        try {
            if (filled($summary['date_to'] ?? null)) {
                $end = Carbon::parse((string) $summary['date_to']);
                $closingDate = $end->translatedFormat('d F Y');
                $weekdayDate = $end->translatedFormat('l, d F Y');
            }
            if (filled($summary['date_from'] ?? null)) {
                $prevClosing = Carbon::parse((string) $summary['date_from'])->subDay()->translatedFormat('d F Y');
            }
        } catch (\Throwable) {
            $closingDate = '-';
            $weekdayDate = '-';
        }

        return [
            'closing_date' => $closingDate,
            'weekday_date' => $weekdayDate,
            'prev_closing' => $prevClosing,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'book' => $book,
            'bank' => $bank,
            'cash' => $cash,
            'securities' => 0.0,
            'diff' => $book - ($bank + $cash),
            'paper' => [
                ['label' => 'Rp 100.000', 'denom' => 100000],
                ['label' => 'Rp 50.000', 'denom' => 50000],
                ['label' => 'Rp 20.000', 'denom' => 20000],
                ['label' => 'Rp 10.000', 'denom' => 10000],
                ['label' => 'Rp 5.000', 'denom' => 5000],
                ['label' => 'Rp 2.000', 'denom' => 2000],
                ['label' => 'Rp 1.000', 'denom' => 1000],
            ],
            'coins' => [
                ['label' => 'Rp 1.000', 'denom' => 1000],
                ['label' => 'Rp 500', 'denom' => 500],
                ['label' => 'Rp 200', 'denom' => 200],
                ['label' => 'Rp 100', 'denom' => 100],
            ],
        ];
    }

    /**
     * Data BERITA ACARA PEMERIKSAAN KAS Formulir BOS-K7C.
     *
     * Angka mengikuti K7B/BKU periode yang sama; nomor SK tidak tersedia
     * di skema sehingga memakai placeholder untuk diisi manual.
     *
     * @param  array<string,mixed>  $summary
     * @return array{weekday_date:string,closing_date:string,cash:float,bank:float,securities:float,total:float,book:float,diff:float}
     */
    private function k7cData(string $scope, ?int $period, array $summary): array
    {
        $k7b = $this->k7bData($scope, $period, $summary);

        return [
            'weekday_date' => $k7b['weekday_date'],
            'closing_date' => $k7b['closing_date'],
            'cash' => $k7b['cash'],
            'bank' => $k7b['bank'],
            'securities' => 0.0,
            'total' => $k7b['cash'] + $k7b['bank'],
            'book' => $k7b['book'],
            'diff' => $k7b['diff'],
        ];
    }

    /**
     * Data Formulir BOS-K7: Realisasi Penggunaan Dana Tiap Jenis Anggaran.
     *
     * Struktur mengikuti formulir resmi (terverifikasi terhadap contoh
     * TW II 2025): kop identitas sekolah + kotak "Formulir Bos-K7", tabel
     * 9 kolom (No. Kode | Uraian Kegiatan | Jumlah | Rutin | BOS
     * Pusat/Provinsi/Kab-Kota | Bantuan Lain | Sumber Pendapatan Lainnya)
     * dengan baris bernomor 1,2,3,4,5,8,9,10,11, hierarki A (penerimaan:
     * saldo awal, rutin, BOS, bantuan lain, sumber pendapatan lainnya,
     * total) -> B/I (Program Sekolah: 8 program -> sub-program -> kegiatan
     * dari kode kegiatan transaksi) -> II (total penggunaan) -> C
     * (sisa = I-II), lalu blok tanda tangan Komite/Kepala Sekolah/
     * Bendahara.
     *
     * Nominal penggunaan memakai gross_amount seperti A-1 agar konsisten
     * antar laporan. Transaksi tanpa kode kegiatan 01-08 dilaporkan
     * sebagai unmapped (tidak masuk JUMLAH).
     *
     * @param  array<string,mixed>  $summary
     * @return array{title:array{heading:string,year_line:string,range_line:string},fund_column:string,rows:list<array{kind:string,code:string,label:string,bold:bool,zero_dash:bool,amounts:array<string,float>}>,unmapped:float,unmappedCount:int,place:string,signed_date:string}
     */
    private function k7Data(string $scope, ?int $period, Collection $transactions, array $summary): array
    {
        $months = $this->scopeMonths($scope, $period);
        $fiscalYear = FiscalYear::query()->find($this->context->fiscalYearId());
        $year = (int) ($fiscalYear?->year ?: now()->year);
        $fundName = (string) ($fiscalYear?->fundSource?->name ?? $fiscalYear?->fund_source ?? '');
        $fundColumn = $this->k7FundColumn($fundName);

        $zero = fn (): array => [
            'jumlah' => 0.0, 'rutin' => 0.0, 'pusat' => 0.0, 'provinsi' => 0.0,
            'kab_kota' => 0.0, 'bantuan_lain' => 0.0, 'sumber_lainnya' => 0.0,
        ];

        // ---- A. Penerimaan dari mirror kas umum ----
        $receipts = $this->k7Receipts($months, $year);
        // Aturan domain: Pendapatan Rutin dikosongkan pada K7.
        $receipts['rutin'] = 0.0;
        // Aturan domain: penerimaan BOS triwulan = penerimaan Tahap / 2,
        // hanya untuk BOSP Reguler. Fund source lain (mis. BOP/BOSDA) tidak dibagi 2.
        // Tahap 1 = bulan 1-6 (TW I-II), Tahap 2 = bulan 7-12 (TW III-IV).
        if ($scope === 'triwulan' && $period !== null && $period >= 1 && $period <= 4
            && str_contains(mb_strtolower(trim($fundName)), 'bosp')) {
            $tahapMonths = $period <= 2 ? [1, 2, 3, 4, 5, 6] : [7, 8, 9, 10, 11, 12];
            $tahapReceipts = $this->k7Receipts($tahapMonths, $year);
            $receipts['bos'] = $tahapReceipts['bos'] / 2;
        }
        $in = $zero();
        $in['rutin'] += $receipts['rutin'];
        $in['bantuan_lain'] += $receipts['bantuan_lain'];
        $in['sumber_lainnya'] += $receipts['sumber_lainnya'];
        $in[$fundColumn] += $receipts['saldo_awal'] + $receipts['bos'];
        $in['jumlah'] = $in['rutin'] + $in['pusat'] + $in['provinsi'] + $in['kab_kota'] + $in['bantuan_lain'] + $in['sumber_lainnya'];

        $rows = [];
        $add = function (string $kind, string $code, string $label, array $amounts, bool $bold, bool $zeroDash = true) use (&$rows): void {
            $rows[] = [
                'kind' => $kind, 'code' => $code, 'label' => $label,
                'amounts' => $amounts, 'bold' => $bold, 'zero_dash' => $zeroDash,
            ];
        };

        $add('section', 'A', 'Penerimaan Terdiri Dari', $in, true);
        $detail = function (string $label, float $value, string $column) use ($add, $zero): void {
            $amounts = $zero();
            $amounts['jumlah'] = $value;
            $amounts[$column] = $value;
            $add('detail', '', $label, $amounts, false);
        };
        $detail('Saldo Awal', $receipts['saldo_awal'], $fundColumn);
        $detail('Pendapatan Rutin', $receipts['rutin'], 'rutin');
        $detail('Bantuan Operasional Sekolah', $receipts['bos'], $fundColumn);
        $detail('Bantuan Lain', $receipts['bantuan_lain'], 'bantuan_lain');
        $detail('Sumber Pendapatan Lainnya', $receipts['sumber_lainnya'], 'sumber_lainnya');
        $add('detail', '', 'Total Penerimaan', $in, false);

        // ---- B/I. Penggunaan dana per hierarki kegiatan ----
        $add('section', 'B', 'Penggunaan Dana :', $zero(), true, false);
        [$usage, $unmapped, $unmappedCount] = $this->k7UsageHierarchy($transactions);

        $out = $zero();
        $out[$fundColumn] = $usage['total'];
        $out['jumlah'] = $usage['total'];
        $add('section', 'I', 'Program Sekolah', $out, true);

        foreach (BospRekapStandardMapper::k7Programs() as $programNo => $programLabel) {
            $program = $usage['programs'][$programNo] ?? ['total' => 0.0, 'subs' => []];
            $programAmounts = $zero();
            $programAmounts[$fundColumn] = $program['total'];
            $programAmounts['jumlah'] = $program['total'];
            // Label resmi K7 untuk program 04 (contoh resmi TW I 2026).
            if ($programNo === 4) {
                $programLabel = 'Standar Tenaga Kependidikan';
            }
            $add('program', sprintf('%02d.', $programNo), $programLabel, $programAmounts, true);
            foreach ($program['subs'] as $subNo => $sub) {
                $subAmounts = $zero();
                $subAmounts[$fundColumn] = $sub['total'];
                $subAmounts['jumlah'] = $sub['total'];
                $add('sub', sprintf('%02d.%02d.', $programNo, $subNo), $sub['label'], $subAmounts, true);
                foreach ($sub['activities'] as $activityCode => $activity) {
                    $activityAmounts = $zero();
                    $activityAmounts[$fundColumn] = $activity['total'];
                    $activityAmounts['jumlah'] = $activity['total'];
                    $add('activity', $activityCode.'.', $activity['label'], $activityAmounts, false);
                }
            }
        }

        // ---- II. Total & C. Sisa ----
        $add('section', '', 'Total Penggunaan Dana', $out, true);
        $rest = $zero();
        foreach (['rutin', 'pusat', 'provinsi', 'kab_kota', 'bantuan_lain', 'sumber_lainnya'] as $column) {
            $rest[$column] = $in[$column] - $out[$column];
        }
        $rest['jumlah'] = $in['jumlah'] - $out['jumlah'];
        $add('section', 'C', 'Sisa Dana = I - II (Pengembalian)', $rest, true);

        $signed = $this->bosA1Title($summary);

        return [
            'title' => $this->k7Title($summary, $scope, $period, $year),
            'fund_column' => $fundColumn,
            'rows' => $rows,
            'unmapped' => $unmapped,
            'unmappedCount' => $unmappedCount,
            'place' => $signed['place'],
            'signed_date' => $signed['signed_date'],
        ];
    }

    /**
     * Judul kop resmi K7: "Realisasi Penggunaan Dana Tiap Jenis Anggaran /
     * Tahun Anggaran 2025 / Periode Tanggal 01 April S/D 30 Juni 2025
     * ( Triwulan II )".
     *
     * @param  array<string,mixed>  $summary
     * @return array{heading:string,year_line:string,range_line:string}
     */
    private function k7Title(array $summary, string $scope, ?int $period, int $year): array
    {
        $fromLabel = '-';
        $toLabel = '-';
        try {
            if (filled($summary['date_from'] ?? null)) {
                $fromLabel = Carbon::parse((string) $summary['date_from'])->translatedFormat('d F');
            }
            if (filled($summary['date_to'] ?? null)) {
                $toLabel = Carbon::parse((string) $summary['date_to'])->translatedFormat('d F Y');
            }
        } catch (\Throwable) {
            // Label tetap '-' bila tanggal periode tidak valid.
        }

        return [
            'heading' => 'Realisasi Penggunaan Dana Tiap Jenis Anggaran',
            'year_line' => 'Tahun Anggaran '.$year,
            'range_line' => 'Periode Tanggal '.$fromLabel.' S/D '.$toLabel.' ( '.$this->k7PeriodQualifier($scope, $period, $year).' )',
        ];
    }

    /** Kualifikasi periode gaya formulir: Triwulan II, Semester I, Bulan April, Tahun 2025. */
    private function k7PeriodQualifier(string $scope, ?int $period, int $year): string
    {
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        return match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => 'Triwulan '.($roman[$period ?? 0] ?? (string) $period),
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => 'Semester '.($roman[$period ?? 0] ?? (string) $period),
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => ($period >= 1 && $period <= 12)
                ? 'Bulan '.Carbon::create($year, $period, 1)->translatedFormat('F')
                : 'Bulanan',
            default => 'Tahun '.$year,
        };
    }

    /**
     * Kolom sumber dana K7 untuk nama fund source aktif. BOSP/BOS Reguler/
     * Kinerja (APBN) -> Pusat; BOSDA/BOP daerah mengikuti namanya;
     * default Pusat.
     */
    private function k7FundColumn(string $fundName): string
    {
        $name = mb_strtolower(trim($fundName));

        if (str_contains($name, 'provinsi')) {
            return 'provinsi';
        }
        if (str_contains($name, 'kab') || str_contains($name, 'kota') || str_contains($name, 'daerah')) {
            return 'kab_kota';
        }
        if (str_contains($name, 'bantuan lain')) {
            return 'bantuan_lain';
        }
        if (str_contains($name, 'rutin')) {
            return 'rutin';
        }
        if (str_contains($name, 'lainnya') || str_contains($name, 'pendapatan lain')) {
            return 'sumber_lainnya';
        }

        return 'pusat';
    }

    /**
     * Rincian penerimaan K7 dari mirror kas umum periode: saldo_awal
     * (bank+tunai bulan pertama), rutin (bunga bank), bos (Terima Dana BOS,
     * distinct bukti+tanggal+nominal seperti BPK), bantuan_lain (Terima
     * *Bantuan*), sumber_lainnya (penerimaan masuk lain). Transfer internal
     * (Setor/Tarik Tunai, Pergeseran Tunai) bukan penerimaan baru dan
     * dikecualikan.
     *
     * @return array{saldo_awal:float,rutin:float,bos:float,bantuan_lain:float,sumber_lainnya:float}
     */
    private function k7Receipts(array $months, int $year): array
    {
        $out = ['saldo_awal' => 0.0, 'rutin' => 0.0, 'bos' => 0.0, 'bantuan_lain' => 0.0, 'sumber_lainnya' => 0.0];
        if ($months === []) {
            return $out;
        }

        $firstMonth = min($months);
        $placeholders = implode(',', array_fill(0, count($months), '?'));
        $records = DB::connection('school')->table('arkas_mirror_kas_umum')
            ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
            ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
            ->get(['payload'])
            ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER))
            ->values();
        $fundSourceId = $this->context->fundSourceId();
        if ($fundSourceId !== null) {
            $records = $records->filter(
                fn (array $payload): bool => $this->kasMatchesFund($payload, $fundSourceId)
            )->values();
        }
        $records = $this->bkuDedupBackfillTax($records);

        $receivedKeys = [];
        foreach ($records as $payload) {
            $baseRek = $this->bkuBaseRek((string) ($payload['REK_BKU'] ?? ''));
            $amount = (float) ($payload['JUMLAH'] ?? 0);
            $month = (int) substr((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), 5, 2);
            if (! in_array($month, $months, true)) {
                continue;
            }
            if ($month === $firstMonth && in_array($baseRek, ['Saldo Awal Bank', 'Saldo Awal Tunai'], true)) {
                $out['saldo_awal'] += $amount;

                continue;
            }
            if ($baseRek === 'Terima Dana BOS') {
                $key = (string) ($payload['NO_BUKTI'] ?? '').'|'.(string) ($payload['TANGGAL_TRANSAKSI'] ?? '').'|'.$amount;
                if (! isset($receivedKeys[$key])) {
                    $receivedKeys[$key] = true;
                    $out['bos'] += $amount;
                }

                continue;
            }
            if ($baseRek === 'Bunga Bank') {
                $out['rutin'] += $amount;

                continue;
            }
            if (in_array($baseRek, ['Setor Tunai', 'Tarik Tunai', 'Pergeseran Tunai', 'Kas Keluar', 'Kas Keluar Non Tunai', 'Pajak Belanja Setor', 'Pajak Belanja Terima', 'Pajak Bunga'], true)) {
                continue;
            }
            if (str_starts_with($baseRek, 'Terima')) {
                if (str_contains(mb_strtolower($baseRek), 'bantuan')) {
                    $out['bantuan_lain'] += $amount;
                } else {
                    $out['sumber_lainnya'] += $amount;
                }

                continue;
            }
        }

        return $out;
    }

    /**
     * Hierarki penggunaan K7 dari transaksi periode: 8 program ->
     * sub-program (segmen 2 kode kegiatan) -> kegiatan (kode penuh).
     * Label program dari BospRekapStandardMapper::k7Programs(), label sub
     * dari k7SubProgramLabel(), label kegiatan dari activity_name mirror.
     *
     * @return array{0:array{total:float,programs:array<int,array{total:float,subs:array<int,array{total:float,label:string,activities:array<string,array{total:float,label:string}>}>}>},1:float,2:int}
     */
    private function k7UsageHierarchy(Collection $transactions): array
    {
        $programs = [];
        foreach (array_keys(BospRekapStandardMapper::k7Programs()) as $programNo) {
            $programs[$programNo] = ['total' => 0.0, 'subs' => []];
        }
        $total = 0.0;
        $unmapped = 0.0;
        $unmappedCount = 0;

        foreach ($transactions as $transaction) {
            $code = trim(trim((string) $transaction->sourceValue('activity_code')), '.');
            $gross = (float) $transaction->sourceValue('gross_amount');
            $program = BospRekapStandardMapper::a1RowForActivity($code);
            if ($program === null) {
                $unmapped += $gross;
                $unmappedCount++;

                continue;
            }
            $parts = array_values(array_filter(explode('.', $code), fn (string $part): bool => $part !== ''));
            $sub = isset($parts[1]) && ctype_digit($parts[1]) ? (int) $parts[1] : 0;

            $activityName = trim((string) $transaction->sourceValue('activity_name'));
            if ($activityName === '') {
                $activityName = $code;
            }

            $programs[$program]['total'] += $gross;
            $total += $gross;
            if (! isset($programs[$program]['subs'][$sub])) {
                $programs[$program]['subs'][$sub] = [
                    'total' => 0.0,
                    'label' => BospRekapStandardMapper::k7SubProgramLabel($sub),
                    'activities' => [],
                ];
            }
            $programs[$program]['subs'][$sub]['total'] += $gross;
            if (! isset($programs[$program]['subs'][$sub]['activities'][$code])) {
                $programs[$program]['subs'][$sub]['activities'][$code] = ['total' => 0.0, 'label' => $activityName];
            }
            $programs[$program]['subs'][$sub]['activities'][$code]['total'] += $gross;
        }

        foreach ($programs as &$program) {
            ksort($program['subs']);
            foreach ($program['subs'] as &$sub) {
                ksort($sub['activities']);
            }
            unset($sub);
        }
        unset($program);

        return [['total' => $total, 'programs' => $programs], $unmapped, $unmappedCount];
    }

    /**
     * Data SPTJM: identitas penanda tangan + nominal periode dari mirror
     * (penerimaan distinct seperti rekap; penggunaan = jumlah BELANJA).
     *
     * @param  array<string,mixed>  $summary
     * @return array{year:int,fund:string,period_label:string,received:float,used:float,range:string,place:string,signed_date:string}
     */
    private function sptjmData(string $scope, ?int $period, array $summary): array
    {
        $year = (int) (FiscalYear::query()->find($this->context->fiscalYearId())?->year ?: now()->year);
        $fundSource = (string) (FiscalYear::query()->find($this->context->fiscalYearId())?->fundSource?->name ?? '-');

        $months = $this->scopeMonths($scope, $period);
        $received = 0.0;
        $used = 0.0;

        if ($months !== []) {
            $placeholders = implode(',', array_fill(0, count($months), '?'));
            $records = DB::connection('school')->table('arkas_mirror_kas_umum')
                ->whereRaw("CAST(strftime('%Y', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) = ?", [$year])
                ->whereRaw("CAST(strftime('%m', json_extract(payload, '\$.TANGGAL_TRANSAKSI')) AS INTEGER) IN ({$placeholders})", $months)
                ->orderByRaw("json_extract(payload, '\$.TANGGAL_TRANSAKSI')")
                ->orderBy('id')
                ->get(['payload'])
                ->map(fn ($row): array => array_change_key_case(json_decode((string) $row->payload, true) ?? [], CASE_UPPER))
                ->values();

            $fundSourceId = $this->context->fundSourceId();
            if ($fundSourceId !== null) {
                $records = $records->filter(
                    fn (array $payload): bool => $this->kasMatchesFund($payload, $fundSourceId)
                )->values();
            }

            $receivedKeys = [];
            foreach ($records as $payload) {
                $amount = (float) ($payload['JUMLAH'] ?? 0);
                if (($payload['REK_BKU'] ?? '') === 'Terima Dana BOS') {
                    $key = (string) ($payload['NO_BUKTI'] ?? '').'|'.(string) ($payload['TANGGAL_TRANSAKSI'] ?? '').'|'.$amount;
                    if (! isset($receivedKeys[$key])) {
                        $receivedKeys[$key] = true;
                        $received += $amount;
                    }

                    continue;
                }
                if (strtoupper((string) ($payload['KATEGORI_BKU'] ?? '')) === 'BELANJA') {
                    $used += $amount;
                }
            }
        }

        $title = $this->bosA1Title($summary);
        $school = $this->context->school();
        $place = trim((string) ($school->desa ?? '')) ?: trim((string) ($school->district ?? '')) ?: '-';

        return [
            'year' => $year,
            'fund' => $fundSource,
            'period_label' => (string) ($summary['period_label'] ?? ''),
            'received' => $received,
            'used' => $used,
            'range' => $title['range'],
            'place' => $place,
            'signed_date' => $title['signed_date'],
        ];
    }

    /** @return list<array{key:string,label:string,type:string}> */
    private function recapColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'type' => 'integer'],
            ['key' => 'date', 'label' => 'Tanggal', 'type' => 'text'],
            ['key' => 'evidence', 'label' => 'No. Bukti', 'type' => 'text'],
            ['key' => 'activity', 'label' => 'Kegiatan', 'type' => 'text'],
            ['key' => 'account', 'label' => 'Rekening', 'type' => 'text'],
            ['key' => 'description', 'label' => 'Uraian', 'type' => 'text'],
            ['key' => 'gross', 'label' => 'Bruto', 'type' => 'money'],
            ['key' => 'tax', 'label' => 'Pajak', 'type' => 'money'],
            ['key' => 'net', 'label' => 'Netto', 'type' => 'money'],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function recapRows(Collection $transactions): array
    {
        return $transactions->values()->map(fn (Transaction $transaction, int $index): array => [
            'no' => $index + 1,
            'date' => $transaction->sourceDateString('transaction_date', 'd-m-Y') ?? '-',
            'evidence' => $transaction->sourceValue('no_bukti') ?: '-',
            'activity' => trim(($transaction->sourceValue('activity_code') ?: '').' '.($transaction->sourceValue('activity_name') ?: '')) ?: '-',
            'account' => trim(($transaction->sourceValue('account_code') ?: '').' '.($transaction->sourceValue('account_name') ?: '')) ?: '-',
            'description' => $transaction->sourceValue('description') ?: '-',
            'gross' => (float) $transaction->sourceValue('gross_amount'),
            'tax' => (float) $transaction->sourceValue('tax_total'),
            'net' => (float) $transaction->sourceValue('net_amount'),
        ])->all();
    }

    /** @return list<string> */
    private function statement(string $reportKey, string $periodLabel): array
    {
        return match ($reportKey) {
            'sptjm' => [
                "Dengan ini menyatakan bahwa penggunaan dana pada {$periodLabel} telah dicatat berdasarkan transaksi pada konteks sekolah, tahun anggaran, dan sumber dana aktif.",
                'Seluruh bukti pengeluaran, pemotongan pajak, dan dokumen pendukung menjadi bagian yang tidak terpisahkan dari pertanggungjawaban periode ini.',
            ],
            'k7b' => [
                "Register penutupan kas {$periodLabel} merangkum transaksi, nilai bruto, pajak, dan nilai yang dibayarkan pada periode laporan.",
                'Saldo fisik kas dan bank tetap harus dicocokkan dengan rekening koran/buku kas yang dikuasai bendahara pada tanggal penutupan.',
            ],
            'k7c' => [
                "Pada akhir {$periodLabel} dilakukan pemeriksaan atas pencatatan transaksi dan pertanggungjawaban kas berdasarkan data yang tersedia pada APP-SPJ.",
                'Hasil pemeriksaan ditandatangani setelah nilai pada laporan ini dicocokkan dengan bukti fisik dan saldo aktual.',
            ],
            'berita_acara_rekonsiliasi' => [
                "Berita acara rekonsiliasi {$periodLabel} dibuat berdasarkan pencocokan data transaksi, nilai bruto, pajak, dan realisasi netto pada konteks aktif.",
                'Lampiran rincian transaksi digunakan sebagai dasar penelusuran bila terdapat perbedaan dengan catatan eksternal.',
            ],
            default => [],
        };
    }

    private function orientation(string $presentation): string
    {
        return in_array($presentation, ['ledger', 'cash_ledger', 'bank_ledger', 'tax', 'transaction_recap', 'bku_ledger', 'bpk_bos', 'bos_a1', 'k2', 'rpd'], true)
            ? 'landscape'
            : 'portrait';
    }

    /**
     * BKU resmi memakai kertas F4/Folio; A-1 yang lebar ikut folio; laporan lain tetap A4.
     */
    private function paper(string $presentation): string
    {
        return in_array($presentation, ['bku_ledger', 'cash_ledger', 'bpk_bos', 'bos_a1', 'k2', 'rpd'], true) ? 'folio' : 'a4';
    }

    /**
     * Label judul dinas: TRIWULAN I–IV / SEMESTER I–II / BULAN / TAHUN.
     *
     * @param  array<string,mixed>  $summary
     * @return array{title:string}
     */
    private function bpkTitle(array $summary, int $year): array
    {
        $scope = (string) ($summary['scope'] ?? '');
        $period = (int) ($summary['period'] ?? 0);
        $roman = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

        if ($scope === SpjPeriodicReportRegistry::SCOPE_MONTHLY && $period >= 1 && $period <= 12) {
            $label = 'BULAN '.mb_strtoupper(Carbon::create($year, $period, 1)->translatedFormat('F')).' TAHUN '.$year;
        } elseif ($scope === SpjPeriodicReportRegistry::SCOPE_QUARTERLY && $period >= 1 && $period <= 4) {
            $label = 'TRIWULAN '.$roman[$period].' TAHUN '.$year;
        } elseif ($scope === SpjPeriodicReportRegistry::SCOPE_SEMESTER && $period >= 1 && $period <= 2) {
            $label = 'SEMESTER '.$roman[$period].' TAHUN '.$year;
        } else {
            $label = 'TAHUN '.$year;
        }

        return ['title' => 'REKAP REALISASI PENGGUNAAN DANA BOS '.$label];
    }

    /**
     * Nama sekolah versi kop Dinas: prefix UPTD untuk SD/SMP Negeri.
     */
    private function bpkSchoolName(?string $name): string
    {
        $upper = mb_strtoupper((string) $name);

        if (preg_match('/^(SD|SMP)\b/i', (string) $name) === 1 && ! str_starts_with($upper, 'UPTD')) {
            return 'UPTD '.$upper;
        }

        return $upper;
    }

    private function fileName(string $label, string $periodLabel): string
    {
        $value = strtoupper($label.'-'.$periodLabel);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?: 'LAPORAN-PERIODE';

        return trim($value, '-');
    }
}
