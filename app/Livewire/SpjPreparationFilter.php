<?php

namespace App\Livewire;

use App\Support\SpjDisplay;
use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjPreparationFilter extends Component
{
    use WithPagination;

    #[Url(except: 'semua')]
    public string $mode = 'semua';

    #[Url(except: null)]
    public ?int $periode = null;

    #[Url(except: '')]
    public string $spj_category = '';

    #[Url(except: 'all')]
    public string $state = 'all';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 15)]
    public int|string $perPage = 15;

    public function mount(): void
    {
        $mode = (string) request('mode', 'semua');
        $periode = request()->integer('periode') ?: null;
        if (! in_array($mode, array_keys($this->modes()), true)) {
            $mode = 'semua';
        }

        // Kompatibilitas URL lama ?month= / ?quarter=.
        if ($mode === 'semua' && $periode === null) {
            $month = request()->integer('month') ?: null;
            $quarter = request()->integer('quarter') ?: null;
            if ($month) {
                $mode = 'bulan';
                $periode = $month;
            } elseif ($quarter) {
                $mode = 'triwulan';
                $periode = $quarter;
            }
        }

        $this->mode = $mode;
        $this->periode = $periode;
        $this->spj_category = trim((string) request('spj_category', ''));
        $state = (string) request('state', 'all');
        $this->state = in_array($state, $this->allowedStates(), true) ? $state : 'all';
        $this->search = trim((string) request('search', ''));
        $this->perPage = $this->normalizePerPage(request('perPage', 15));
    }

    public function updating($property): void
    {
        if (in_array($property, ['mode', 'periode', 'spj_category', 'state', 'perPage', 'search'], true)) {
            $this->resetPage();
        }
    }

    public function modes(): array
    {
        return SpjDisplay::periodModes();
    }

    public function setMode(string $modeOption): void
    {
        $this->mode = $modeOption;
        $this->periode = null;
        $this->resetPage();
    }

    public function setQueueState(string $state): void
    {
        if (! in_array($state, $this->allowedStates(), true)) {
            return;
        }

        $this->state = $state;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['mode', 'periode', 'spj_category', 'state', 'perPage', 'search']);
        $this->resetPage();
    }

    public function render(): View
    {
        $data = app(SpjWorkspaceUseCase::class)->preparationData($this->filters(), $this->resolvedPerPage());

        return view('livewire.spj-preparation-filter', [
            'transactions' => $data['transactions'],
            'workQueueCounts' => $data['workQueueCounts'],
            'spjTypes' => $data['spjTypes'],
        ]);
    }

    /** @return array{mode: string, periode: int|null, spj_category: string|null, state: string, search: string|null} */
    private function filters(): array
    {
        return [
            'mode' => $this->mode,
            'periode' => $this->periode,
            'spj_category' => $this->spj_category === '' ? null : $this->spj_category,
            'state' => $this->state,
            'search' => $this->search === '' ? null : $this->search,
        ];
    }

    private function resolvedPerPage(): int
    {
        return HasSpjListFilter::resolvedPerPageValue($this->perPage, [15, 25, 50, 100, 10000]);
    }

    private function normalizePerPage(mixed $raw): int|string
    {
        return HasSpjListFilter::normalizePerPageValue($raw, [15, 25, 50, 100], true);
    }

    /** @return list<string> */
    private function allowedStates(): array
    {
        return ['all', 'attention', 'needs_details', 'unprepared', 'draft', 'ready', 'numbered'];
    }
}
