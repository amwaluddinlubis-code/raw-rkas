<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentTemplate extends Model
{
    protected $connection = 'school';

    protected $fillable = [
        'fiscal_year_id',
        'document_type',
        'name',
        'format',
        'file_path',
        'applicable_categories',
        'is_siplah',
        'is_active',
        'sort_order',
    ];

    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_siplah' => 'boolean',
            'applicable_categories' => 'array',
        ];
    }

    protected static function booted(): void
    {
        // Template baru selalu antre paling akhir pada tahunnya agar tidak
        // menyalip urutan pratinjau yang sudah diatur operator.
        static::creating(static function (self $template): void {
            if ((int) ($template->sort_order ?? 0) !== 0) {
                return;
            }

            $max = (int) self::query()
                ->where('fiscal_year_id', $template->fiscal_year_id)
                ->max('sort_order');

            $template->sort_order = $max + 1;
        });
    }
}
