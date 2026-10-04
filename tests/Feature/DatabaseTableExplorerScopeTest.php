<?php

namespace Tests\Feature;

use App\Livewire\DatabaseTableExplorer;
use App\Models\School;
use App\Services\SchoolDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DatabaseTableExplorerScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_central_listing_excludes_credential_tables(): void
    {
        $manager = app(SchoolDatabaseManager::class);
        $school = new School(['npsn' => '00000000', 'name' => 'Sekolah Uji']);

        $names = collect($manager->listTables($school, 'central'))->pluck('name')->all();

        $this->assertContains('schools', $names);
        foreach (SchoolDatabaseManager::CENTRAL_DENIED_TABLES as $denied) {
            $this->assertNotContains($denied, $names);
        }
    }

    public function test_central_schema_and_data_reject_denied_tables(): void
    {
        $manager = app(SchoolDatabaseManager::class);
        $school = new School(['npsn' => '00000000', 'name' => 'Sekolah Uji']);

        $this->expectException(\InvalidArgumentException::class);
        $manager->tableSchema($school, 'users', 'central');
    }

    public function test_explorer_switches_between_school_and_central(): void
    {
        Livewire::test(DatabaseTableExplorer::class, ['tables' => [
            ['name' => 'transactions', 'count' => 3, 'label' => 'Transaksi', 'group' => 'Transaksi & BKU', 'blurb' => 'x'],
        ]])
            ->assertSee('Sekolah Aktif')
            ->assertSee('Database Pusat')
            ->set('scope', 'central')
            ->assertSee('Database Pusat')
            ->set('scope', 'salah')
            ->assertSee('Sekolah Aktif');
    }

    public function test_explorer_marks_up_scope_buttons_and_tokens(): void
    {
        $blade = (string) file_get_contents(resource_path('views/livewire/database-table-explorer.blade.php'));

        $this->assertStringContainsString("\$set('scope', 'school')", $blade);
        $this->assertStringContainsString("\$set('scope', 'central')", $blade);
        $this->assertStringContainsString('disembunyikan', $blade);
        $this->assertStringNotContainsString('users', $blade);
    }
}
