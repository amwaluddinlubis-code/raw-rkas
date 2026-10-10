<?php

namespace App\Livewire;

use App\Services\SchoolDatabaseManager;
use App\Support\SpjDisplay;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class DatabaseStatusSummary extends Component
{
    public string $schoolName = 'Belum dipilih';

    public string $npsn = '—';

    public string $sessionId = '—';

    public string $health = 'BELUM TERSEDIA';

    public string $healthHint = 'Aktifkan sekolah untuk health check';

    public string $storage = '—';

    public string $databaseSize = '—';

    public string $walSize = '—';

    public int $tableCount = 0;

    public string $tableRows = '0';

    public function mount(array $active, ?array $activeStatus = null): void
    {
        $school = $active['school'] ?? null;
        if ($school) {
            $this->schoolName = $school->name;
            $this->npsn = (string) ($school->npsn ?? '—');
            $this->sessionId = (string) ($active['schoolId'] ?? '—');
            $health = app(SchoolDatabaseManager::class)->health($school);
            $this->health = strtoupper((string) ($health['level'] ?? 'unknown'));
            $this->healthHint = empty($health['issues']) ? 'Tidak ada masalah terdeteksi' : implode(' · ', $health['issues']);
        }

        if ($activeStatus) {
            $this->tableCount = count($activeStatus['tableCounts'] ?? []);
            $this->storage = SpjDisplay::bytes((int) ($activeStatus['totalSize'] ?? 0));
            $this->databaseSize = SpjDisplay::bytes((int) ($activeStatus['size'] ?? 0));
            $this->walSize = SpjDisplay::bytes((int) ($activeStatus['walSize'] ?? 0));
            $this->tableRows = number_format((int) collect($activeStatus['tableCounts'] ?? [])->sum(), 0, ',', '.');
        }
    }

    public function render(): View
    {
        return view('livewire.database-status-summary');
    }
}
