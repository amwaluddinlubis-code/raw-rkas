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
        $perPage = request()->integer($this->perPageQueryParam(), $default);

        return in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : $default;
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
        return [
            'bulan' => 'Bulan',
            'triwulan' => 'Triwulan',
            'semester' => 'Semester',
            'semua' => 'Semua',
        ];
    }

    public function setMode(string $modeOption): void
    {
        $this->mode = $modeOption;
        $this->periode = null;
        $this->resetPage($this->paginationPageName());
    }
}
