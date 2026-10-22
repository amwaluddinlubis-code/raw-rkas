<?php

namespace App\Console\Commands;

use App\Models\FiscalYear;
use App\Models\School;
use App\Services\ArkasMirrorResolver;
use App\Services\RkasPlanningSuggestionService;
use App\Services\SchoolDatabaseManager;
use App\Support\SpjDisplay;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Diagnosis read-only untuk anomali halaman "Saran Perencanaan RKAS".
 *
 * Menjawab pertanyaan: dari baris mentah mana angka pagu sebuah kegiatan
 * berasal — mirror arkas_mirror_rapbs atau tabel normalisasi arkas_rkas_items —
 * beserta sebaran tahun, sumber dana, dan jumlah baris di baliknya.
 * Perintah ini hanya membaca database; tidak ada satu pun operasi tulis.
 */
class DiagnoseRkasSaranCommand extends Command
{
    protected $signature = 'spj:diagnose-rkas-saran
        {--school= : NPSN sekolah tenant (wajib)}
        {--year= : Tahun anggaran, mis. 2026 (default: tahun terbaru di DB tenant)}
        {--fund-source= : ID sumber dana (default: semua sumber dana yang punya baris RKAS)}';

    protected $description = 'Diagnosis read-only angka Saran Perencanaan RKAS (pagu per kegiatan).';

    public function handle(SchoolDatabaseManager $databases): int
    {
        $npsn = trim((string) ($this->option('school') ?? ''));
        if ($npsn === '') {
            $this->error('Isi --school=NPSN.');

            return self::FAILURE;
        }

        $school = School::query()->with('databaseRecord')->where('npsn', $npsn)->first();
        if (! $school) {
            $this->error('Sekolah dengan NPSN '.$npsn.' tidak ditemukan pada database utama.');

            return self::FAILURE;
        }
        if (! $school->databaseRecord || ! File::exists($school->databaseRecord->database_path)) {
            $this->error('File database tenant untuk '.$school->name.' tidak ditemukan. Perintah ini tidak membuat database baru.');

            return self::FAILURE;
        }

        $databases->activate($school);

        $yearOption = trim((string) ($this->option('year') ?? ''));
        $year = $yearOption === '' ? (int) FiscalYear::query()->max('year') : (int) $yearOption;
        if ($year < 2000 || $year > 2100) {
            $this->error('Tahun anggaran tidak valid.');

            return self::FAILURE;
        }

        $fundOption = trim((string) ($this->option('fund-source') ?? ''));
        $years = FiscalYear::query()->where('year', $year)->orderBy('fund_source_id')->get();
        if ($years->isEmpty()) {
            $this->error('Tidak ada tahun anggaran '.$year.' pada database tenant.');

            return self::FAILURE;
        }
        if ($fundOption !== '') {
            $years = $years->where('fund_source_id', (int) $fundOption)->values();
            if ($years->isEmpty()) {
                $this->error('Sumber dana '.$fundOption.' tidak punya tahun anggaran '.$year.'.');

                return self::FAILURE;
            }
        }

        $this->info('Diagnosis Saran RKAS — '.$school->name.' ('.$npsn.'), tahun '.$year);
        $this->newLine();

        foreach ($years as $fiscalYear) {
            $this->diagnoseYearFund((int) $fiscalYear->id, (int) $fiscalYear->fund_source_id, $year);
            $this->newLine();
        }

        return self::SUCCESS;
    }

