<?php

namespace Tests\Feature;

use Tests\TestCase;

class TopProgressTest extends TestCase
{
    public function test_authenticated_layout_renders_top_progress_marker(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('id="app-top-progress"', $layout);
        $this->assertSame(1, substr_count($layout, 'id="app-top-progress"'));
    }

    public function test_global_navbar_keeps_active_fiscal_year_selector_after_school_name(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));
        $provider = file_get_contents(app_path('Providers/AppServiceProvider.php'));

        $this->assertIsString($layout);
        $this->assertIsString($provider);
        $this->assertStringContainsString('id="header-fiscal-year"', $layout);
        $this->assertStringContainsString('@disabled($headerYears->isEmpty())', $layout);
        $this->assertStringContainsString('Tahun anggaran dan sumber dana aktif', $layout);
        $this->assertStringContainsString("->whereNotNull('fund_source_id')", $provider);
        $this->assertStringNotContainsString('arkas_rkas_items', $provider);
        $this->assertStringNotContainsString('arkas_mirror_rapbs', $provider);
    }

    public function test_top_progress_styles_follow_active_theme(): void
    {
        $css = file_get_contents(resource_path('css/top-progress.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#app-top-progress', $css);
        $this->assertStringContainsString('.is-active', $css);
        $this->assertStringContainsString('var(--theme-accent)', $css);
        $this->assertStringContainsString('prefers-reduced-motion', $css);
    }

    public function test_canonical_tables_use_square_material_surfaces_and_explicit_pagination_contracts(): void
    {
        $tableCss = file_get_contents(resource_path('css/table-standardization.css'));
        $nativeCss = file_get_contents(resource_path('css/token-native-components.css'));
        $tableComponent = file_get_contents(resource_path('views/components/ui/table.blade.php'));

        $this->assertIsString($tableCss);
        $this->assertIsString($nativeCss);
        $this->assertIsString($tableComponent);
        $this->assertStringContainsString('--ui-table-radius: 0;', $tableCss);
        $this->assertStringContainsString('border-radius: 0 !important;', $tableCss);
        $this->assertStringContainsString('border-radius: 0;', $nativeCss);
        $this->assertStringContainsString('data-pagination="{{ $pagination }}"', $tableComponent);
    }

    public function test_top_progress_hooks_cover_load_navigation_and_livewire(): void
    {
        $js = file_get_contents(resource_path('js/top-progress.js'));

        $this->assertIsString($js);
        $this->assertStringContainsString('beforeunload', $js);
        $this->assertStringContainsString('livewire:init', $js);
        $this->assertStringContainsString('livewire:navigate', $js);
        $this->assertStringContainsString('livewire:navigated', $js);
        $this->assertStringContainsString('morph.updated', $js);
        $this->assertStringContainsString('disableProgressBar', $js);
        $this->assertStringContainsString("addEventListener('load'", $js);
    }
}
