<?php

namespace App\Livewire;

use App\Services\ReferenceLookupService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Read-only directory for ARKAS reference accounts, price benchmarks, and activity hierarchy.
 *
 * All underlying tables in central mirror are read-only.
 */
class ReferenceDirectory extends Component
{
    use WithPagination;

    #[Url(except: ReferenceLookupService::TAB_ACCOUNTS)]
    public string $tab = ReferenceLookupService::TAB_ACCOUNTS;

    #[Url(except: '')]
    public string $tahun = '';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 15)]
    public int $perPage = 15;

    #[Url(except: '')]
    public string $program = '';

    #[Url(except: '')]
    public string $sub = '';

    #[Url(except: '')]
    public string $kegiatan = '';

    public function mount(ReferenceLookupService $references): void
    {
        $years = $references->availableYears();
        $requestedYear = (string) request('tahun', '');

        if ($requestedYear !== '' && in_array($requestedYear, $years, true)) {
            $this->tahun = $requestedYear;
        } else {
            $this->tahun = $years[0] ?? '';
        }

        $requestedTab = (string) request('tab', '');
        if (in_array($requestedTab, [ReferenceLookupService::TAB_ACCOUNTS, ReferenceLookupService::TAB_PRICES, ReferenceLookupService::TAB_ACTIVITIES], true)) {
            $this->tab = $requestedTab;
        }

        $this->q = trim((string) request('q', ''));
        $requestedPerPage = (int) request('perPage', 15);
        if (in_array($requestedPerPage, [15, 25, 50, 100], true)) {
            $this->perPage = $requestedPerPage;
        }

        $this->program = trim((string) request('program', ''));
        $this->sub = trim((string) request('sub', ''));
        $this->kegiatan = trim((string) request('kegiatan', ''));
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, [ReferenceLookupService::TAB_ACCOUNTS, ReferenceLookupService::TAB_PRICES, ReferenceLookupService::TAB_ACTIVITIES], true)) {
            $this->tab = $tab;
            $this->resetPage();
            if ($this->tab !== ReferenceLookupService::TAB_ACTIVITIES) {
                $this->reset(['program', 'sub', 'kegiatan']);
            }
        }
    }

    public function updatedTahun(): void
    {
        $this->reset(['program', 'sub', 'kegiatan']);
        $this->resetPage();
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function updatedProgram(): void
    {
        $this->reset(['sub', 'kegiatan']);
        $this->resetPage();
    }

    public function updatedSub(): void
    {
        $this->reset(['kegiatan']);
        $this->resetPage();
    }

    public function updatedKegiatan(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['q', 'program', 'sub', 'kegiatan']);
        $this->resetPage();
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function years(): array
    {
        return app(ReferenceLookupService::class)->availableYears();
    }

    #[Computed]
    public function rows(): LengthAwarePaginator
    {
        $service = app(ReferenceLookupService::class);
        $perPage = in_array($this->perPage, [15, 25, 50, 100], true) ? $this->perPage : 15;

        return match ($this->tab) {
            ReferenceLookupService::TAB_PRICES => $service->paginatePriceReferences($this->tahun, $this->q, $perPage),
            ReferenceLookupService::TAB_ACTIVITIES => $service->paginateActivities($this->tahun, $this->q, $perPage, $this->program, $this->sub, $this->kegiatan),
            default => $service->paginateAccounts($this->tahun, $this->q, $perPage),
        };
    }

    /**
     * @return array{0:array<int,array{kode:string,nama:string}>,1:array<int,array{kode:string,nama:string}>,2:array<int,array{kode:string,nama:string}>}
     */
    #[Computed]
    public function activityFilterOptions(): array
    {
        if ($this->tab !== ReferenceLookupService::TAB_ACTIVITIES) {
            return [[], [], []];
        }

        return app(ReferenceLookupService::class)->activityOptions($this->tahun, $this->program, $this->sub);
    }

    public function render(): View
    {
        [$programOptions, $subOptions, $kegiatanOptions] = $this->activityFilterOptions();

        return view('livewire.reference-directory', [
            'isPriceTab' => $this->tab === ReferenceLookupService::TAB_PRICES,
            'isActivityTab' => $this->tab === ReferenceLookupService::TAB_ACTIVITIES,
            'years' => $this->years(),
            'rows' => $this->rows(),
            'programOptions' => $programOptions,
            'subOptions' => $subOptions,
            'kegiatanOptions' => $kegiatanOptions,
        ]);
    }
}
