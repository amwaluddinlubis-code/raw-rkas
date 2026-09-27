<?php

namespace App\Console\Commands;

use App\Models\School;
use App\Services\BusinessPartnerNormalizer;
use App\Services\SchoolDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class FusePartnerDuplicates extends Command
{
    protected $signature = 'partners:fuse-duplicates
        {--school= : NPSN sekolah target; wajib diisi kecuali memakai --all}
        {--all : Proses semua sekolah yang file database tenant-nya ada}
        {--apply : Tulis perubahan (default hanya laporan dry-run)}';

    protected $description = 'Gabungkan baris rekanan ganda hasil sinkron ARKAS per identitas ternormalisasi (nama + NPWP).';

    public function handle(BusinessPartnerNormalizer $normalizer, SchoolDatabaseManager $databases): int
    {
        $apply = (bool) $this->option('apply');
        $schools = $this->resolveSchools();

        if ($schools === null) {
            return self::FAILURE;
        }

        if ($schools->isEmpty()) {
            $this->warn('Tidak ada database tenant yang ditemukan.');

            return self::SUCCESS;
        }

        $totalGroups = 0;
        $totalMerged = 0;

        foreach ($schools as $school) {
            try {
                $databases->activate($school);
            } catch (\Throwable $exception) {
                $this->warn("[{$school->npsn}] lewati: ".$exception->getMessage());

                continue;
            }

            if (! Schema::connection('school')->hasTable('business_partners')) {
                $this->warn("[{$school->npsn}] lewati: tabel business_partners belum ada.");

                continue;
            }

            $report = $normalizer->normalize(! $apply);
            $totalGroups += $report['groups'];
            $totalMerged += $report['merged'];

            $remaining = (int) DB::connection('school')->table('business_partners')->count();
            $this->line("[{$school->npsn}] {$school->name}: {$report['groups']} grup ganda, {$report['merged']} baris digabung, sisa {$remaining} baris.");
            foreach ($report['pairs'] as $pair) {
                $this->line('  simpan #'.$pair['keep'].' <- hapus #'.implode(', #', $pair['drop']).' | '.implode(' ; ', $pair['names']));
            }
        }

        $this->info($apply
            ? "Selesai: {$totalGroups} grup, {$totalMerged} baris digabung."
            : "Dry-run selesai: {$totalGroups} grup ganda ditemukan. Ulangi dengan --apply untuk menulis.");

        return self::SUCCESS;
    }

    private function resolveSchools(): ?Collection
    {
        if ((bool) $this->option('all')) {
            return School::query()->with('databaseRecord')->get()->filter(
                fn (School $school) => $school->databaseRecord && File::exists($school->databaseRecord->database_path)
            )->values();
        }

        $npsn = trim((string) ($this->option('school') ?? ''));
        if ($npsn === '') {
            $this->error('Isi --school=NPSN atau gunakan --all.');

            return null;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan pada database utama.');

            return null;
        }

        if (! $school->databaseRecord || ! File::exists($school->databaseRecord->database_path)) {
            $this->error('File database tenant untuk '.$school->name.' tidak ditemukan. Perintah ini tidak membuat database baru.');

            return null;
        }

        return collect([$school]);
    }
}
