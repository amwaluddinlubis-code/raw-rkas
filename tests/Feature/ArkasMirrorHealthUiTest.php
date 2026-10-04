<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ArkasMirrorHealthUiTest extends TestCase
{
    public function test_health_route_is_registered_with_admin_guards(): void
    {
        $this->assertTrue(Route::has('arkas.mirror.health-repair'));

        $route = Route::getRoutes()->getByName('arkas.mirror.health-repair');
        $this->assertSame(['POST'], $route->methods());
        $this->assertContains('active-school', $route->gatherMiddleware());
        $this->assertContains('throttle:3,1', $route->gatherMiddleware());
    }

    public function test_mirror_page_uses_canonical_primitives_for_health_section(): void
    {
        $blade = (string) file_get_contents(resource_path('views/arkas/mirror.blade.php'));

        $this->assertStringContainsString('Kesehatan Mirror Kas', $blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('<x-ui.button', $blade);
        $this->assertStringContainsString("route('arkas.mirror.health-repair')", $blade);
        $this->assertStringContainsString('data-confirm=', $blade);
        $this->assertStringContainsString('name="health_year"', $blade);
        $this->assertStringContainsString('name="health_category"', $blade);
        $this->assertStringContainsString('data-auto-submit="true"', $blade);
        $this->assertStringContainsString('var(--ui-', $blade);
        $this->assertStringNotContainsString('text-slate-', $blade);
        $this->assertStringNotContainsString('bg-white', $blade);
    }
}
