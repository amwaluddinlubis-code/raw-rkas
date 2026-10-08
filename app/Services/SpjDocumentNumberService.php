<?php

namespace App\Services;

use App\Models\DocumentNumberFormat;
use App\Models\SpjDocument;
use App\Models\SpjGoods;
use App\Models\SpjPackage;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDOException;

class SpjDocumentNumberService
{
    /**
     * Sentinel fund_source_id untuk sequence tanpa sumber dana (NULL legacy).
     * Seluruh pembaca/penulis tabel document_number_sequences wajib memakai
     * sentinel ini agar unique index (fiscal_year_id, fund_source_id,
     * format_name, period_key) benar-benar melindungi grupnya di SQLite,
     * yang menganggap NULL sebagai nilai distinct. Nilai 0 aman karena id
     * fund_sources selalu positif.
     */
    public const NULL_FUND_SOURCE_SENTINEL = 0;

    public function __construct(private readonly SpjNumberingPolicyService $policy) {}

    /**
     * Terbitkan nomor domain paket berdasarkan registry canonical.
     * Nomor yang sudah tersedia tidak pernah ditimpa.
     *
     * @param  array<int, string>|null  $onlyDocumentTypes
     * @return array{created:int,skipped:int,documents:Collection<int, SpjDocument>}
     */
    public function assignAutomaticNumbers(SpjPackage $package, string $schoolCode, ?string $npsn = null, ?array $onlyDocumentTypes = null): array
    {
        $package->load('transaction');
        $package->transaction?->load(['goods', 'workOrder', 'travels', 'goodsReceipts.items']);
        $transaction = $package->transaction;
        $documents = collect();
        $created = 0;
        $skipped = 0;
        $selectedTypes = $onlyDocumentTypes === null
            ? null
            : collect($onlyDocumentTypes)
                ->map(fn (string $type): ?string => $this->policy->canonicalAutomaticDocumentType($type))
                ->filter()
                ->unique()
                ->values();

        $assign = function (string $type, CarbonInterface $date, string $scopeKey = 'MAIN', bool $count = true) use ($package, $schoolCode, $npsn, $documents, &$created, &$skipped): SpjDocument {
            $alreadyNumbered = $package->documents()
                ->where(['document_type' => $type, 'scope_key' => $scopeKey])
                ->where('status', '!=', 'CANCELLED')
                ->whereNotNull('document_number')
                ->exists();
            $document = $this->assign($package, $type, $date, $schoolCode, $scopeKey, npsn: $npsn);
            $documents->push($document);
            if ($count) {
                $alreadyNumbered ? $skipped++ : $created++;
            }

            return $document;
        };

        foreach ($this->policy->automaticDocumentTypes() as $type) {
            if (($selectedTypes !== null && ! $selectedTypes->contains($type))
                || ! $this->policy->isAutomaticDocumentEligible($transaction, $type)) {
                continue;
            }

            $definition = $this->policy->numberingDefinition($type);
            if (! $definition) {
                continue;
            }
            $target = $definition['number_target'];
            $targetField = $target['field'];
            $eventDate = $this->policy->documentEventDateValue($transaction, $type);

            if ($target['relation'] === 'package') {
                if ($eventDate) {
                    $assign($type, Carbon::parse($eventDate));
                }

                continue;
            }

            if ($target['relation'] === 'goods') {
                if (($definition['scope_rule'] ?? null) === 'TAHAP'
                    && $transaction->goodsReceipts->where('status', '!==', 'CANCELLED')->count() > 1) {
                    $staged = $this->assignTahapNumbers($package, $transaction, $type, $targetField, $assign, $documents);
                    $created += $staged['created'];
                    $skipped += $staged['skipped'];

                    continue;
                }
                if (! $eventDate || ! $targetField) {
                    continue;
                }
                $existing = $transaction->goods->pluck($targetField)->filter()->first();
                if ($existing) {
                    $transaction->goods()->whereNull($targetField)->update([$targetField => $existing]);
                    $skipped++;

                    continue;
                }
                $document = $assign($type, Carbon::parse($eventDate));
                $transaction->goods()->whereNull($targetField)->update([$targetField => $document->document_number]);

                continue;
            }

            if ($target['relation'] === 'workOrder') {
                $workOrder = $transaction->workOrder;
                if (! $workOrder || ! $eventDate || ! $targetField) {
                    continue;
                }
                if (filled($workOrder->{$targetField})) {
                    $skipped++;

                    continue;
                }
                $document = $assign($type, Carbon::parse($eventDate));
                $workOrder->forceFill([$targetField => $document->document_number])->save();

                continue;
            }

            if ($target['relation'] === 'travels') {
                $rule = $definition['event_date_rule'];
                $travels = $transaction->travels->sortBy(function ($travel) use ($rule): string {
                    $date = $travel->{$rule['field']} ?: ($rule['fallback_field'] ? $travel->{$rule['fallback_field']} : null) ?: '9999-12-31';

                    return Carbon::parse($date)->format('Y-m-d').'-'.str_pad((string) ($travel->sort_order ?? 0), 8, '0', STR_PAD_LEFT).'-'.str_pad((string) $travel->id, 12, '0', STR_PAD_LEFT);
                });
                foreach ($travels as $travel) {
                    $travelEventDate = $travel->{$rule['field']} ?: ($rule['fallback_field'] ? $travel->{$rule['fallback_field']} : null);
                    if (! $travelEventDate) {
                        continue;
                    }
                    if ($targetField && filled($travel->{$targetField})) {
                        $skipped++;

                        continue;
                    }
                    $scopeKey = $definition['scope_rule'] === 'TRAVEL' ? 'TRAVEL-'.$travel->id : 'MAIN';
                    $document = $assign($type, Carbon::parse($travelEventDate), $scopeKey);
                    if ($targetField) {
                        $travel->forceFill([$targetField => $document->document_number])->save();
                    }
                    if ($rule['field'] === 'assignment_letter_date' && blank($travel->{$rule['field']})) {
                        $travel->forceFill([$rule['field'] => $travelEventDate])->save();
                    }
                }
            }
        }

        return compact('created', 'skipped', 'documents');
    }

