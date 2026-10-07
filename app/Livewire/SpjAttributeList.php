<?php

namespace App\Livewire;

use App\UseCases\Spj\SpjWorkspaceUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SpjAttributeList extends Component
{
    use HasSpjListFilter;
    use WithPagination;

    #[Url(as: 'attribute_perPage', except: 15)]
    public int $perPage = 15;

    #[Url(as: 'attribute_q', except: '')]
    public string $search = '';

    #[Url(as: 'attribute_status', except: '')]
    public string $status = '';

    #[Url(as: 'attribute_category', except: '')]
    public string $category = '';

    #[Url(as: 'attribute_mode', except: 'semua')]
    public string $mode = 'semua';

    #[Url(as: 'attribute_period', except: '')]
    public ?int $periode = null;

    public function mount(): void
    {
        $this->perPage = $this->resolvePerPage();
    }

    protected function paginationPageName(): string
    {
        return 'attribute_page';
    }

    protected function perPageQueryParam(): string
    {
        return 'attribute_perPage';
    }

    public function render(): View
    {
        return view('livewire.spj-attribute-list', [
            'attributeList' => app(SpjWorkspaceUseCase::class)->attributeListData($this->perPage, [
                'search' => $this->search,
                'status' => $this->status,
                'category' => $this->category,
                'mode' => $this->mode,
                'periode' => $this->periode,
            ]),
        ]);
    }
}
