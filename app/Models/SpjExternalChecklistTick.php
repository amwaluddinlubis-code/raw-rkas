<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjExternalChecklistTick extends Model
{
    protected $connection = 'school';

    protected $fillable = ['spj_package_id', 'item_key', 'is_checked', 'checked_by', 'checked_at'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpjPackage::class, 'spj_package_id');
    }

    protected function casts(): array
    {
        return ['is_checked' => 'boolean', 'checked_at' => 'datetime'];
    }
}
