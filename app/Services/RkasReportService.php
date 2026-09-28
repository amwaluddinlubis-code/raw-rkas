<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\School;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builder laporan Kertas Kerja RKAS (Tahunan, Tahap, Triwulan, Bulanan)
 * per pengesahan revisi, dari snapshot mirror ARKAS.
 *
 * Data per revisi diambil lewat ArkasMirrorBudgetService::snapshot() dengan
 * ID anggaran eksplisit sehingga pengesahan terdahulu dapat digenerate
 * ulang (ARKAS hanya mencetak pengesahan terakhir). Alokasi Tahap mengikuti
 * konvensi ARKAS: Tahap 1 = TW 1+2, Tahap 2 = TW 3+4.
 */
final class RkasReportService
{
    public const SCOPES = ['tahunan', 'tahap', 'triwulan', 'bulanan'];

    /** @var array<int,array{code:string,label:string}> */
    public const PENERIMAAN_ROWS = [
        ['code' => '4.3.1.00.', 'label' => 'SiLPA BOSP Reguler'],
        ['code' => '4.3.1.01.', 'label' => 'BOSP Reguler'],
        ['code' => '4.3.1.03.', 'label' => 'BOSP Daerah'],
        ['code' => '4.3.1.12.', 'label' => 'BOSP Kinerja'],
        ['code' => '4.3.1.34.', 'label' => 'SiLPA BOSP Afirmasi'],
        ['code' => '4.3.1.35.', 'label' => 'SiLPA BOSP Kinerja'],
        ['code' => '4.3.1.99.', 'label' => 'Lainnya'],
    ];

    /** @var array<int,string> */
    public const FUND_COLUMNS = ['BOSP REGULER', 'BOSP DAERAH', 'AFIRMASI', 'KINERJA', 'SiLPA', 'BOSP LAINNYA'];

    /** @var array<int,string> */
    public const MONTH_NAMES = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

    public function __construct(private readonly ArkasMirrorBudgetService $budgets) {}

