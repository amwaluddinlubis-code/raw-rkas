<?php

namespace App\Livewire;

use App\Models\FiscalYear;
use App\Services\ArkasMirrorBudgetService;
use App\Services\RkasPaguSimulationService;
use Livewire\Attributes\Computed;
use Livewire\Component;

class RkasBudgetSimulator extends Component
{
    public int $yearId;

    public int $fundSourceId;

    /** @var array<string,float> */
    public array $overrides = [];

    public function mount(): void
    {
        $this->yearId = (int) session('active_fiscal_year_id');
        $this->fundSourceId = (int) session('active_fund_source_id');
    }

    #[Computed]
    public function simulation(): array
    {
        $year = (int) (FiscalYear::query()->whereKey($this->yearId)->value('year') ?: now()->year);
        $revisions = app(ArkasMirrorBudgetService::class)->revisions($this->fundSourceId, $year);
        $latestApproved = collect($revisions)->where('status', 'approved')->last()['id'] ?? null;
        $snapshot = app(ArkasMirrorBudgetService::class)->snapshot($this->fundSourceId, $year, $latestApproved !== null ? [$latestApproved] : null);

        $knownCodes = [];
        foreach ($snapshot['rows'] as $row) {
            $code = (string) ($row['activity_code'] ?? '');
            if ($code !== '') {
                $knownCodes[str_replace('.', '_', $code)] = $code;
            }
        }
        $overrides = [];
        foreach ($this->overrides as $sanitized => $value) {
            if ($value === '' || $value === null || ! isset($knownCodes[$sanitized])) {
                continue;
            }
            $overrides[$knownCodes[$sanitized]] = (float) $value;
        }

        return app(RkasPaguSimulationService::class)->simulate($snapshot['rows'], $overrides);
    }

    public function render()
    {
        return view('livewire.rkas-budget-simulator', ['simulasi' => $this->simulation])
            ->layout('components.layouts.tailwind-app');
    }
}
