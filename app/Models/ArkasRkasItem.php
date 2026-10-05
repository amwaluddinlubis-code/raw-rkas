<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArkasRkasItem extends Model
{
    protected $connection = 'school';

    protected $table = 'arkas_rkas_items';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'amount' => 'decimal:2'];
    }
}
