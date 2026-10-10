<?php

namespace App\Livewire;

use App\Support\SpjDisplay;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DatabaseDiagnostics extends Component
{
    public bool $hasDatabase = false;

    public bool $exists = false;

    public bool $writable = false;

    public string $integrity = '—';

    public string $status = '—';

    public string $size = '—';

    public string $walSize = '—';

    public string $shmSize = '—';

    public string $path = '—';

    public string $connectionError = '';

    public array $tableCounts = [];

    public string $integrityRoute = '#';

    public function mount(?array $activeStatus = null): void
    {
        if (! $activeStatus) {
            return;
        }

        $this->hasDatabase = true;
        $this->exists = (bool) ($activeStatus['exists'] ?? false);
        $this->writable = (bool) ($activeStatus['isWritable'] ?? false);
        $this->integrity = (string) ($activeStatus['integrity'] ?? '—');
        $this->status = (string) ($activeStatus['status'] ?? '—');
        $this->size = SpjDisplay::bytes((int) ($activeStatus['size'] ?? 0));
        $this->walSize = SpjDisplay::bytes((int) ($activeStatus['walSize'] ?? 0));
        $this->shmSize = SpjDisplay::bytes((int) ($activeStatus['shmSize'] ?? 0));
        $this->path = (string) ($activeStatus['path'] ?? '—');
        $this->connectionError = (string) ($activeStatus['connectionError'] ?? '');
        $this->tableCounts = $activeStatus['tableCounts'] ?? [];
        $this->integrityRoute = route('database-manager.integrity', $activeStatus['school']->id);
    }

    public function render(): View
    {
        return view('livewire.database-diagnostics');
    }
}
