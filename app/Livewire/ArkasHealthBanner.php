<?php

namespace App\Livewire;

use App\Services\ArkasMirrorFreshnessService;
use App\Services\ArkasMirrorIntegrityService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ArkasHealthBanner extends Component
{
    public function render(): View
    {
        $yearId = (int) session('active_fiscal_year_id');
        $fundSourceId = (int) session('active_fund_source_id');

        return view('livewire.arkas-health-banner', [
            'syncFreshness' => app(ArkasMirrorFreshnessService::class)->summarize((int) session('active_school_id'), $yearId),
            'integrity' => app(ArkasMirrorIntegrityService::class)->summarize($yearId, $fundSourceId),
        ]);
    }
}
