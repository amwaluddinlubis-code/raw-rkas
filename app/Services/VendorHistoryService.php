<?php

namespace App\Services;

use App\Models\Transaction;

/**
 * Read-only vendor memory for the SPJ package form.
 *
 * Suggests vendor_owner / receipt_recipient_name from the latest tenant
 * transaction carrying the same vendor_name, so operators do not retype
 * details for repeat suppliers. Results are advisory only: the form
 * auto-fills empty fields and the operator confirms by saving.
 *
 * Source facts (no_bukti, transaction_date) live in the ARKAS fixed
 * mirror and are read through ArkasMirrorResolver, like the rest of
 * the app, since the duplicate local columns were dropped.
 */
class VendorHistoryService
{
    /**
     * @return array{vendor_owner:?string, receipt_recipient_name:?string, no_bukti:string, transaction_date:?string}|null
     */
    public function latestForVendor(string $vendorName, ?int $excludeTransactionId = null): ?array
    {
        $normalized = $this->normalize($vendorName);
        if ($normalized === '') {
            return null;
        }

        // Name variants differ by case and inner spacing ("UD. X" vs
        // "ud.  X"), which SQL cannot collapse portably, so candidates
        // are filtered by the PHP normalizer below. Tenant transaction
        // volume is in the hundreds; the 500-row window is documented.
        $candidates = Transaction::query()
            ->whereNotNull('vendor_name')
            ->when($excludeTransactionId, fn ($query) => $query->where('id', '!=', $excludeTransactionId))
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->filter(
                fn (Transaction $row) => $this->normalize((string) $row->vendor_name) === $normalized
                    && (filled($row->vendor_owner) || filled($row->receipt_recipient_name))
            )
            ->sortByDesc(fn (Transaction $row) => [
                (string) ($row->sourceCarbon('transaction_date')?->format('Y-m-d') ?? ''),
                $row->id,
            ])
            ->values();

        $match = $candidates->first();
        if (! $match instanceof Transaction) {
            return null;
        }

        return [
            'vendor_owner' => filled($match->vendor_owner) ? trim((string) $match->vendor_owner) : null,
            'receipt_recipient_name' => filled($match->receipt_recipient_name) ? trim((string) $match->receipt_recipient_name) : null,
            'no_bukti' => (string) ($match->sourceValue('no_bukti') ?? ''),
            'transaction_date' => $match->sourceCarbon('transaction_date')?->format('Y-m-d'),
        ];
    }

    private function normalize(string $value): string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($collapsed);
    }
}
