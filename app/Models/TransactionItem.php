<?php

namespace App\Models;

use App\Services\ArkasMirrorResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TransactionItem extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'source_item_id', 'siplah_item_mpid', 'rkas_item_code', 'rkas_item_name', 'siplah_item_name', 'siplah_mapped_quantity', 'siplah_quantity_received', 'siplah_unit_dpp', 'siplah_unit_ppn', 'siplah_unit_price', 'siplah_unit_insurance_cost', 'siplah_unit_packaging_cost', 'siplah_item_metadata', 'item_description', 'source_status', 'last_seen_sync_run_id', 'source_missing_since'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function goods(): HasOne
    {
        return $this->hasOne(SpjGoods::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(SpjParticipant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function honors(): HasMany
    {
        return $this->hasMany(SpjHonor::class)->orderBy('sort_order')->orderBy('id');
    }

    public function receiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /**
     * Memo baris mirror kas_umum per instance (bukan atribut database).
     *
     * @var array{generation: int, value: array<string, mixed>|null}|null
     */
    private ?array $mirrorKasUmumMemo = null;

    /**
     * Baris kas_umum mirror (sumber 1:1 via source_item_id).
     * Memo per instance; valid selama generasi resolver tidak berubah
     * (tulisan mirror menaikkan generasi via forget()).
     *
     * @return array<string, mixed>|null
     */
    public function mirrorKasUmum(): ?array
    {
        if (blank($this->source_item_id)) {
            return null;
        }

        try {
            $resolver = app(ArkasMirrorResolver::class);
        } catch (\Throwable) {
            return null;
        }

        if ($this->mirrorKasUmumMemo !== null && $this->mirrorKasUmumMemo['generation'] === $resolver->generation()) {
            return $this->mirrorKasUmumMemo['value'];
        }

        $value = $resolver->kasUmum((string) $this->source_item_id);
        $this->mirrorKasUmumMemo = ['generation' => $resolver->generation(), 'value' => $value];

        return $value;
    }

    /**
     * Batalkan memo mirrorKasUmum instance ini. Dipanggil otomatis saat
     * model disimpan.
     */
    public function forgetMirrorKasUmum(): void
    {
        $this->mirrorKasUmumMemo = null;
    }

    protected static function booted(): void
    {
        static::saved(static function (self $item): void {
            $item->forgetMirrorKasUmum();
        });
    }

    /**
     * Fakta sumber rincian: mirror dulu, kolom lokal sebagai fallback
     * transisi. item_description adalah overlay operator, bukan sumber.
     */
    public function sourceValue(string $field): mixed
    {
        $map = ['description' => 'URAIAN', 'quantity' => 'VOLUME', 'amount' => 'JUMLAH'];

        if (isset($map[$field])) {
            $row = $this->mirrorKasUmum();
            $value = ArkasMirrorResolver::field($row ?? [], [$map[$field]]);

            if ($value !== null && $value !== '') {
                return $field === 'quantity' || $field === 'amount' ? (float) $value : $value;
            }
        }

        if (in_array($field, ['unit', 'unit_price'], true)) {
            $row = $this->mirrorKasUmum();
            $rapbsPeriodeId = ArkasMirrorResolver::field($row ?? [], ['ID_RAPBS_PERIODE']);

            if (is_string($rapbsPeriodeId) && $rapbsPeriodeId !== '') {
                $periode = app(ArkasMirrorResolver::class)->rapbsPeriode($rapbsPeriodeId);
                $key = $field === 'unit' ? ['SATUAN', 'S1', 'S2', 'S3', 'S4'] : ['HARGA_SATUAN'];
                $value = ArkasMirrorResolver::field($periode ?? [], $key);

                if ($value !== null && $value !== '') {
                    return $field === 'unit_price' ? (float) $value : $value;
                }
            }
        }

        return $this->getAttribute($field);
    }

    /**
     * Klon read-only dengan fakta sumber dari mirror
     * (description/quantity/unit/amount). Overlay tidak disentuh.
     */
    public function withMirrorSource(): static
    {
        $row = $this->mirrorKasUmum();

        if ($row === null) {
            return $this;
        }

        $clone = $this->replicate();
        $clone->exists = $this->exists;
        $clone->setAttribute($clone->getKeyName(), $this->getKey());

        $description = ArkasMirrorResolver::field($row, ['URAIAN']);
        $quantity = ArkasMirrorResolver::field($row, ['VOLUME']);
        $amount = ArkasMirrorResolver::field($row, ['JUMLAH']);

        if ($description !== null && $description !== '') {
            $clone->setAttribute('description', $description);
        }
        if ($quantity !== null && $quantity !== '') {
            $clone->setAttribute('quantity', (float) $quantity);
        }
        if ($amount !== null && $amount !== '') {
            $clone->setAttribute('amount', (float) $amount);
        }

        $rapbsPeriodeId = ArkasMirrorResolver::field($row, ['ID_RAPBS_PERIODE']);
        if (is_string($rapbsPeriodeId) && $rapbsPeriodeId !== '') {
            $periode = app(ArkasMirrorResolver::class)->rapbsPeriode($rapbsPeriodeId);
            $unit = ArkasMirrorResolver::field($periode ?? [], ['SATUAN', 'S1', 'S2', 'S3', 'S4']);
            if ($unit !== null && $unit !== '') {
                $clone->setAttribute('unit', $unit);
            }
        }

        return $clone;
    }

    protected function casts(): array
    {
        return ['siplah_mapped_quantity' => 'decimal:2', 'siplah_quantity_received' => 'decimal:2', 'siplah_unit_dpp' => 'decimal:2', 'siplah_unit_ppn' => 'decimal:2', 'siplah_unit_price' => 'decimal:2', 'siplah_unit_insurance_cost' => 'decimal:2', 'siplah_unit_packaging_cost' => 'decimal:2', 'siplah_item_metadata' => 'array', 'source_missing_since' => 'datetime'];
    }
}