    private function diagnoseYearFund(int $yearId, int $fundSourceId, int $year): void
    {
        $this->line('<fg=cyan;options=bold>— Tahun ID '.$yearId.' · sumber dana '.$fundSourceId.' —</>');

        $mirrorAvailable = Schema::connection('school')->hasTable('arkas_mirror_rapbs')
            && DB::connection('school')->table('arkas_mirror_rapbs')->exists();
        $this->line('Jalur halaman: '.($mirrorAvailable ? 'MIRROR (arkas_mirror_rapbs)' : 'LEGASI (arkas_rkas_items)'));

        $mirror = $mirrorAvailable ? $this->mirrorBreakdown($fundSourceId, $year) : null;
        $legacy = $this->legacyBreakdown($yearId, $fundSourceId);

        $this->line('Baris mirror relevan: '.($mirror['rows'] ?? 0).' (source_key unik: '.($mirror['distinct_keys'] ?? 0).')');
        $this->line('Baris arkas_rkas_items: '.$legacy['rows'].' · total pagu normalisasi: '.SpjDisplay::rupiah($legacy['total']));

        if ($mirror !== null) {
            if ($mirror['rows'] !== $mirror['distinct_keys']) {
                $this->warn('  ! source_key duplikat di mirror: '.($mirror['rows'] - $mirror['distinct_keys']).' baris rangkap.');
            }
            if ($mirror['year_null'] > 0) {
                $this->line('  · '.$mirror['year_null'].' baris mirror tanpa tahun (dianggap tahun berjalan oleh halaman).');
            }
            $this->line('  · Sebaran tahun pada baris mirror: '.$this->formatDistribution($mirror['years']));
            $this->line('  · Sebaran sumber dana pada baris mirror: '.$this->formatDistribution($mirror['funds']));
            $this->table(
                ['Kode', 'Kegiatan', 'Baris', 'Pagu (Σ)', 'Baris terbesar'],
                $mirror['top']->map(fn (array $row): array => [
                    $row['code'], mb_strimwidth($row['name'], 0, 34, '…'), $row['count'],
                    SpjDisplay::rupiah($row['total']),
                    SpjDisplay::rupiah($row['max_amount']).' ('.$row['max_key'].')',
                ])->all(),
            );
        }

        /** @var RkasPlanningSuggestionService $service */
        $service = app(RkasPlanningSuggestionService::class);
        $report = $service->build($yearId, $fundSourceId);
        $remaining = $report['remaining'];
        $totals = $remaining['totals'];
        $this->line('Halaman Modul 2 — total pagu: '.SpjDisplay::rupiah($totals['budget'])
            .' · realisasi: '.SpjDisplay::rupiah($totals['spent'])
            .' · sisa: '.SpjDisplay::rupiah($totals['remaining']));
        $this->table(
            ['Kode', 'Kegiatan', 'Pagu', 'Realisasi', 'Sisa'],
            collect($remaining['rows'])->sortByDesc('budget')->take(5)->map(fn (array $row): array => [
                $row['activity_code'], mb_strimwidth($row['activity_name'], 0, 34, '…'),
                SpjDisplay::rupiah($row['budget']), SpjDisplay::rupiah($row['spent']), SpjDisplay::rupiah($row['remaining']),
            ])->all(),
        );

        if ($mirror !== null && abs($mirror['total'] - $legacy['total']) > 0.5) {
            $this->warn('  ! Total mirror ('.SpjDisplay::rupiah($mirror['total']).') ≠ total normalisasi ('
                .SpjDisplay::rupiah($legacy['total']).') — halaman memakai angka mirror.');
        }
        $top = collect($remaining['rows'])->sortByDesc('budget')->first();
        if ($top && $totals['budget'] > 0 && $top['budget'] > $totals['budget'] * 0.5) {
            $this->warn('  ! Satu kegiatan ('.$top['activity_name'].') memegang >50% total pagu halaman: '
                .SpjDisplay::rupiah($top['budget']).'. Lihat rincian baris mentahnya di tabel mirror di atas.');
        }
    }

