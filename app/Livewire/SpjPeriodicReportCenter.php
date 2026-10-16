<?php

namespace App\Livewire;

use App\Services\SpjPeriodicReportRegistry;
use App\UseCases\Spj\SpjPeriodicReportUseCase;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class SpjPeriodicReportCenter extends Component
{
    #[Url(as: 'paket_laporan', except: 'bulan')]
    public string $scope = SpjPeriodicReportRegistry::SCOPE_MONTHLY;

    #[Url(as: 'periode_laporan', except: null)]
    public ?int $periode = null;

    public function mount(): void
    {
        $registry = $this->registry();

        if (! $registry->isScope($this->scope)) {
            $this->scope = SpjPeriodicReportRegistry::SCOPE_MONTHLY;
        }

        if (! $registry->periodRequired($this->scope)) {
            $this->periode = null;
        } elseif (! $registry->isValidPeriod($this->scope, $this->periode)) {
            $this->periode = null;
        }
    }

    public function setScope(string $scope): void
    {
        if (! $this->registry()->isScope($scope)) {
            return;
        }

        $this->scope = $scope;
        $this->periode = null;
    }

    public function updatedPeriode(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->periode = null;

            return;
        }

        $period = (int) $value;
        $this->periode = $this->registry()->isValidPeriod($this->scope, $period) ? $period : null;
    }

    /** @return list<array{value:int,label:string}> */
    public function periodOptions(): array
    {
        return $this->registry()->periodOptions($this->scope);
    }

    public function render(): View
    {
        $registry = $this->registry();
        $reports = app(SpjPeriodicReportUseCase::class);

        return view('livewire.spj-periodic-report-center', [
            'scopeLabels' => $registry->scopeLabels(),
            'reportDefinitions' => $registry->forScope($this->scope),
            'periodRequired' => $registry->periodRequired($this->scope),
            'summary' => $reports->summary($this->scope, $this->periode),
        ]);
    }

    private function registry(): SpjPeriodicReportRegistry
    {
        return app(SpjPeriodicReportRegistry::class);
    }
}
