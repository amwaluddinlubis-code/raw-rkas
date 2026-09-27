<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    protected $connection = 'school';

    protected $fillable = ['transaction_id', 'scope_key', 'receipt_sequence', 'receipt_date', 'status', 'notes', 'is_late_entry', 'order_date', 'bap_date', 'bast_date', 'invoice_date', 'invoice_status'];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'is_late_entry' => 'boolean', 'order_date' => 'date', 'bap_date' => 'date', 'bast_date' => 'date', 'invoice_date' => 'date'];
    }

    /**
     * Document scope key for staged letters (PESANAN/BAP/BAST per tahap).
     * The single kuitansi stays on scope MAIN.
     */
    public function documentScopeKey(): string
    {
        return 'TAHAP:'.$this->receipt_sequence;
    }
}