    /**
     * Per-tahap numbering for staged goods letters (BNU33 pattern: one
     * payment, several monthly deliveries). Each receipt gets its own
     * document scope TAHAP:n; the assigned number is written back only
     * onto the spj_goods rows of that tahap's items.
     *
     * @param  callable(string, CarbonInterface, string, bool):SpjDocument  $assign
     * @param  Collection<int, SpjDocument>  $documents
     * @return array{created:int,skipped:int}
     */
    private function assignTahapNumbers(SpjPackage $package, Transaction $transaction, string $type, string $targetField, callable $assign, Collection $documents): array
    {
        $created = 0;
        $skipped = 0;
        $receipts = $transaction->goodsReceipts
            ->where('status', '!==', 'CANCELLED')
            ->sortBy('receipt_sequence')
            ->values();

        foreach ($receipts as $receipt) {
            $scopeKey = 'TAHAP:'.$receipt->receipt_sequence;
            $eventDate = $this->policy->documentEventDateValue($transaction, $type, $scopeKey);
            if (! $eventDate) {
                continue;
            }
            $itemIds = $receipt->items->pluck('transaction_item_id')->all();
            $goodsQuery = SpjGoods::query()->whereIn('transaction_item_id', $itemIds);
            if ($itemIds === [] || $goodsQuery->count() === 0) {
                continue;
            }
            $existing = (clone $goodsQuery)->whereNotNull($targetField)->value($targetField);
            if ($existing) {
                (clone $goodsQuery)->whereNull($targetField)->update([$targetField => $existing]);
                $skipped++;

                continue;
            }
            $document = $assign($type, Carbon::parse($eventDate), $scopeKey, false);
            (clone $goodsQuery)->whereNull($targetField)->update([$targetField => $document->document_number]);
            $documents->push($document);
            $created++;
        }

        return compact('created', 'skipped');
    }

