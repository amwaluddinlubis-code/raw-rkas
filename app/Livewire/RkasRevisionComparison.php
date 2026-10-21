<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Services\ArkasMirrorBudgetService;
use App\Services\RkasRevisionComparisonService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

class RkasRevisionComparison extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public int $year = 0;

    public int $fundSourceId = 0;

    public string $fundName = '';

    public function mount(string $from = '', string $to = ''): void
    {
        $yearId = (int) session('active_fiscal_year_id');
        $this->fundSourceId = (int) session('active_fund_source_id');
        $this->year = (int) FiscalYear::query()->whereKey($yearId)->value('year');
        $this->fundName = (string) (DB::connection('school')->table('fund_sources')->where('id', $this->fundSourceId)->value('name') ?? '');

        $revisions = $this->revisions();
        $revisionIds = array_column($revisions, 'id');
        $defaultPair = array_slice($revisions, -2);

        $initialFrom = $from !== '' ? $from : ($this->from !== '' ? $this->from : ($defaultPair[0]['id'] ?? ''));
        $initialTo = $to !== '' ? $to : ($this->to !== '' ? $this->to : ($defaultPair[1]['id'] ?? ''));

        // If to is specified but from is omitted, pick predecessor
        if ($from === '' && $initialTo !== '') {
            $toIndex = array_search($initialTo, $revisionIds, true);
            if (is_int($toIndex) && $toIndex > 0) {
                $initialFrom = $revisionIds[$toIndex - 1];
            }
        }

        $this->from = $initialFrom;
        $this->to = $initialTo;
    }

    /**
     * @return array<int, array{id:string,seq:int,status:string,dateLabel:string,amount:float}>
     */
    #[Computed]
    public function revisions(): array
    {
        return app(ArkasMirrorBudgetService::class)->revisions($this->fundSourceId, $this->year);
    }

    /**
     * @return array{totals:array{from:float,to:float,delta:float},counts:array{added:int,removed:int,changed:int,unchanged:int},rows:array<int,array{status:string,activity_code:string,account_code:string,description:string,activity_name:string,from_amount:float,to_amount:float,amount_delta:float,from_volume:float,from_unit:string,to_volume:float,to_unit:string,periods:array<int,array{month:int,delta:float,volume_delta:float}>}>}|null
     */
    #[Computed]
    public function comparison(): ?array
    {
        if ($this->from === '' || $this->to === '' || $this->from === $this->to) {
            return null;
        }

        $revisionIds = array_column($this->revisions(), 'id');
        if (! in_array($this->from, $revisionIds, true) || ! in_array($this->to, $revisionIds, true)) {
            return null;
        }

        return app(RkasRevisionComparisonService::class)->compare($this->fundSourceId, $this->year, $this->from, $this->to);
    }

    public function render(): View
    {
        return view('livewire.rkas-revision-comparison', [
            'revisions' => $this->revisions(),
            'comparison' => $this->comparison(),
        ]);
    }
}
