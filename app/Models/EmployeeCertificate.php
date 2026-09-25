<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeCertificate extends Model
{
    protected $connection = 'school';

    public const KIND_SK_GTT_PTT = 'SK_GTT_PTT';

    public const KIND_SK_PEMBAGIAN_TUGAS = 'SK_PEMBAGIAN_TUGAS';

    public const KIND_SK_PENETAPAN = 'SK_PENETAPAN';

    public const KIND_SK_PEMBINA = 'SK_PEMBINA';

    public const KIND_LAINNYA = 'LAINNYA';

    protected $fillable = ['employee_id', 'kind', 'number', 'issued_date', 'valid_until', 'notes', 'created_by'];

    /** @return array<string,string> */
    public static function kinds(): array
    {
        return [
            self::KIND_SK_GTT_PTT => 'SK GTT/PTT',
            self::KIND_SK_PEMBAGIAN_TUGAS => 'SK Pembagian Tugas',
            self::KIND_SK_PENETAPAN => 'SK Penetapan Narasumber',
            self::KIND_SK_PEMBINA => 'SK Pembina Ekstrakurikuler',
            self::KIND_LAINNYA => 'Lainnya',
        ];
    }

    public static function label(string $kind): string
    {
        return self::kinds()[$kind] ?? $kind;
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until !== null && Carbon::parse($this->valid_until)->isPast();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        if ($this->valid_until === null || $this->isExpired()) {
            return false;
        }

        return Carbon::parse($this->valid_until)->diffInDays(Carbon::now()->startOfDay(), true) <= $days;
    }

    protected function casts(): array
    {
        return ['issued_date' => 'date', 'valid_until' => 'date'];
    }
}
