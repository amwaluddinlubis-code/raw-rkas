<?php

namespace App\Livewire;

/**
 * Filter daftar SPJ yang dipakai ulang tab Paket dan Atribut.
 *
 * Properti filter ($search, $status, $category, $mode, $periode, $perPage)
 * tetap dideklarasikan di komponen masing-masing karena nama query-string
 * URL berbeda (package_* vs attribute_*). Trait ini hanya menyatukan
 * perilaku: normalisasi perPage, reset paginasi, mode periode, dan
 * clearFilters agar kedua tab tidak divergen.
 */
trait HasSpjListFilter
{
    abstract protected function paginationPageName(): string;

    abstract protected function perPageQueryParam(): string;

    protected function resolvePerPage(int $default = 15): int
    {
        return (int) self::normalizePerPageValue(
            request()->input($this->perPageQueryParam(), $default),
            [10, 15, 25, 50, 100],
            false,
            $default
        );
    }

    /**
     * Normalisasi perPage mentah dipakai ulang semua filter Livewire.
     * allowAll=true mempertahankan sentinel 'all' (10000 saat resolved).
     *
     * @param  array<int, int>  $allowed
     */
    public static function normalizePerPageValue(mixed $raw, array $allowed = [10, 15, 25, 50, 100], bool $allowAll = false, int $default = 15): int|string
    {
        if ($allowAll && $raw === 'all') {
            return 'all';
        }

        $perPage = (int) $raw;

        return in_array($perPage, $allowed, true) ? $perPage : $default;
    }

    /**
     * @param  array<int, int>  $allowed
     */
    public static function resolvedPerPageValue(int|string $perPage, array $allowed = [15, 25, 50, 100, 10000], int $default = 15): int
    {
        $resolved = $perPage === 'all' ? 10000 : (int) $perPage;

        return in_array($resolved, $allowed, true) ? $resolved : $default;
    }

    public function updating($property): void
    {
        if (in_array($property, ['perPage', 'search', 'status', 'category', 'mode', 'periode'], true)) {
            $this->resetPage($this->paginationPageName());
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'mode', 'periode']);
        $this->resetPage($this->paginationPageName());
    }

    /**
     * @return array<string, string>
     */
    public function modes(): array
    {
        return SpjDisplay::periodModes();
    }

    public function setMode(string $modeOption): void
    {
        $this->mode = $modeOption;
        $this->periode = null;
        $this->resetPage($this->paginationPageName());
    }
}
