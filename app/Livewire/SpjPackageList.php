<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjPackageList extends Component
{
    use WithPagination;

    #[Url(as: 'package_perPage', except: 15)]
    public int $perPage = 15;

    #[Url(as: 'package_q', except: '')]
    public string $search = '';

    #[Url(as: 'package_status', except: '')]
    public string $status = '';

    #[Url(as: 'package_category', except: '')]
    public string $category = '';

    #[Url(as: 'package_mode', except: 'semua')]
    public string $mode = 'semua';

    #[Url(as: 'package_period', except: '')]
    public ?int $periode = null;

    public function mount(): void
    {
        $perPage = request()->integer('package_perPage', 15);
        $this->perPage = in_array($perPage, [10, 15, 25, 50, 100], true) ? $perPage : 15;
    }

    public function updating($property): void
    {
        if (in_array($property, ['perPage', 'search', 'status', 'category', 'mode', 'periode'], true)) {
            $this->resetPage('package_page');
        }
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'status', 'category', 'mode', 'periode']);
        $this->resetPage('package_page');
    }

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
        $this->resetPage('package_page');
    }

    public function render(): View
    {
        return view('livewire.spj-package-list', [
            'packageList' => app(SpjWorkspaceUseCase::class)->packageListData($this->perPage, [
                'search' => $this->search,
                'status' => $this->status,
                'category' => $this->category,
                'mode' => $this->mode,
                'periode' => $this->periode,
            ]),
        ]);
    }
}