    /**
     * @return array{rows:int, distinct_keys:int, year_null:int, years:array<string,int>, funds:array<string,int>, total:float, top:Collection<int,array{code:string,name:string,count:int,total:float,max_amount:float,max_key:string}>}
     */
    private function mirrorBreakdown(int $fundSourceId, int $year): array
    {
        $rows = DB::connection('school')->table('arkas_mirror_rapbs')->get(['source_key', 'payload']);
        $keys = [];
        $yearCounts = [];
        $fundCounts = [];
        $yearNull = 0;
        /** @var array<string, array{code:string,name:string,count:int,total:float,max_amount:float,max_key:string,rows:array<int,array{key:string,amount:float,year:?int}>}> $groups */
        $groups = [];

        foreach ($rows as $row) {
            $payload = json_decode((string) $row->payload, true);
            if (! is_array($payload)) {
                continue;
            }
            if ((int) (ArkasMirrorResolver::field($payload, ['SOFT_DELETE', 'IS_DELETED']) ?? 0) === 1) {
                continue;
            }
            $fund = ArkasMirrorResolver::field($payload, ['ID_REF_SUMBER_DANA', 'SUMBER_DANA_ID']);
            $fundKey = $fund === null || $fund === '' ? '(kosong)' : (string) $fund;
            $fundCounts[$fundKey] = ($fundCounts[$fundKey] ?? 0) + 1;
            if ($fund !== null && $fund !== '' && (int) $fund !== 0 && (int) $fund !== $fundSourceId) {
                continue;
            }
            $rawYear = ArkasMirrorResolver::field($payload, ['TAHUN', 'TAHUN_ANGGARAN', 'tahun']);
            $rowYear = is_numeric($rawYear) ? (int) $rawYear : null;
            $yearKey = $rowYear === null ? '(null)' : (string) $rowYear;
            $yearCounts[$yearKey] = ($yearCounts[$yearKey] ?? 0) + 1;
            if ($rowYear === null) {
                $yearNull++;
            } elseif ($rowYear !== $year) {
                continue;
            }

            $code = trim((string) (ArkasMirrorResolver::field($payload, ['KODE_KEGIATAN', 'ID_KODE']) ?? ''), '.');
            $name = (string) (ArkasMirrorResolver::field($payload, ['NAMA_KEGIATAN', 'URAIAN_KODE', 'URAIAN']) ?? 'Kegiatan belum diisi');
            $amount = (float) (ArkasMirrorResolver::field($payload, ['JUMLAH', 'amount']) ?? 0);
            $groupKey = $code.'|'.$name;
            $keys[(string) $row->source_key] = true;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = ['code' => $code !== '' ? $code : '-', 'name' => $name, 'count' => 0, 'total' => 0.0, 'max_amount' => 0.0, 'max_key' => '', 'rows' => []];
            }
            $groups[$groupKey]['count']++;
            $groups[$groupKey]['total'] += $amount;
            $groups[$groupKey]['rows'][] = ['key' => (string) $row->source_key, 'amount' => $amount, 'year' => $rowYear];
            if ($amount > $groups[$groupKey]['max_amount']) {
                $groups[$groupKey]['max_amount'] = $amount;
                $groups[$groupKey]['max_key'] = (string) $row->source_key;
            }
        }

        $top = collect($groups)->sortByDesc('total')->take(8)->values()->map(
            fn (array $group): array => [
                'code' => $group['code'], 'name' => $group['name'], 'count' => $group['count'],
                'total' => $group['total'], 'max_amount' => $group['max_amount'], 'max_key' => $group['max_key'],
            ]
        );

        return [
            'rows' => array_sum(array_column($groups, 'count')),
            'distinct_keys' => count($keys),
            'year_null' => $yearNull,
            'years' => $yearCounts,
            'funds' => $fundCounts,
            'total' => collect($groups)->sum('total'),
            'top' => $top,
        ];
    }

    /** @return array{rows:int, total:float} */
    private function legacyBreakdown(int $yearId, int $fundSourceId): array
    {
        $db = DB::connection('school');

        return [
            'rows' => (int) $db->table('arkas_rkas_items')
                ->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->count(),
            'total' => (float) $db->table('arkas_rkas_items')
                ->where('fiscal_year_id', $yearId)->where('fund_source_id', $fundSourceId)->sum('amount'),
        ];
    }

    /** @param array<string,int> $distribution */
    private function formatDistribution(array $distribution): string
    {
        if ($distribution === []) {
            return '(kosong)';
        }
        $parts = [];
        foreach ($distribution as $key => $count) {
            $parts[] = $key.'×'.$count;
        }

        return implode(', ', $parts);
    }
}