    public function assign(
        SpjPackage $package,
        string $documentType,
        CarbonInterface $documentDate,
        string $schoolCode,
        string $scopeKey = 'MAIN',
        ?int $templateId = null,
        ?string $npsn = null,
    ): SpjDocument {
        $documentType = $this->policy->canonicalAutomaticDocumentType($documentType);
        if ($documentType === null) {
            throw new InvalidArgumentException('Jenis dokumen tidak termasuk domain penomoran canonical aplikasi.');
        }
        $documentDate = $this->canonicalDocumentDate($package, $documentType, $documentDate, $scopeKey);

        return DB::connection('school')->transaction(function () use ($package, $documentType, $documentDate, $schoolCode, $scopeKey, $templateId, $npsn): SpjDocument {
            $identity = [
                'spj_package_id' => $package->id,
                'document_type' => $documentType,
                'scope_key' => $scopeKey,
            ];
            $activeDocument = SpjDocument::query()
                ->where($identity)
                ->where('status', '!=', 'CANCELLED')
                ->latest('id')
                ->first();
            if ($activeDocument?->document_number) {
                return $activeDocument;
            }

            $document = $activeDocument ?? new SpjDocument($identity);
            $yearId = (int) $package->transaction->fiscal_year_id;
            // NULL fund_source_id dinormalisasi ke sentinel agar unique index
            // sequence melindungi grup tanpa sumber dana (K2).
            $fundSourceId = $package->transaction->fund_source_id === null
                ? self::NULL_FUND_SOURCE_SENTINEL
                : (int) $package->transaction->fund_source_id;
            $format = $this->policy->formatFor($yearId, $documentType);
            $periodKey = $this->periodKey($format->reset_period, $documentDate);
            $sequenceKey = [
                'fiscal_year_id' => $yearId,
                'fund_source_id' => $fundSourceId,
                'format_name' => $documentType,
                'period_key' => $periodKey,
            ];
            $next = $this->allocateSequenceNumber($sequenceKey);

            $number = $this->renderNumber($format, $documentType, $next, $documentDate, $schoolCode, $npsn);
            $document->fill([
                'document_template_id' => $templateId,
                'document_number' => $number,
                'sequence_number' => $next,
                'numbering_period_key' => $periodKey,
                'document_date' => $documentDate,
                'event_date' => $documentDate,
                'status' => 'NUMBERED',
                'is_late_entry' => (bool) $package->is_late_entry,
                'numbered_at' => now(),
                'replaces_document_id' => null,
                'cancelled_at' => null,
                'cancelled_by' => null,
                'cancellation_reason' => null,
            ])->save();

            $definition = $this->policy->numberingDefinition($documentType);
            if (($definition['number_target']['relation'] ?? null) === 'package' && $scopeKey === 'MAIN') {
                $field = $definition['number_target']['field'] ?? null;
                $updates = [
                    'status' => 'NUMBERED',
                    'numbered_at' => now(),
                    'cancelled_at' => null,
                    'cancelled_by' => null,
                    'cancellation_reason' => null,
                ];
                if ($field) {
                    $updates[$field] = $number;
                }
                $package->forceFill($updates)->save();
            }

            return $document;
        });
    }

