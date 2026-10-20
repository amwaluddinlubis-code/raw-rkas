<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarNavigationPlacementTest extends TestCase
{
    public function test_reference_group_expands_for_all_reference_routes(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "'employees.*', 'students.*', 'references.*'",
            $layout,
        );
    }

    public function test_data_group_marks_mirror_routes_as_active(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "'synced-data.*', 'arkas.settings*', 'arkas.mirror*', 'dapodik.*'",
            $layout,
        );
    }

    public function test_spj_children_use_distinct_active_routes(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));
        $reports = file_get_contents(resource_path('views/components/layouts/partials/spj-report-navigation.blade.php'));

        $this->assertIsString($layout);
        $this->assertIsString($reports);

        $this->assertStringContainsString(
            "request()->routeIs('spj.index', 'spj.checklist') && request('tab', 'persiapan') !== 'laporan'",
            $layout,
        );
        $this->assertStringContainsString(
            "request()->routeIs('spj.numbering-workflow', 'spj.numbering-correction')",
            $layout,
        );
        $this->assertStringContainsString(
            "request()->routeIs('spj.honor-payments.*', 'spj.service-recipients.*')",
            $reports,
        );
    }

    public function test_school_profile_is_operator_accessible_and_letterhead_aware(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString(
            "request()->routeIs('schools.settings', 'schools.profile.*', 'schools.letterhead')",
            $layout,
        );
        $this->assertStringNotContainsString(
            "@if (auth()->user()->isAdministrator())\n                            <a class=\"app-nav {{ request()->routeIs('schools.settings'",
            $layout,
        );
    }

    public function test_breadcrumb_uses_sidebar_group_names_and_covers_all_nav_routes(): void
    {
        $breadcrumb = file_get_contents(resource_path('views/components/global-breadcrumb.blade.php'));

        $this->assertIsString($breadcrumb);

        foreach (["\$group = 'Referensi';", "\$group = 'SPJ & Laporan';", "\$group = 'Data & Integrasi';"] as $group) {
            $this->assertStringContainsString($group, $breadcrumb);
        }

        foreach ([
            'asisten.*',
            'rkas-planning.*',
            'references.*',
            'spj.quarter-recap',
            'spj.numbering-correction',
            'arkas.mirror*',
            'schools.select',
        ] as $route) {
            $this->assertStringContainsString($route, $breadcrumb);
        }
    }
}
