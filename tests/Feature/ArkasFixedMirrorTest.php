<?php

namespace Tests\Feature;

use App\Models\BackgroundOperation;
use App\Models\School;
use App\Models\User;
use App\Services\ArkasFixedMirrorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ArkasFixedMirrorTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_is_fixed_to_thirty_tables_without_ptk(): void
    {
        $registry = ArkasFixedMirrorService::registry();
        $sources = collect($registry)->pluck('source')->all();

        $this->assertCount(30, $registry);
        $this->assertNotContains('ptk', $sources);
        $this->assertCount(13, collect($registry)->where('connection', 'central')->all());
        $this->assertCount(17, collect($registry)->where('connection', 'school')->all());

        foreach ($registry as $entry) {
            $this->assertNotEmpty($entry['source']);
            $this->assertNotEmpty($entry['mirror']);
            $this->assertNotEmpty($entry['keys']);
            $this->assertNotEmpty($entry['label']);
        }
    }

    public function test_mirror_routes_are_registered_and_importer_is_unlinked_from_nav(): void
    {
        $this->assertTrue(collect(Route::getRoutes())->contains(
            fn ($route): bool => $route->getName() === 'arkas.mirror'
        ));
        $this->assertTrue(collect(Route::getRoutes())->contains(
            fn ($route): bool => $route->getName() === 'arkas.mirror.sync-refs'
        ));
        $this->assertTrue(collect(Route::getRoutes())->contains(
            fn ($route): bool => $route->getName() === 'arkas.mirror.sync-school'
        ));
        $this->assertFalse(collect(Route::getRoutes())->contains(
            fn ($route): bool => $route->getName() === 'arkas.mirror.sync'
        ));

        $nav = file_get_contents(base_path('resources/views/components/layouts/tailwind-app.blade.php'));
        $this->assertStringContainsString("route('arkas.mirror')", $nav);
        $this->assertStringNotContainsString("route('arkas.importer')", $nav);
    }

    public function test_mirror_status_endpoint_exposes_refs_and_school_progress(): void
    {
        $admin = User::factory()->create(['role' => 'ADMIN']);
        $school = School::query()->create(['npsn' => '00000001', 'name' => 'Sekolah Status']);

        BackgroundOperation::query()->create([
            'school_id' => $school->id,
            'type' => 'ARKAS_FIXED_MIRROR_REFS',
            'status' => 'RUNNING',
            'progress' => 42,
            'message' => 'Sinkronisasi tabel 5/13 (ref_kode) …',
        ]);

        $response = $this->actingAs($admin)
            ->withSession(['active_school_id' => $school->id])
            ->getJson(route('arkas.mirror.status'));

        $response->assertOk()
            ->assertJsonPath('refs.status', 'RUNNING')
            ->assertJsonPath('refs.progress', 42)
            ->assertJsonPath('school', null);
    }
}