    /**
     * @param  array{school_id:int,fiscal_year_id:int,fund_source_id:int,revision?:string|null,month?:int|null}  $options
     * @return array<string,mixed>|null null bila data mirror belum siap
     */
    public function build(string $scope, array $options): ?array
    {
        if (! in_array($scope, self::SCOPES, true)) {
            return null;
        }

        $schoolId = (int) ($options['school_id'] ?? 0);
        $yearId = (int) ($options['fiscal_year_id'] ?? 0);
        $fundSourceId = (int) ($options['fund_source_id'] ?? 0);
        if ($schoolId <= 0 || $yearId <= 0 || $fundSourceId <= 0) {
            return null;
        }

        $db = DB::connection('school');
        $yearNumber = (int) (FiscalYear::query()->whereKey($yearId)->value('year') ?: 0);
        if ($yearNumber <= 0 || ! $this->hasUsableMirror($db, $yearId)) {
            return null;
        }

        $school = School::query()->find($schoolId);
        if ($school === null) {
            return null;
        }

        $fund = (array) ($db->table('fund_sources')->where('id', $fundSourceId)->first() ?? ['name' => 'BOSP Reguler']);
        $fundName = (string) ($fund['name'] ?? 'BOSP Reguler');

        $month = (int) ($options['month'] ?? 0);
        if ($scope === 'bulanan' && ($month < 1 || $month > 12)) {
            return null;
        }

        $revisions = $this->budgets->revisions($fundSourceId, $yearNumber);
        $allowedIds = collect($revisions)->pluck('id')->all();
        $latestApprovedId = collect($revisions)->where('status', 'approved')->last()['id'] ?? null;
        $requested = trim((string) ($options['revision'] ?? ''));
        if ($requested === '' || $requested === 'persetujuan') {
            $selectedId = $latestApprovedId;
        } elseif ($requested === 'pengajuan') {
            $pending = collect($revisions)->firstWhere('status', 'pending');
            $selectedId = $pending['id'] ?? $latestApprovedId;
        } else {
            $selectedId = in_array($requested, $allowedIds, true) ? $requested : $latestApprovedId;
        }
        $selectedTab = collect($revisions)->firstWhere('id', $selectedId);

        $snapshot = $this->budgets->snapshot($fundSourceId, $yearNumber, $selectedId === null ? null : [$selectedId]);
        /** @var Collection<int,array> $rows */
        $rows = $snapshot['rows'] ?? collect();
        if ($rows->isEmpty()) {
            return null;
        }

        $lines = $this->buildLines($rows, $scope, $month);
        if ($lines === []) {
            return null;
        }

        $totals = $this->sumLines($lines);
        $profile = (array) ($db->table('school_profiles')->where('fiscal_year_id', $yearId)->first() ?? []);

        return [
            'scope' => $scope,
            'scope_label' => $this->scopeLabel($scope, $month),
            'title' => $this->title($scope),
            'footer_tag' => $this->footerTag($scope),
            'school' => [
                'npsn' => (string) ($school->npsn ?? ''),
                'name' => (string) ($school->name ?? ''),
                'address' => trim((string) ($school->address ?? '')) !== '' ? (string) $school->address : '-',
                'district' => trim((string) ($school->district ?? '')) !== '' ? (string) $school->district : '-',
                'regency' => trim((string) ($school->regency ?? '')) !== '' ? (string) $school->regency : '-',
                'province' => trim((string) ($school->province ?? '')) !== '' ? (string) $school->province : '-',
            ],
            'signatories' => [
                'principal_name' => (string) ($profile['principal_name'] ?? ''),
                'principal_nip' => (string) ($profile['principal_nip'] ?? ''),
                'treasurer_name' => (string) ($profile['treasurer_name'] ?? ''),
                'treasurer_nip' => (string) ($profile['treasurer_nip'] ?? ''),
            ],
            'year' => $yearNumber,
            'fund_name' => $fundName,
            'fund_column' => $this->fundColumnIndex($fundName),
            'penerimaan' => $this->penerimaanRows($fundName, (float) $totals['jumlah']),
            'revision_label' => $this->revisionLabel($selectedTab),
            'revision_date' => (string) ($selectedTab['dateLabel'] ?? '-'),
            'place_date' => $this->placeDate($school),
            'lines' => $lines,
            'totals' => $totals,
            'file_name' => $this->fileName($scope, $yearNumber, $fundName, $selectedTab, $month),
        ];
    }

