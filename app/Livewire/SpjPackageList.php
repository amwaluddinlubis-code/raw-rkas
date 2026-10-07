<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjPackageList extends Component
{
    use HasSpjListFilter;
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
        $this->perPage = $this->resolvePerPage();
    }

    protected function paginationPageName(): string
    {
        return 'package_page';
    }

    protected function perPageQueryParam(): string
    {
        return 'package_perPage';
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
