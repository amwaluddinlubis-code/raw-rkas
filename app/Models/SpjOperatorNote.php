<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpjOperatorNote extends Model
{
    protected $connection = 'school';

    protected $fillable = ['spj_package_id', 'body', 'created_by'];

    public function package(): BelongsTo
    {
        return $this->belongsTo(SpjPackage::class, 'spj_package_id');
    }
}
