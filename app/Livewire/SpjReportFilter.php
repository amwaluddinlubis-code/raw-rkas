<?php

namespace App\Livewire;

use App\Support\SpjDisplay;
use App\UseCases\Spj\SpjReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjReportFilter extends Component
{
    use WithPagination;

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(except: 15)]
    public int|string $perPage = 15;

    #[Url(except: '')]
    public string $search = '';

    public function mount(): void
    {
        [$mode, $periode] = SpjReportUseCase::resolveModePeriode(request()->all());
        $this->mode = in_array($mode, $this->allowedModes(), true) ? $mode : 'semua';
        $this->periode = $periode;
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
        $this->search = trim((string) request('search', ''));
    }

    public function updating($property): void
    {
        if (in_array($property, ['periode', 'perPage', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function setMode(string $mode): void
    {
        if (! in_array($mode, $this->allowedModes(), true)) {
            return;
        }

        $this->mode = $mode;
        $this->periode = null;
        $this->resetPage();
    }

    public function render(): View
    {
        [$packages, $summary] = app(SpjReportUseCase::class)
            ->reportData($this->mode, $this->periode, $this->resolvedPerPage(), 15, $this->search);

        return view('livewire.spj-report-filter', [
            'packages' => $packages,
            'summary' => $summary,
        ]);
    }

    private function resolvedPerPage(): int
    {
        return HasSpjListFilter::resolvedPerPageValue($this->perPage, [10, 15, 25, 50, 100, 10000]);
    }

    private function normalizePerPage(mixed $raw): int|string
    {
        return HasSpjListFilter::normalizePerPageValue($raw, [10, 15, 25, 50, 100], true);
    }

    /** @return list<string> */
    public function allowedModes(): array
    {
        return SpjDisplay::periodModeKeys();
    }

    /** @return list<array{0: string, 1: string}> */
    public function modes(): array
    {
        return [
            ['semua', 'Semua'],
            ['semester', 'Semester'],
            ['triwulan', 'Triwulan'],
            ['bulan', 'Bulan'],
        ];
    }
}
