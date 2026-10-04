<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class SpjPackageNavigationButtonsTest extends TestCase
{
    public function test_all_package_tabs_include_prev_next_navigation(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($index);
        // Rincian, Rincian Pajak, dan Penomoran memakai partial bersama.
        $this->assertSame(3, substr_count($index, "@include('spj.partials.package.package-navigation'"));
        $this->assertStringContainsString("@include('spj.partials.package.package-navigation', ['packageTab' => 'rincian'])", $index);
        $this->assertStringContainsString("@include('spj.partials.package.package-navigation', ['packageTab' => 'pajak'])", $index);
        $this->assertStringContainsString("@include('spj.partials.package.package-navigation', ['packageTab' => 'penomoran'])", $index);
        // Isian Manual memakai markup sendiri (tombol Simpan di tengah) dengan package_tab yang sama.
        $this->assertSame(2, substr_count($index, "'package_tab' => 'isian'"));
    }

    public function test_navigation_partial_preserves_package_tab(): void
    {
        $html = Blade::render(
            "@include('spj.partials.package.package-navigation', ['packageTab' => 'pajak', 'previousPackageId' => 11, 'nextPackageId' => 13])"
        );

        $this->assertStringContainsString('← Prev', $html);
        $this->assertStringContainsString('Next →', $html);
        $this->assertStringContainsString('package_id=11', $html);
        $this->assertStringContainsString('package_id=13', $html);
        $this->assertStringContainsString('package_tab=pajak', $html);
    }

    public function test_navigation_partial_disables_missing_neighbours(): void
    {
        $html = Blade::render(
            "@include('spj.partials.package.package-navigation', ['packageTab' => 'rincian'])"
        );

        $this->assertStringContainsString('← Prev', $html);
        $this->assertStringContainsString('Next →', $html);
        $this->assertStringNotContainsString('package_id=', $html);
        $this->assertStringContainsString('Tidak ada paket sebelumnya', $html);
        $this->assertStringContainsString('Tidak ada paket berikutnya', $html);
    }
}