    private function hasUsableMirror(object $db, int $yearId): bool
    {
        try {
            return $db->table('arkas_mirror_rapbs')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function title(string $scope): string
    {
        return match ($scope) {
            'tahunan' => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS)',
            'tahap' => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TAHAP',
            'triwulan' => 'KERTAS KERJA RENCANA KEGIATAN DAN ANGGARAN SEKOLAH (RKAS) PER TRIWULAN',
            default => 'RINCIAN KERTAS KERJA PERBULAN',
        };
    }

    private function footerTag(string $scope): string
    {
        return match ($scope) {
            'tahunan' => 'Kertas Kerja',
            'tahap' => 'Kertas Kerja perTahap',
            'triwulan' => 'Kertas Kerja perTriwulan',
            default => 'Kertas Kerja perBulan',
        };
    }

    private function scopeLabel(string $scope, int $month): string
    {
        return match ($scope) {
            'tahap' => 'I dan II',
            'triwulan' => 'I,II,III dan IV',
            'bulanan' => self::MONTH_NAMES[$month - 1].' ',
            default => '',
        };
    }

    /** @param array{id:string,status:string,seq:int,dateLabel:string,revisionNo:int,amount:float}|null $tab */
    private function revisionLabel(?array $tab): string
    {
        if ($tab === null) {
            return 'Pengesahan terakhir';
        }
        if (($tab['status'] ?? '') === 'pending') {
            return 'Pengajuan (menunggu persetujuan)';
        }

        return 'Pengesahan ke-'.($tab['seq'] ?? $tab['revisionNo'] ?? 1);
    }

    /** @param array{id:string,status:string,seq:int,dateLabel:string,revisionNo:int,amount:float}|null $tab */
    private function fileName(string $scope, int $year, string $fund, ?array $tab, int $month): string
    {
        $slug = preg_replace('/[^A-Za-z0-9]+/', '-', strtoupper($fund));
        $rev = $tab === null ? 'TERAKHIR' : (($tab['status'] ?? '') === 'pending' ? 'PENGAJUAN' : 'PENGESAHAN-'.($tab['seq'] ?? 1));
        $suffix = $scope === 'bulanan' ? '-'.strtoupper(self::MONTH_NAMES[$month - 1]) : '';

        return trim(sprintf('RKAS-%s-%d-%s-%s%s', strtoupper($scope), $year, trim((string) $slug, '-'), $rev, $suffix), '-');
    }

    private function placeDate(School $school): string
    {
        $place = trim((string) ($school->district ?? ''));
        $date = now()->translatedFormat('d F Y');

        return ($place !== '' ? $place.', ' : '').$date;
    }

    private function fundColumnIndex(string $fundName): int
    {
        $name = mb_strtoupper($fundName);
        if (str_contains($name, 'DAERAH')) {
            return 1;
        }
        if (str_contains($name, 'AFIRMASI')) {
            return 2;
        }
        if (str_contains($name, 'KINERJA')) {
            return 3;
        }
        if (str_contains($name, 'SILPA') || str_contains($name, 'SISA')) {
            return 4;
        }
        if (str_contains($name, 'REGULER') || str_contains($name, 'BOSP')) {
            return 0;
        }

        return 5;
    }

    /** @return array<int,array{code:string,label:string,amount:float,active:bool}> */
    private function penerimaanRows(string $fundName, float $total): array
    {
        $activeCode = $this->penerimaanCode($fundName);

        return collect(self::PENERIMAAN_ROWS)->map(fn (array $row): array => [
            'code' => $row['code'],
            'label' => $row['label'],
            'amount' => $row['code'] === $activeCode ? $total : 0.0,
            'active' => $row['code'] === $activeCode,
        ])->all();
    }

    private function penerimaanCode(string $fundName): string
    {
        $name = mb_strtoupper($fundName);
        $isSilpa = str_contains($name, 'SILPA') || str_contains($name, 'SISA');
        if (str_contains($name, 'DAERAH')) {
            return '4.3.1.03.';
        }
        if (str_contains($name, 'KINERJA')) {
            return $isSilpa ? '4.3.1.35.' : '4.3.1.12.';
        }
        if (str_contains($name, 'AFIRMASI')) {
            return '4.3.1.34.';
        }
        if (str_contains($name, 'REGULER') || str_contains($name, 'BOSP')) {
            return $isSilpa ? '4.3.1.00.' : '4.3.1.01.';
        }

        return '4.3.1.99.';
    }

    /**
     * @param  Collection<int,array>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function buildLines(Collection $rows, string $scope, int $month): array
    {
        $tree = [];
        foreach ($rows as $row) {
            $activityCode = trim((string) ($row['activity_code'] ?? ''), '.');
            if ($activityCode === '') {
                continue;
            }
            $parts = explode('.', $activityCode);
            $programCode = $parts[0];
            $subCode = count($parts) >= 2 ? implode('.', array_slice($parts, 0, 2)) : $programCode;
            $amounts = $this->rowAmounts($row);
            $scoped = $scope === 'bulanan' ? ($amounts['months'][$month] ?? 0.0) : (float) $row['amount'];
            if ((float) $row['amount'] <= 0 || ($scope === 'bulanan' && $scoped <= 0)) {
                continue;
            }
            $isModal = str_starts_with(trim((string) ($row['account_code'] ?? '')), '5.2');
            $item = [
                'program_code' => $programCode,
                'program_name' => (string) ($row['program_name'] ?? 'Program'),
                'sub_code' => $subCode,
                'sub_name' => (string) ($row['subprogram_name'] ?? 'Subprogram'),
                'activity_code' => $activityCode,
                'activity_name' => (string) ($row['activity_name'] ?? 'Kegiatan belum diisi'),
                'account_code' => trim((string) ($row['account_code'] ?? '')),
                'account_name' => (string) ($row['account_name'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'volume' => (float) (ArkasMirrorResolver::field($row['payload'] ?? [], ['VOLUME_TOTAL', 'VOLUME']) ?? 0),
                'unit' => (string) (ArkasMirrorResolver::field($row['payload'] ?? [], ['SATUAN', 'UNIT']) ?? ''),
                'unit_price' => (float) (ArkasMirrorResolver::field($row['payload'] ?? [], ['HARGA_SATUAN']) ?? 0),
                'jumlah' => (float) $row['amount'],
                'operasi' => $isModal ? 0.0 : (float) $row['amount'],
                'modal' => $isModal ? (float) $row['amount'] : 0.0,
                'tw' => $amounts['quarters'],
                'tahap' => [$amounts['quarters'][1] + $amounts['quarters'][2], $amounts['quarters'][3] + $amounts['quarters'][4]],
                'scoped' => $scope === 'bulanan' ? $scoped : (float) $row['amount'],
            ];
            $tree[$programCode] ??= ['code' => $programCode, 'name' => $item['program_name'], 'subs' => []];
            $tree[$programCode]['subs'][$subCode] ??= ['code' => $subCode, 'name' => $item['sub_name'], 'activities' => []];
            $tree[$programCode]['subs'][$subCode]['activities'][$activityCode] ??= ['code' => $activityCode, 'name' => $item['activity_name'], 'accounts' => []];
            $accountKey = $item['account_code'].'|'.$item['account_name'];
            $tree[$programCode]['subs'][$subCode]['activities'][$activityCode]['accounts'][$accountKey] ??= [
                'account_code' => $item['account_code'], 'account_name' => $item['account_name'], 'items' => [],
            ];
            $tree[$programCode]['subs'][$subCode]['activities'][$activityCode]['accounts'][$accountKey]['items'][] = $item;
        }

        uksort($tree, fn (string $a, string $b): int => $this->compareCodes($a, $b));
        $lines = [];
        foreach ($tree as $program) {
            uksort($program['subs'], fn (string $a, string $b): int => $this->compareCodes($a, $b));
            $programLines = [];
            foreach ($program['subs'] as $sub) {
                uksort($sub['activities'], fn (string $a, string $b): int => $this->compareCodes($a, $b));
                $subLines = [];
                foreach ($sub['activities'] as $activity) {
                    uksort($activity['accounts'], 'strcmp');
                    $activityLines = [];
                    foreach ($activity['accounts'] as $account) {
                        usort($account['items'], fn (array $a, array $b): int => $this->compareCodes($a['account_code'], $b['account_code']) ?: strnatcasecmp($a['description'], $b['description']));
                        $accountLines = array_map(fn (array $item): array => $this->line('item', $item), $account['items']);
                        if ($accountLines === [] && $scope === 'bulanan') {
                            continue;
                        }
                        $activityLines[] = $this->aggregateLine('account', [
                            'account_code' => $account['account_code'], 'name' => $account['account_name'],
                            'activity_code' => $activity['code'],
                        ], $accountLines);
                        array_push($activityLines, ...$accountLines);
                    }
                    if ($activityLines === [] && $scope === 'bulanan') {
                        continue;
                    }
                    $subLines[] = $this->aggregateLine('activity', ['activity_code' => $activity['code'], 'name' => $activity['name']], $activityLines);
                    array_push($subLines, ...$activityLines);
                }
                if ($subLines === [] && $scope === 'bulanan') {
                    continue;
                }
                $programLines[] = $this->aggregateLine('subprogram', ['activity_code' => $sub['code'], 'name' => $sub['name']], $subLines);
                array_push($programLines, ...$subLines);
            }
            if ($programLines === [] && $scope === 'bulanan') {
                continue;
            }
            $lines[] = $this->aggregateLine('program', ['activity_code' => $program['code'], 'name' => $program['name']], $programLines);
            array_push($lines, ...$programLines);
        }

        $number = 0;
        foreach ($lines as &$line) {
            $line['no'] = ++$number;
        }
        unset($line);

        return $lines;
    }

    /** @return array{quarters:array<int,float>,months:array<int,float>} */
    private function rowAmounts(array $row): array
    {
        $quarters = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        $months = [];
        foreach ($row['periods'] ?? [] as $period) {
            $amount = (float) (ArkasMirrorResolver::field($period, ['JUMLAH']) ?? 0);
            $month = (int) ($period['__MONTH_NUMBER'] ?? 0);
            $quarter = (int) ($period['__QUARTER_NUMBER'] ?? 0);
            if ($month >= 1 && $month <= 12) {
                $months[$month] = ($months[$month] ?? 0.0) + $amount;
            }
            if ($quarter >= 1 && $quarter <= 4) {
                $quarters[$quarter] += $amount;
            } elseif ($month >= 1 && $month <= 12) {
                $quarters[(int) ceil($month / 3)] += $amount;
            }
        }

        return ['quarters' => $quarters, 'months' => $months];
    }

    /** @param array<string,mixed> $item */
    private function line(string $level, array $item): array
    {
        return [
            'level' => $level,
            'account_code' => $item['account_code'] ?? '',
            'activity_code' => $item['activity_code'] ?? '',
            'name' => $item['description'] ?? $item['name'] ?? '',
            'volume' => (float) ($item['volume'] ?? 0),
            'unit' => (string) ($item['unit'] ?? ''),
            'unit_price' => (float) ($item['unit_price'] ?? 0),
            'jumlah' => (float) ($item['jumlah'] ?? 0),
            'operasi' => (float) ($item['operasi'] ?? 0),
            'modal' => (float) ($item['modal'] ?? 0),
            'tw' => $item['tw'] ?? [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0],
            'tahap' => $item['tahap'] ?? [0.0, 0.0],
            'scoped' => (float) ($item['scoped'] ?? $item['jumlah'] ?? 0),
            'no' => 0,
        ];
    }

    /**
     * @param  array<int,array<string,mixed>>  $children
     * @param  array{activity_code:string,name:string,account_code?:string}  $meta
     */
    private function aggregateLine(string $level, array $meta, array $children): array
    {
        $sum = fn (string $key): float => array_sum(array_map(fn (array $line): float => (float) ($line[$key] ?? 0), $children));
        $tw = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        foreach ($children as $child) {
            foreach ([1, 2, 3, 4] as $q) {
                $tw[$q] += (float) ($child['tw'][$q] ?? 0);
            }
        }

        return $this->line($level, [
            'account_code' => $meta['account_code'] ?? '',
            'activity_code' => $meta['activity_code'],
            'name' => $meta['name'],
            'jumlah' => $sum('jumlah'),
            'operasi' => $sum('operasi'),
            'modal' => $sum('modal'),
            'tw' => $tw,
            'tahap' => [$tw[1] + $tw[2], $tw[3] + $tw[4]],
            'scoped' => $sum('scoped'),
        ]);
    }

    /** @param array<int,array<string,mixed>> $lines */
    private function sumLines(array $lines): array
    {
        $sum = fn (string $key): float => array_sum(array_map(
            fn (array $line): float => $line['level'] === 'item' ? (float) ($line[$key] ?? 0) : 0.0,
            $lines
        ));
        $tw = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0];
        foreach ($lines as $line) {
            if ($line['level'] !== 'item') {
                continue;
            }
            foreach ([1, 2, 3, 4] as $q) {
                $tw[$q] += (float) ($line['tw'][$q] ?? 0);
            }
        }

        return [
            'jumlah' => $sum('jumlah'),
            'operasi' => $sum('operasi'),
            'modal' => $sum('modal'),
            'tw' => $tw,
            'tahap' => [$tw[1] + $tw[2], $tw[3] + $tw[4]],
            'scoped' => $sum('scoped'),
        ];
    }

    private function compareCodes(string $left, string $right): int
    {
        $leftParts = array_values(array_filter(explode('.', trim($left, '.')), fn (string $part): bool => $part !== ''));
        $rightParts = array_values(array_filter(explode('.', trim($right, '.')), fn (string $part): bool => $part !== ''));
        foreach (range(0, max(count($leftParts), count($rightParts)) - 1) as $index) {
            $leftPart = $leftParts[$index] ?? '';
            $rightPart = $rightParts[$index] ?? '';
            if ($leftPart === $rightPart) {
                continue;
            }
            if ($leftPart === '') {
                return -1;
            }
            if ($rightPart === '') {
                return 1;
            }
            if (ctype_digit($leftPart) && ctype_digit($rightPart)) {
                return (int) $leftPart <=> (int) $rightPart;
            }

            return strnatcasecmp($leftPart, $rightPart);
        }

        return 0;
    }
}
