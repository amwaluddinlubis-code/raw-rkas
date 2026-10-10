<?php

namespace App\Livewire;

use App\Support\SpjDisplay;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DatabaseOverview extends Component
{
    public bool $available = false;

    public string $integrity = '—';

    public string $databaseSize = '—';

    public string $totalStorage = '—';

    public string $lastMigrated = '—';

    public array $tableCounts = [];

    public string $integrityRoute = '#';

    public function mount(?array $activeStatus = null): void
    {
        if (! $activeStatus) {
            return;
        }

        $this->available = true;
        $this->integrity = (string) ($activeStatus['integrity'] ?? '—');
        $this->databaseSize = SpjDisplay::bytes((int) ($activeStatus['size'] ?? 0));
        $this->totalStorage = SpjDisplay::bytes((int) ($activeStatus['totalSize'] ?? 0));
        $this->lastMigrated = ! empty($activeStatus['lastMigrated']) ? $activeStatus['lastMigrated']->diffForHumans() : '—';
        $this->tableCounts = $activeStatus['tableCounts'] ?? [];
        $this->integrityRoute = route('database-manager.integrity', $activeStatus['school']->id);
    }

    public function render(): View
    {
        return view('livewire.database-overview');
    }
}
