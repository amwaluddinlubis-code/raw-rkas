<?php

namespace App\Livewire;

use App\Services\SchoolDatabaseManager;
use App\Services\SchoolDatabaseTableGuide;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

class DatabaseTableExplorer extends Component
{
    use WithPagination;

    public array $tables = [];

    public array $centralTables = [];

    public string $scope = 'school';

    public string $search = '';

    public string $sort = 'name';

    public string $direction = 'asc';

    public int $perPage = 15;

    public ?string $openTable = null;

    public ?array $detail = null;

    public function mount(array $tables = [], ?string $initialTable = null): void
    {
        $this->tables = $tables;
        $this->centralTables = $this->loadCentralTables();
        if ($initialTable !== null) {
            $this->openTable($initialTable);
        }
    }

    public function updatedScope(): void
    {
        if (! in_array($this->scope, ['school', 'central'], true)) {
            $this->scope = 'school';
        }
        $this->closeTable();
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, ['name', 'rows'], true)) {
            return;
        }

        if ($this->sort === $field) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function openTable(string $name): void
    {
        $scope = $this->scope === 'central' ? 'central' : 'school';
        $list = $scope === 'central' ? $this->centralTables : $this->tables;
        if (! collect($list)->contains(fn (array $table): bool => $table['name'] === $name)) {
            return;
        }

        $this->openTable = $name;
        $active = app(SchoolDatabaseManager::class)->activeInfo();
        if (! $active['school']) {
            return;
        }

        try {
            $manager = app(SchoolDatabaseManager::class);
            $schema = $manager->tableSchema($active['school'], $name, $scope);
            $data = $manager->tableData($active['school'], $name, 10, $scope);
            $this->detail = [
                'name' => $name,
                'scope' => $scope,
                'meta' => app(SchoolDatabaseTableGuide::class)->describe($name),
                'total' => $data->total(),
                'columns' => collect($schema)->map(fn (object $column): array => [
                    'name' => $column->name,
                    'type' => $column->type,
                    'required' => (bool) $column->notnull,
                    'pk' => (bool) $column->pk,
                ])->values()->all(),
                'rows' => $data->getCollection()->map(fn (object $row): array => (array) $row)->values()->all(),
            ];
        } catch (\Throwable) {
            $this->detail = null;
        }
    }

    public function closeTable(): void
    {
        $this->openTable = null;
        $this->detail = null;
    }

    public function render(): View
    {
        $tables = $this->scope === 'central' ? $this->centralTables : $this->tables;
        $filtered = collect($tables)
            ->filter(function (array $table): bool {
                $needle = strtolower(trim($this->search));
                if ($needle === '') {
                    return true;
                }

                return str_contains(strtolower(implode(' ', [
                    $table['name'] ?? '',
                    $table['label'] ?? '',
                    $table['group'] ?? '',
                    $table['blurb'] ?? '',
                ])), $needle);
            })
            ->sort(function (array $left, array $right): int {
                $a = $this->sort === 'rows' ? (int) ($left['count'] ?? 0) : strtolower((string) ($left['label'] ?? $left['name'] ?? ''));
                $b = $this->sort === 'rows' ? (int) ($right['count'] ?? 0) : strtolower((string) ($right['label'] ?? $right['name'] ?? ''));

                return ($a <=> $b) * ($this->direction === 'asc' ? 1 : -1);
            });

        $page = $filtered->forPage($this->getPage(), $this->perPage);

        return view('livewire.database-table-explorer', [
            'pageTables' => $page,
            'total' => $filtered->count(),
            'pages' => max(1, (int) ceil($filtered->count() / $this->perPage)),
            'schoolCount' => count($this->tables),
            'centralCount' => count($this->centralTables),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadCentralTables(): array
    {
        $active = app(SchoolDatabaseManager::class)->activeInfo();
        if (! $active['school']) {
            return [];
        }

        try {
            $manager = app(SchoolDatabaseManager::class);
            $guide = app(SchoolDatabaseTableGuide::class);

            return collect($manager->listTables($active['school'], 'central'))
                ->map(function (array $row) use ($manager, $active, $guide): array {
                    try {
                        $row['columns'] = count($manager->tableSchema($active['school'], (string) $row['name'], 'central'));
                    } catch (\Throwable) {
                        $row['columns'] = null;
                    }

                    return $row + $guide->describe((string) ($row['name'] ?? ''));
                })
                ->sortBy([fn ($row) => array_search($row['group'], $guide->groups()), fn ($row) => $row['label']])
                ->values()->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