    /**
     * Alokasikan nomor urut berikutnya untuk satu scope sequence.
     *
     * Idempoten terhadap race insert-pertama (S3): bila baris sequence
     * dimenangkan proses lain di antara baca dan insert, duplicate-key
     * exception ditangkap lalu alokasi diulang dengan membaca ulang baris
     * pemenang — tidak pernah 500 dan tidak pernah nomor ganda.
     *
     * @param  array{fiscal_year_id:int,fund_source_id:int,format_name:string,period_key:string}  $sequenceKey
     */
    private function allocateSequenceNumber(array $sequenceKey): int
    {
        $sequence = DB::connection('school')->table('document_number_sequences')
            ->where($sequenceKey)
            ->lockForUpdate()->first();
        if ($sequence) {
            $next = ((int) $sequence->last_number) + 1;
            DB::connection('school')->table('document_number_sequences')->where('id', $sequence->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        }

        try {
            $this->insertSequenceRow($sequenceKey, 1);
        } catch (QueryException $exception) {
            if (! $this->isDuplicateKeyException($exception)) {
                throw $exception;
            }
            $sequence = DB::connection('school')->table('document_number_sequences')
                ->where($sequenceKey)
                ->lockForUpdate()->first();
            if (! $sequence) {
                throw $exception;
            }
            $next = ((int) $sequence->last_number) + 1;
            DB::connection('school')->table('document_number_sequences')->where('id', $sequence->id)
                ->update(['last_number' => $next, 'updated_at' => now()]);

            return $next;
        }

        return 1;
    }

    /**
     * @param  array{fiscal_year_id:int,fund_source_id:int,format_name:string,period_key:string}  $sequenceKey
     */
    protected function insertSequenceRow(array $sequenceKey, int $next): void
    {
        DB::connection('school')->table('document_number_sequences')->insert($sequenceKey + [
            'last_number' => $next,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function isDuplicateKeyException(QueryException $exception): bool
    {
        $previous = $exception->getPrevious();
        $code = $previous instanceof PDOException ? (string) $previous->getCode() : '';
        $message = strtolower($exception->getMessage().' '.($previous?->getMessage() ?? ''));

        return $code === '23000'
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'duplicate key');
    }

    public function renderConfiguredNumber(
        DocumentNumberFormat $format,
        string $documentType,
        int $sequence,
        CarbonInterface $documentDate,
        string $schoolCode,
        ?string $npsn = null,
    ): string {
        return $this->renderNumber($format, strtoupper(trim($documentType)), $sequence, $documentDate, $schoolCode, $npsn);
    }

    private function canonicalDocumentDate(SpjPackage $package, string $documentType, CarbonInterface $fallback, string $scopeKey): CarbonInterface
    {
        $package->load('transaction');
        $package->transaction?->load(['goods', 'workOrder', 'travels', 'goodsReceipts.items']);
        $value = $this->policy->documentEventDateValue($package->transaction, $documentType, $scopeKey);

        return filled($value) ? Carbon::parse($value) : $fallback;
    }

    private function periodKey(string $resetPeriod, CarbonInterface $date): string
    {
        return match (strtoupper($resetPeriod)) {
            'MONTH' => $date->format('Y-m'),
            'QUARTER' => $date->format('Y').'-Q'.ArkasMirrorResolver::quarterOfMonth((int) $date->format('n')),
            'NONE' => 'ALL',
            default => $date->format('Y'),
        };
    }

    private function renderNumber(DocumentNumberFormat $format, string $documentType, int $sequence, CarbonInterface $documentDate, string $schoolCode, ?string $npsn): string
    {
        return strtr($format->format_pattern, [
            '{SEQ}' => str_pad((string) $sequence, $format->padding, '0', STR_PAD_LEFT),
            '{TYPE}' => $documentType,
            '{SCHOOL}' => $schoolCode,
            '{NPSN}' => $npsn ?: $schoolCode,
            '{YEAR}' => $documentDate->format('Y'),
            '{MONTH}' => $documentDate->format('m'),
            '{ROMAN_MONTH}' => $this->romanMonth((int) $documentDate->format('n')),
            '{TW}' => $this->quarterToken($documentDate),
        ]);
    }

    private function quarterToken(CarbonInterface $date): string
    {
        $quarter = ArkasMirrorResolver::quarterOfMonth((int) $date->format('n'));

        return [1 => 'I', 'II', 'III', 'IV'][$quarter];
    }

    private function romanMonth(int $month): string
    {
        return [1 => 'I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month];
    }
}
