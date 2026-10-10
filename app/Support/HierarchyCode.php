<?php

namespace App\Support;

/**
 * Pembanding kode hierarkis bertingkat (mis. kode rekening 5.1.02.01).
 *
 * Setiap tingkat dibandingkan secara natural: numerik bila keduanya angka,
 * natural case-insensitive bila campuran. Kode yang lebih pendek dianggap
 * lebih kecil bila semua tingkat awalnya sama.
 */
final class HierarchyCode
{
    public static function compare(string $left, string $right): int
    {
        $leftParts = array_values(array_filter(explode('.', trim($left, '.')), static fn (string $part): bool => $part !== ''));
        $rightParts = array_values(array_filter(explode('.', trim($right, '.')), static fn (string $part): bool => $part !== ''));

        foreach (range(0, max(count($leftParts), count($rightParts)) - 1) as $index) {
            $leftPart = $leftParts[$index] ?? '';
            $rightPart = $rightParts[$index] ?? '';
            if ($leftPart === $rightPart) {
                continue;
            }
            if ($leftPart === '') {
                return -1;
            }
            if ($rightPart === '') {
                return 1;
            }
            if (ctype_digit($leftPart) && ctype_digit($rightPart)) {
                return (int) $leftPart <=> (int) $rightPart;
            }

            return strnatcasecmp($leftPart, $rightPart);
        }

        return 0;
    }
}
