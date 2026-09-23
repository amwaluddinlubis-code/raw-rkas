<?php

namespace App\Services;

final class SpjPlaceholderValueFormatter
{
    public function amount(mixed $value): string
    {
        return (string) (int) round((float) $value);
    }

    public function number(float $value): string
    {
        if (abs($value - round($value)) < 0.00001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Kuantitas rincian barang: selalu 2 desimal seperti cast decimal:2
     * kolom lokal lama (mis. 2 → '2.00').
     */
    public function quantity(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
