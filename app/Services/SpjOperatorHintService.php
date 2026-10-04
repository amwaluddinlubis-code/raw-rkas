<?php

namespace App\Services;

use App\Models\SpjPackage;
use App\Models\Transaction;

/**
 * Hint operator non-blocking untuk checklist Paket SPJ.
 *
 * Pure read-only helper: tidak mengubah lifecycle, numbering, sync,
 * maupun tenant boundary. Pemanggil wajib memastikan paket sudah
 * berada pada konteks aktif (lihat SpjPackageChecklistController).
 *
 * Aturan ambang mengikuti poster 10 pola bukti dukung
 * (docs/SPJ_SUPPORTING_DOCUMENT_PATTERNS.md): materai > Rp5 Jt,
 * PPN belanja > Rp2 Jt, PPh 23 2% + pajak restoran 10%.
 */
final class SpjOperatorHintService
{
    public function __construct(
        private readonly SpjProcurementPolicyService $procurement,
    ) {}

    /**
     * @return array<int, array{key: string, label: string, message: string}>
     */
    public function hints(SpjPackage $package, array $externalCheckedKeys = []): array
    {
        $transaction = $package->transaction;
        if (! $transaction instanceof Transaction) {
            return [];
        }

        $hints = [];
        $gross = (float) $transaction->sourceValue('gross_amount');
        $category = strtoupper((string) $transaction->spj_category);

        if ($gross > 5000000) {
            $hints[] = [
                'key' => 'materai',
                'label' => 'Materai Rp10.000',
                'message' => 'Nilai di atas Rp5 Jt — pastikan kuitansi memakai materai Rp10.000 dan nomor, nilai BKU, serta kuitansi saling cocok.',
            ];
        }

        if (in_array($category, ['BARANG', 'JASA_LAINNYA', 'PEMELIHARAAN'], true)
            && $gross > 2000000
            && (float) $transaction->sourceValue('ppn') <= 0) {
            $hints[] = [
                'key' => 'ppn_check',
                'label' => 'Periksa PPN',
                'message' => 'Belanja di atas Rp2 Jt tanpa PPN tercatat — pastikan Bukti Setor PPN tersedia atau memang tidak ada kewajiban pajak.',
            ];
        }

        if ($category === 'KONSUMSI' && (float) $transaction->sourceValue('pph23') <= 0) {
            $hints[] = [
                'key' => 'konsumsi_tax',
                'label' => 'Periksa pajak konsumsi',
                'message' => 'Konsumsi tanpa PPh 23 tercatat — periksa PPh 23 2% dan pajak restoran 10% pada Bukti Setor Pajak.',
            ];
        }

        foreach ($this->duplicateWarnings($transaction) as $warning) {
            $hints[] = $warning;
        }

        if ($category === 'BARANG' && ($this->procurement->isSiplah($transaction))) {
            $missing = array_diff(
                ['bukti_transfer', 'faktur', 'invoice_eksternal', 'berita_acara_serah_terima', 'foto_barang'],
                $externalCheckedKeys
            );
            if ($missing !== []) {
                $hints[] = [
                    'key' => 'siplah_docs',
                    'label' => 'Kelengkapan SiPlah',
                    'message' => 'Transaksi SiPlah — pastikan transfer, faktur/invoice, BAST, dan foto barang sudah dicentang pada Bukti Dukung Eksternal ('.count($missing).' belum ditandai).',
                ];
            }
        }

        return array_values($hints);
    }

    /**
     * Perbandingan berdampingan Nomor + Nilai BKU (mirror readonly)
     * vs overlay operator (kuitansi). Read-only, tanpa side effect.
     *
     * @return array{rows: array<int, array{label: string, source: string, overlay: string, match: bool}>, all_match: bool}
     */
    public function bkuMatch(SpjPackage $package): array
    {
        $transaction = $package->transaction;
        if (! $transaction instanceof Transaction) {
            return ['rows' => [], 'all_match' => false];
        }

        $rupiah = static fn (mixed $value): string => 'Rp '.number_format((float) $value, 0, ',', '.');

        $sourceRecipient = (string) ($transaction->sourceValue('recipient_name') ?? '');
        $overlayRecipient = (string) ($transaction->effective_receipt_recipient_name ?? '');
        $sourceDescription = (string) ($transaction->sourceValue('description') ?? '');
        $overlayDescription = (string) ($transaction->payment_description ?? '');
        $gross = (float) $transaction->sourceValue('gross_amount');
        $itemTotal = (float) $transaction->items->sum(
            static fn ($item): float => (float) $item->sourceValue('amount')
        );

        $rows = [
            [
                'label' => 'Nomor bukti',
                'source' => (string) ($transaction->sourceValue('no_bukti') ?? '—'),
                'overlay' => $package->document_number ?: 'Belum bernomor (wajar sebelum penomoran)',
                'match' => true,
            ],
            [
                'label' => 'Nilai bruto',
                'source' => $rupiah($gross),
                'overlay' => $transaction->items->isEmpty() ? 'Rincian belum ada' : $rupiah($itemTotal),
                'match' => $transaction->items->isEmpty() || abs($itemTotal - $gross) <= 0.01,
            ],
            [
                'label' => 'Penerima',
                'source' => $sourceRecipient !== '' ? $sourceRecipient : '—',
                'overlay' => $overlayRecipient !== '' ? $overlayRecipient : 'Belum diisi operator',
                'match' => $overlayRecipient !== '',
            ],
            [
                'label' => 'Uraian',
                'source' => $sourceDescription !== '' ? $sourceDescription : '—',
                'overlay' => $overlayDescription !== '' ? $overlayDescription : 'Belum diisi operator',
                'match' => $overlayDescription !== '',
            ],
        ];

        return [
            'rows' => $rows,
            'all_match' => collect($rows)->every(static fn (array $row): bool => $row['match']),
        ];
    }

