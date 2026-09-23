<?php

namespace App\Models;

use App\Services\ArkasMirrorResolver;
use App\Support\ActiveSpjContext;
use Illuminate\Contracts\Pagination\AbstractPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class Transaction extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        // Konteks + kunci sumber (fakta dibaca dari mirror ARKAS tetap)
        'fiscal_year_id',
        'fund_source_id',
        'id_kas_umum',
        'rkas_date',
        'source_created_at',
        'source_last_updated_at',
        'payment_description',
        'payment_method',
        'payment_reference',
        'vendor_name',
        'vendor_owner',
        'vendor_npwp',
        'invoice_number',
        'invoice_date',
        'invoice_status',
        'ppn_rate',
        'pph21_rate',
        'pph22_rate',
        'pph23_rate',
        'pph4_rate',
        'sspd_rate',
        'is_siplah',
        'siplah_order_number',
        'siplah_metadata',
        'siplah_transaction_id',
        'siplah_marketplace',
        'siplah_merchant_address',
        'siplah_payment_date',
        'siplah_dq_passed',
        'siplah_backfilled',
        'siplah_budget_mapping_rejected',
        'siplah_partially_mapped',
        'status',
        'source_key',
        'source_hash',
        'source_status',
        'last_seen_sync_run_id',
        'source_missing_since',
        'requires_reconciliation',

        // SPJ Transaction Metadata (keep)
        'spj_category', // SPJ category selected
        'spj_recipient_name', // Override for SPJ recipient
        'receipt_recipient_name', // Manual receipt recipient, may differ from ARKAS/BKU recipient
        'event_name',
        'event_location',
        'event_date',
        'participant_count',
        'maintenance_material_transaction_id',
        'maintenance_labor_transaction_id',

    ];

    public function scopeForSpjContext(Builder $query, ActiveSpjContext $context): Builder
    {
        return $query
            ->where('fiscal_year_id', $context->fiscalYearId())
            ->where('fund_source_id', $context->fundSourceId());
    }

    /**
     * Compatibility scope for callers outside the SPJ use-case layer.
     * New SPJ code should pass ActiveSpjContext explicitly via forSpjContext().
     */
    public function scopeActiveContext(Builder $query): Builder
    {
        return $this->scopeForSpjContext($query, app(ActiveSpjContext::class));
    }

    /**
     * Filter transaksi yang benar-benar masih membutuhkan perhatian rekonsiliasi.
     * Resolution terbaru menutup event terbaru yang memiliki source hash sama,
     * walaupun flag legacy requires_reconciliation belum tersapu.
     */
    public function scopeNeedsReconciliation(Builder $query): Builder
    {
        return $query->where(function (Builder $attention): void {
            $attention->where('source_status', 'SOURCE_MISSING')
                ->orWhere(function (Builder $changed): void {
                    $changed->where('requires_reconciliation', true)
                        ->whereNotExists(function ($resolved): void {
                            $resolved->selectRaw('1')
                                ->from('transaction_source_reconciliations as tsr')
                                ->whereColumn('tsr.transaction_id', 'transactions.id')
                                ->whereRaw('(tsr.source_hash = transactions.source_hash OR (tsr.source_hash IS NULL AND transactions.source_hash IS NULL))')
                                ->whereRaw('tsr.source_event_id = (SELECT MAX(tse.id) FROM transaction_source_events tse WHERE tse.transaction_id = transactions.id)');
                        });
                });
        });
    }

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function goods(): HasManyThrough
    {
        return $this->hasManyThrough(SpjGoods::class, TransactionItem::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(SpjWorkOrder::class);
    }

    public function maintenanceMaterialTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'maintenance_material_transaction_id');
    }

    public function maintenanceLaborTransaction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'maintenance_labor_transaction_id');
    }

    public function workers(): HasManyThrough
    {
        return $this->hasManyThrough(SpjWorker::class, SpjWorkOrder::class, 'transaction_id', 'work_order_id')
            ->orderBy('spj_workers.sort_order')
            ->orderBy('spj_workers.id');
    }

    public function participants(): HasManyThrough
    {
        return $this->hasManyThrough(SpjParticipant::class, TransactionItem::class)
            ->orderBy('spj_participants.sort_order')
            ->orderBy('spj_participants.id');
    }

    public function travels(): HasMany
    {
        return $this->hasMany(SpjTravel::class)->orderBy('sort_order')->orderBy('id');
    }

    public function honors(): HasManyThrough
    {
        return $this->hasManyThrough(SpjHonor::class, TransactionItem::class)
            ->orderBy('spj_honors.sort_order')
            ->orderBy('spj_honors.id');
    }

    public function serviceRecipients(): HasMany
    {
        return $this->hasMany(SpjServiceRecipient::class)
            ->chaperone()
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function spjPackage(): HasOne
    {
        return $this->hasOne(SpjPackage::class);
    }

    public function getEffectiveReceiptRecipientNameAttribute(): ?string
    {
        return $this->receipt_recipient_name
            ?: $this->spj_recipient_name
            ?: $this->sourceValue('recipient_name');
    }

    /**
     * Memo agregat mirrorSource per instance (bukan atribut database).
     * Valid hanya selama generasi resolver tidak berubah; setiap tulisan
     * mirror memanggil ArkasMirrorResolver::forget() yang menaikkan
     * generasi sehingga memo basi otomatis dihitung ulang.
     *
     * @var array{generation: int, value: array<string, mixed>|null}|null
     */
    private ?array $mirrorSourceMemo = null;

    /**
     * Agregat fakta sumber dari tabel mirror ARKAS (bukan kolom lokal).
     * Kembalikan null bila mirror belum disinkron — pemanggil memakai
     * kolom lokal sebagai fallback transisi.
     *
     * @return array<string, mixed>|null
     */
    public function mirrorSource(): ?array
    {
        try {
            $resolver = app(ArkasMirrorResolver::class);
        } catch (\Throwable) {
            return null;
        }

        if ($this->mirrorSourceMemo !== null && $this->mirrorSourceMemo['generation'] === $resolver->generation()) {
            return $this->mirrorSourceMemo['value'];
        }

        try {
            // Pakai relasi items yang sudah eager-load bila tersedia agar
            // render daftar tidak menembak satu query pluck per sel.
            // Pengecualian: konteks dokumen pemeliharaan menyimpan kunci
            // milik sendiri karena relasi items diganti milik material.
            $ownKeys = $this->getAttribute('maintenance_own_source_item_ids');
            $ids = is_array($ownKeys) && $ownKeys !== []
                ? array_values(array_filter(array_map(strval(...), $ownKeys)))
                : ($this->relationLoaded('items')
                    ? $this->items->pluck('source_item_id')->filter()->values()->all()
                    : $this->items()->pluck('source_item_id')->filter()->values()->all());
        } catch (\Throwable) {
            $ids = [];
        }

        if ($ids === [] && filled($this->id_kas_umum)) {
            $ids = [(string) $this->id_kas_umum];
        }

        if ($ids === []) {
            $this->mirrorSourceMemo = ['generation' => $resolver->generation(), 'value' => null];

            return null;
        }

        try {
            $value = $resolver->transactionSource(
                $ids,
                filled($this->id_kas_umum) ? (string) $this->id_kas_umum : null
            );
        } catch (\Throwable) {
            $value = null;
        }

        $this->mirrorSourceMemo = ['generation' => $resolver->generation(), 'value' => $value];

        return $value;
    }

    /**
     * Batalkan memo mirrorSource instance ini. Dipanggil otomatis saat
     * model disimpan; penulis yang memutasi items lalu membaca ulang
     * dalam request yang sama wajib memanggil ini secara eksplisit.
     */
    public function forgetMirrorSource(): void
    {
        $this->mirrorSourceMemo = null;
    }

    /**
     * Hangatkan memo mirror untuk sekumpulan transaksi sekaligus.
     * Relasi items dimuat bulk lebih dulu sehingga pemanasan tidak
     * menimbulkan query pluck per transaksi.
     *
     * @param  iterable<int, Transaction>|Transaction|null  $transactions
     */
    public static function preloadMirrorSource(mixed $transactions): void
    {
        if ($transactions instanceof self) {
            $transactions->mirrorSource();

            return;
        }

        if ($transactions instanceof AbstractPaginator) {
            $transactions = $transactions->getCollection();
        }

        if ($transactions instanceof LazyCollection) {
            $transactions = $transactions->collect();
        }

        $models = collect($transactions)
            ->filter(static fn ($item): bool => $item instanceof self)
            ->values();

        if ($models->isEmpty()) {
            return;
        }

        $missingKeys = $models
            ->reject(static fn (self $transaction): bool => $transaction->relationLoaded('items'))
            ->map(static fn (self $transaction) => $transaction->getKey())
            ->filter()
            ->values();

        if ($missingKeys->isNotEmpty()) {
            // Satu query untuk seluruh transaksi yang belum memuat items.
            $fresh = self::query()
                ->whereKey($missingKeys->all())
                ->with('items:id,transaction_id,source_item_id')
                ->get(['id'])
                ->keyBy(fn (self $transaction) => $transaction->getKey());

            foreach ($models as $transaction) {
                $match = $fresh->get($transaction->getKey());
                if ($match instanceof self) {
                    $transaction->setRelation('items', $match->items);
                }
            }
        }

        foreach ($models as $transaction) {
            $transaction->mirrorSource();
        }
    }

    protected static function booted(): void
    {
        static::saved(static function (self $transaction): void {
            $transaction->forgetMirrorSource();
        });
    }

    /**
     * Baca fakta sumber dengan prioritas mirror, fallback kolom lokal.
     * Overlay operator (payment_description, spj_category, dsb) selalu
     * dibaca dari kolom lokal dan tidak boleh lewat sini.
     */
    public function sourceValue(string $field): mixed
    {
        $source = $this->mirrorSource();

        if (is_array($source) && array_key_exists($field, $source) && $source[$field] !== null && $source[$field] !== '') {
            return $source[$field];
        }

        return $this->getAttribute($field);
    }

    /** Fakta tanggal sumber sebagai Carbon (mirror dulu, lokal fallback). */
    public function sourceCarbon(string $field = 'transaction_date'): ?Carbon
    {
        $value = $this->sourceValue($field);

        if ($value instanceof \DateTimeInterface) {
            return Carbon::parse($value);
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * Klon read-only dengan fakta sumber diganti nilai mirror.
     * Overlay operator tidak disentuh. Bila mirror belum ada,
     * kembalikan instance ini (tidak ada perubahan perilaku).
     */
    public function withMirrorSource(): static
    {
        $source = $this->mirrorSource();

        if ($source === null) {
            return $this;
        }

        $clone = $this->replicate();
        $clone->exists = $this->exists;
        $clone->setAttribute($clone->getKeyName(), $this->getKey());
        $this->loadMissing('items');
        $clone->setRelation('items', $this->items->map(fn (TransactionItem $item): TransactionItem => $item->withMirrorSource()));

        foreach (['no_bukti', 'transaction_date', 'description', 'account_code', 'recipient_name', 'gross_amount', 'ppn', 'pph21', 'pph22', 'pph23', 'pph4', 'sspd', 'tax_total', 'net_amount'] as $field) {
            if (array_key_exists($field, $source) && $source[$field] !== null && $source[$field] !== '') {
                $clone->setAttribute($field, $source[$field]);
            }
        }

        return $clone;
    }

    /**
     * Legacy template alias. Penandatangan transaksi tidak lagi disimpan;
     * template lama selalu mengambil Kepala Sekolah dari profil tahun aktif.
     */
    public function getSignatoryNameAttribute(): ?string
    {
        return DB::connection('school')
            ->table('school_profiles')
            ->where('fiscal_year_id', $this->fiscal_year_id)
            ->value('principal_name');
    }

    /** Legacy template alias untuk template lama yang masih memakai placeholder jabatan penandatangan. */
    public function getSignatoryRoleAttribute(): string
    {
        return 'Kepala Sekolah';
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    protected function casts(): array
    {
        return ['rkas_date' => 'date', 'source_created_at' => 'datetime', 'source_last_updated_at' => 'datetime', 'invoice_date' => 'date', 'event_date' => 'date', 'siplah_payment_date' => 'datetime', 'participant_count' => 'integer', 'source_missing_since' => 'datetime', 'requires_reconciliation' => 'boolean', 'siplah_dq_passed' => 'boolean', 'siplah_backfilled' => 'boolean', 'siplah_budget_mapping_rejected' => 'boolean', 'siplah_partially_mapped' => 'boolean', 'ppn_rate' => 'decimal:4', 'pph21_rate' => 'decimal:4', 'pph22_rate' => 'decimal:4', 'pph23_rate' => 'decimal:4', 'pph4_rate' => 'decimal:4', 'sspd_rate' => 'decimal:4', 'is_siplah' => 'boolean', 'siplah_metadata' => 'array'];
    }
}
