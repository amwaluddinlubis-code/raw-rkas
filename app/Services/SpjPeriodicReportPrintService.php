<?php

namespace App\Services;

use App\Models\FiscalYear;
use App\Models\Transaction;
use App\Support\ActiveSpjContext;
use App\UseCases\Spj\SpjPeriodicReportUseCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class SpjPeriodicReportPrintService
{
    public function __construct(
        private readonly ActiveSpjContext $context,
        private readonly SpjPeriodicReportUseCase $reports,
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
        if ($presentation === 'bku_ledger' || $presentation === 'tax') {
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
            'bos_k7a', 'bos_k8' => 'activity_summary',
            'format_k7', 'rekap_belanja_modal_barang_jasa', 'rekap_bmd',
            'rekap_belanja_dana_bos', 'form_1c', 'rekapitulasi_pengeluaran_dana_bos' => 'account_summary',
            'lampiran_sp2b', 'lampiran_berita_acara_rekonsiliasi' => 'transaction_recap',
            default => 'statement',
        };
    }

    /** @return array{0:list<array{key:string,label:string,type:string}>,1:list<array<string,mixed>>} */
    private function table(string $presentation, Collection $transactions, string $scope = '', ?int $period = null): array
    {
        return match ($presentation) {
            'bku_ledger' => [$this->bkuLedgerColumns(), $this->bkuLedgerRows($scope, $period)],
            'ledger' => [$this->ledgerColumns(), $this->ledgerRows($transactions)],
            'cash_ledger' => [$this->ledgerColumns(), $this->ledgerRows($this->cashTransactions($transactions))],
            'bank_ledger' => [$this->ledgerColumns(), $this->ledgerRows($this->bankTransactions($transactions))],
            'tax' => [$this->taxColumns(), $this->taxRows($transactions)],
            'activity_summary' => [$this->activityColumns(), $this->activityRows($transactions)],
            'account_summary' => [$this->accountColumns(), $this->accountRows($transactions)],
            'transaction_recap' => [$this->recapColumns(), $this->recapRows($transactions)],
            default => [$this->accountColumns(), $this->accountRows($transactions)],
        };
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
            'date' => (($d = $transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->format('d-m-Y') : null) ?? '-',
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
    private function bkuLedgerRows(string $scope, ?int $period): array
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

        $firstMonth = min($months);
        $openingBank = 0.0;
        $openingCash = 0.0;
        foreach ($records as $payload) {
            if ((int) substr((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), 5, 2) !== $firstMonth) {
                continue;
            }
            match ($payload['REK_BKU'] ?? '') {
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

        $openingLines = [];
        foreach ($records as $payload) {
            if ((int) substr((string) ($payload['TANGGAL_TRANSAKSI'] ?? ''), 5, 2) === $firstMonth
                && in_array($payload['REK_BKU'] ?? '', ['Saldo Awal Bank', 'Saldo Awal Tunai'], true)) {
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
                'balance' => $bank + $cash,
            ];
        }

        $kegiatanCache = [];
        $lines = [];
        $lineOrder = 0;
        foreach ($records as $payload) {
            $rek = (string) ($payload['REK_BKU'] ?? '');
            if (in_array($rek, ['Saldo Awal Bank', 'Saldo Awal Tunai'], true)) {
                continue;
            }

            $amount = (float) ($payload['JUMLAH'] ?? 0);
            $incoming = 0.0;
            $outgoing = 0.0;

            match ($rek) {
                'Terima Dana BOS', 'Pajak Belanja Terima', 'Pergeseran Tunai' => [$incoming, $bank, $cash] = $this->bkuReceive($amount, $rek, $bank, $cash),
                'Kas Keluar', 'Kas Keluar Non Tunai', 'Pajak Belanja Setor', 'Tarik Tunai' => [$outgoing, $bank, $cash] = $this->bkuSpend($amount, $rek, $bank, $cash),
                default => null,
            };

            $totalIn += $incoming;
            $totalOut += $outgoing;
            $parentKey = (string) ($payload['PARENT_ID_KAS_UMUM'] ?? '');
            $this->bkuLineCount++;
            $lines[] = [
                'order' => $lineOrder++,
                'rek' => $rek,
                'date' => $this->bkuDate($payload),
                'activity' => $this->bkuActivityCode($payload, $records, $kegiatanCache, $rkasMap, $chainMaps),
                'account' => (string) ($payload['KODE_REKENING'] ?? '') ?: '-',
                'evidence' => (string) ($payload['NO_BUKTI'] ?? ''),
                'parentEvidence' => $evidenceByKas[$parentKey] ?? '',
                'mirror' => (string) ($payload['URAIAN'] ?? ''),
                'description' => $this->bkuDescription($payload, $operatorMaps),
                'payment' => $this->bkuPaymentLine($payload, $operatorMaps),
                'item' => $this->bkuItemLine($payload, $operatorMaps),
                'incoming' => $incoming,
                'outgoing' => $outgoing,
                'balance' => $bank + $cash,
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
            'balance' => $bank + $cash,
        ];

        $this->bkuClosing = ['bank' => $bank, 'cash' => $cash, 'total' => $bank + $cash];

        return $rows;
    }

    /** @return array{0:float,1:float,2:float} */
    private function bkuReceive(float $amount, string $rek, float $bank, float $cash): array
    {
        if ($rek === 'Terima Dana BOS') {
            $bank += $amount;
        } else {
            $cash += $amount;
        }

        return [$amount, $bank, $cash];
    }

    /** @return array{0:float,1:float,2:float} */
    private function bkuSpend(float $amount, string $rek, float $bank, float $cash): array
    {
        if ($rek === 'Kas Keluar Non Tunai') {
            $bank -= $amount;
        } elseif ($rek === 'Tarik Tunai') {
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
            $rows = DB::table('arkas_mirror_ref_kode')
                ->whereIn('source_key', array_keys($refKeys))
                ->get(['source_key', 'payload']);
            foreach ($rows as $row) {
                $refMap[(string) $row->source_key] = (string) (json_decode((string) ($row->payload ?? ''), true)['id_kode'] ?? '');
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
        return match ($scope) {
            SpjPeriodicReportRegistry::SCOPE_MONTHLY => $period >= 1 && $period <= 12 ? [$period] : [],
            SpjPeriodicReportRegistry::SCOPE_QUARTERLY => $period >= 1 && $period <= 4
                ? range((($period - 1) * 3) + 1, $period * 3)
                : [],
            SpjPeriodicReportRegistry::SCOPE_SEMESTER => $period >= 1 && $period <= 2
                ? range((($period - 1) * 6) + 1, $period * 6)
                : [],
            SpjPeriodicReportRegistry::SCOPE_ANNUAL => range(1, 12),
            default => [],
        };
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
        $itemGroups = [];
        $taxGroups = [];
        foreach ($lines as $line) {
            if (in_array($line['rek'], ['Kas Keluar', 'Kas Keluar Non Tunai'], true) && $line['evidence'] !== '') {
                $itemGroups[$line['evidence']][] = $line;
            } elseif (str_starts_with($line['rek'], 'Pajak Belanja ') && $line['parentEvidence'] !== '') {
                $slot = str_starts_with($line['rek'], 'Pajak Belanja Terima') ? 'terima' : 'setor';
                $taxGroups[$line['parentEvidence']][$slot][] = $line;
            }
        }

        $rows = [];
        $emitted = [];
        foreach ($lines as $line) {
            $bukti = $line['evidence'];
            $isItem = in_array($line['rek'], ['Kas Keluar', 'Kas Keluar Non Tunai'], true) && $bukti !== '';
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

            if ($isTax && isset($itemGroups[$line['parentEvidence']])) {
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
        $rows[] = [
            'row_class' => 'bku-parent',
            'date' => $first['date'],
            'activity' => $first['activity'],
            'account' => $first['account'],
            'evidence' => $bukti,
            'description' => '<div><strong>'.e($first['payment'] !== '' ? $first['payment'] : $first['mirror']).'</strong></div>',
            'incoming' => array_sum(array_column($items, 'incoming')),
            'outgoing' => array_sum(array_column($items, 'outgoing')),
            'balance' => $chronologicalLast['balance'],
        ];

        $child = function (array $line, bool $withAmount) use ($bukti): array {
            $nominal = $line['incoming'] != 0.0 ? $line['incoming'] : $line['outgoing'];
            $label = $line['item'] !== '' ? $line['item'] : $line['mirror'];

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
     * ke uraian mirror bila keduanya kosong.
     *
     * @param  array<string,mixed>  $payload
     * @param  array{items:array<string,array{item:string,payment:string}>,transactions:array<string,string>}  $maps
     */
    private function bkuDescription(array $payload, array $maps): string
    {
        $payment = $this->bkuPaymentLine($payload, $maps);
        $operator = $this->bkuItemLine($payload, $maps);

        $html = '';
        if ($payment !== '') {
            $html .= '<div class="bku-sub">'.e($payment).'</div>';
        }
        $html .= '<div>'.e($operator !== '' ? $operator : (string) ($payload['URAIAN'] ?? '')).'</div>';

        return $html;
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
                'date' => (($d = $transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->format('d-m-Y') : null) ?? '-',
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
            'date' => (($d = $transaction->sourceValue('transaction_date')) ? Carbon::parse($d)->format('d-m-Y') : null) ?? '-',
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
            'spb' => [
                "SPB {$periodLabel} menyajikan ringkasan pengeluaran yang dipertanggungjawabkan pada periode laporan beserta rekap rekening belanjanya.",
            ],
            'sp2b' => [
                "SP2B {$periodLabel} merangkum nilai bruto, pajak, realisasi netto, dan rekap rekening transaksi pada periode aktif.",
            ],
            'sp2t' => [
                "SP2T {$periodLabel} menyajikan pertanggungjawaban transaksi dan pajak periode aktif untuk proses penatausahaan berikutnya.",
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
        return in_array($presentation, ['ledger', 'cash_ledger', 'bank_ledger', 'tax', 'transaction_recap', 'bku_ledger'], true)
            ? 'landscape'
            : 'portrait';
    }

    /**
     BKU resmi memakai kertas F4/Folio; laporan lain tetap A4.
     */
    private function paper(string $presentation): string
    {
        return $presentation === 'bku_ledger' ? 'folio' : 'a4';
    }

    private function fileName(string $label, string $periodLabel): string
    {
        $value = strtoupper($label.'-'.$periodLabel);
        $value = preg_replace('/[^A-Z0-9]+/', '-', $value) ?: 'LAPORAN-PERIODE';

        return trim($value, '-');
    }
}