    /**
     * Saran kategori canonical dari rekening/uraian sumber. Hint saja —
     * operator tetap yang memutuskan lewat Isian Manual.
     *
     * @return array{category: string, label: string, reason: string}|null
     */
    public function categorySuggestion(Transaction $transaction): ?array
    {
        $haystack = mb_strtolower(trim(implode(' ', [
            (string) $transaction->sourceValue('account_name'),
            (string) $transaction->sourceValue('account_code'),
            (string) $transaction->sourceValue('description'),
            (string) $transaction->payment_description,
        ])));

        if ($haystack === '') {
            return null;
        }

        $guess = match (true) {
            $this->containsAny($haystack, ['honor', 'honorarium', 'gtt', 'ptt', 'narasumber', 'insentif']) => 'HONOR_PEGAWAI',
            $this->containsAny($haystack, ['sppd', 'perjalanan dinas', 'perjadin', 'transport perjalanan', 'uang harian']) => 'SPPD',
            $this->containsAny($haystack, ['makan', 'minum', 'konsumsi', 'rapat', 'k3s', 'kkg', 'snack', 'catering']) => 'KONSUMSI',
            $this->containsAny($haystack, ['pemeliharaan', 'rehab', 'renovasi', 'tukang', 'service', 'perbaikan', 'pengecatan']) => 'PEMELIHARAAN',
            $this->containsAny($haystack, ['sewa', 'jasa', 'ekstrakurikuler', 'fotocopy', 'penggandaan', 'cetak', 'langganan']) => 'JASA_LAINNYA',
            $this->containsAny($haystack, ['belanja modal', 'aset', 'pengadaan barang', 'alat tulis', 'atk', 'buku', 'seragam', 'komputer', 'mebel']) => 'BARANG',
            default => null,
        };

        if ($guess === null || strtoupper((string) $transaction->spj_category) === $guess) {
            return null;
        }

        return [
            'category' => $guess,
            'label' => match ($guess) {
                'HONOR_PEGAWAI' => 'Honor Pegawai',
                'SPPD' => 'SPPD',
                'KONSUMSI' => 'Konsumsi',
                'PEMELIHARAAN' => 'Pemeliharaan',
                'JASA_LAINNYA' => 'Jasa Lainnya',
                default => 'Barang',
            },
            'reason' => 'Disarankan dari kode/nama rekening dan uraian sumber. Pilihan akhir tetap di Isian Manual Paket.',
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, message: string}>
     */
    private function duplicateWarnings(Transaction $transaction): array
    {
        if (! $transaction->exists) {
            return [];
        }

        $warnings = [];

        if (filled($transaction->invoice_number) && filled($transaction->vendor_name)) {
            $sameInvoice = $transaction->newQuery()
                ->where('fiscal_year_id', $transaction->fiscal_year_id)
                ->where('fund_source_id', $transaction->fund_source_id)
                ->whereKeyNot($transaction->id)
                ->whereRaw('LOWER(TRIM(invoice_number)) = ?', [mb_strtolower(trim((string) $transaction->invoice_number))])
                ->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower(trim((string) $transaction->vendor_name))])
                ->exists();

            if ($sameInvoice) {
                $warnings[] = [
                    'key' => 'duplicate_invoice',
                    'label' => 'Kemungkinan invoice ganda',
                    'message' => 'Nomor invoice ini sudah dipakai vendor yang sama pada tahun+sumber dana aktif — periksa sebelum lanjut agar tidak double entry.',
                ];
            }
        }

        if (filled($transaction->vendor_name) && (float) $transaction->sourceValue('gross_amount') > 0) {
            // Nilai bruto adalah fakta mirror (bukan kolom lokal), jadi
            // bandingkan di PHP setelah filter vendor di SQL.
            $gross = (float) $transaction->sourceValue('gross_amount');
            $sameAmount = $transaction->newQuery()
                ->where('fiscal_year_id', $transaction->fiscal_year_id)
                ->where('fund_source_id', $transaction->fund_source_id)
                ->whereKeyNot($transaction->id)
                ->whereRaw('LOWER(TRIM(vendor_name)) = ?', [mb_strtolower(trim((string) $transaction->vendor_name))])
                ->limit(20)
                ->get(['id'])
                ->filter(static function (Transaction $sibling) use ($gross): bool {
                    try {
                        return abs((float) $sibling->sourceValue('gross_amount') - $gross) < 0.01;
                    } catch (\Throwable) {
                        return false;
                    }
                });

            if ($sameAmount->isNotEmpty()) {
                $warnings[] = [
                    'key' => 'duplicate_amount',
                    'label' => 'Vendor + nominal sama',
                    'message' => 'Ditemukan '.$sameAmount->count().' transaksi lain dari vendor yang sama dengan nominal persis sama — pastikan bukan pembayaran ganda atas nota yang sama.',
                ];
            }
        }

        return $warnings;
    }

    /** @param array<int, string> $needles */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
