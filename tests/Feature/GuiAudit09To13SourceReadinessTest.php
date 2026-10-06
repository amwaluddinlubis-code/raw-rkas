<?php

namespace Tests\Feature;

use Tests\TestCase;

class GuiAudit09To13SourceReadinessTest extends TestCase
{
    public function test_spj_main_tabs_use_canonical_icons_instead_of_emoji_labels(): void
    {
        $tabs = file_get_contents(resource_path('views/components/tabs.blade.php'));
        $spjTabs = file_get_contents(resource_path('views/spj/partials/main-tabs.blade.php'));

        $this->assertIsString($tabs);
        $this->assertIsString($spjTabs);
        $this->assertStringContainsString('<x-ui.icon', $tabs);
        $this->assertStringContainsString("'icon' => 'archive'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'document'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'report'", $spjTabs);
        $this->assertStringContainsString("'icon' => 'warning'", $spjTabs);
        $this->assertStringNotContainsString('📦', $spjTabs);
        $this->assertStringNotContainsString('📄', $spjTabs);
        $this->assertStringNotContainsString('📊', $spjTabs);
        $this->assertStringNotContainsString('⚠️', $spjTabs);
    }

    public function test_database_reset_page_uses_shared_actions_and_theme_tokens(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/reset.blade.php'));
        $component = file_get_contents(resource_path('views/livewire/database-reset-form.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($component);
        $this->assertStringContainsString('<livewire:database-reset-form', $blade);
        $this->assertStringContainsString('<x-ui.button type="submit" variant="danger"', $component);
        $this->assertStringContainsString('<x-ui.danger-zone', $component);
        $this->assertStringContainsString('var(--ui-fg-muted)', $component);
        $this->assertStringContainsString('var(--ui-surface-base)', $component);
        $this->assertStringNotContainsString('text-slate-', $component);
        $this->assertStringNotContainsString('text-indigo-', $component);
        $this->assertStringNotContainsString('bg-slate-', $component);
    }

    public function test_database_table_explorer_has_one_standard_pagination_control(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/tables.blade.php'));
        $component = file_get_contents(resource_path('views/livewire/database-table-explorer.blade.php'));
        $css = file_get_contents(resource_path('css/ui-generalization.css'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($component);
        $this->assertIsString($css);
        $this->assertStringContainsString("@include('database-manager.partials.tables')", $blade);
        $this->assertStringContainsString('<livewire:database-table-explorer', $partial);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="search"', $component);
        $this->assertStringContainsString('wire:click="gotoPage(', $component);
        $this->assertStringNotContainsString('wire:click="setPage(', $component);
        $this->assertStringContainsString('.ui-pagination-control:first-child', $css);
        $this->assertStringContainsString('.ui-pagination-control:last-child', $css);
    }

    public function test_database_manager_summary_and_tabs_are_livewire_consumers(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $tabs = file_get_contents(resource_path('views/livewire/database-manager-tabs.blade.php'));
        $summary = file_get_contents(resource_path('views/livewire/database-status-summary.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($tabs);
        $this->assertIsString($summary);
        $this->assertStringContainsString('<livewire:database-status-summary', $blade);
        $this->assertStringContainsString('<livewire:database-manager-tabs', $blade);
        $this->assertStringContainsString('wire:click="selectTab(', $tabs);
        $this->assertStringContainsString('data-livewire-summary="true"', $summary);
    }

    public function test_database_diagnostics_is_a_livewire_read_only_panel(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/diagnostics.blade.php'));
        $component = file_get_contents(resource_path('views/livewire/database-diagnostics.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($component);
        $this->assertStringContainsString("@include('database-manager.partials.diagnostics')", $blade);
        $this->assertStringContainsString('<livewire:database-diagnostics', $partial);
        $this->assertStringContainsString('data-livewire-diagnostics="true"', $component);
        $this->assertStringContainsString('Jalankan integrity check', $component);
        $this->assertStringContainsString('tableCounts', $component);
    }

    public function test_database_school_maintenance_and_reset_use_livewire_consumers(): void
    {
        $index = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $schoolPartial = file_get_contents(resource_path('views/database-manager/partials/school-list.blade.php'));
        $maintenancePartial = file_get_contents(resource_path('views/database-manager/partials/maintenance.blade.php'));
        $schoolList = file_get_contents(resource_path('views/livewire/database-school-list.blade.php'));
        $maintenance = file_get_contents(resource_path('views/livewire/database-maintenance.blade.php'));
        $reset = file_get_contents(resource_path('views/livewire/database-reset-form.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($schoolPartial);
        $this->assertIsString($maintenancePartial);
        $this->assertIsString($schoolList);
        $this->assertIsString($maintenance);
        $this->assertIsString($reset);
        $this->assertStringContainsString("@include('database-manager.partials.school-list')", $index);
        $this->assertStringContainsString("@include('database-manager.partials.maintenance')", $index);
        $this->assertStringContainsString('<livewire:database-school-list', $schoolPartial);
        $this->assertStringContainsString('<livewire:database-maintenance', $maintenancePartial);
        $this->assertStringContainsString('wire:model.live.debounce.250ms="search"', $schoolList);
        $this->assertStringContainsString('wire:click="run(', $maintenance);
        $this->assertStringContainsString('wire:submit="resetDatabase"', $reset);
    }

    public function test_database_overview_is_a_livewire_panel(): void
    {
        $blade = file_get_contents(resource_path('views/database-manager/index.blade.php'));
        $partial = file_get_contents(resource_path('views/database-manager/partials/overview.blade.php'));
        $overview = file_get_contents(resource_path('views/livewire/database-overview.blade.php'));

        $this->assertIsString($blade);
        $this->assertIsString($partial);
        $this->assertIsString($overview);
        $this->assertStringContainsString("@include('database-manager.partials.overview')", $blade);
        $this->assertStringContainsString('<livewire:database-overview', $partial);
        $this->assertStringContainsString('data-livewire-overview="true"', $overview);
        $this->assertStringContainsString('Aksi cepat', $overview);
    }

    public function test_legacy_icon_component_is_only_a_compatibility_adapter(): void
    {
        $legacy = file_get_contents(resource_path('views/components/ui-icon.blade.php'));
        $canonical = file_get_contents(resource_path('views/components/ui/icon.blade.php'));

        $this->assertIsString($legacy);
        $this->assertIsString($canonical);
        $this->assertStringContainsString('<x-ui.icon :name="$name"', $legacy);
        $this->assertStringNotContainsString('<svg', $legacy);
        foreach (['dashboard', 'transaction', 'tax', 'report', 'archive', 'number', 'database', 'users'] as $name) {
            $this->assertStringContainsString("'{$name}' =>", $canonical);
        }
    }

    public function test_global_layout_uses_canonical_icons_without_legacy_navigation_symbols(): void
    {
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('<x-ui.icon', $layout);
        $this->assertStringNotContainsString('<x-ui-icon', $layout);
        $this->assertStringNotContainsString('№', $layout);
        $this->assertStringNotContainsString('↺', $layout);
        $this->assertStringNotContainsString('◎', $layout);
    }

    public function test_scroll_to_top_uses_theme_tokens_in_authenticated_layout_css(): void
    {
        $css = file_get_contents(resource_path('css/layout-token-native.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('#app-scroll-to-top', $css);
        $this->assertStringContainsString('var(--ui-component-surface', $css);
        $this->assertStringContainsString('var(--ui-component-text', $css);
        $this->assertStringContainsString('var(--theme-accent)', $css);
    }

    public function test_spj_workspace_does_not_pin_density_owned_tokens(): void
    {
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));
        $summary = file_get_contents(resource_path('views/spj/partials/summary.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($summary);
        $this->assertStringContainsString('spj-work-summary', $summary);

        // Density tokens must stay owned by theme-profiles.css. This file is
        // imported later, so redeclaring them pinned every /spj density to one
        // value and made the active theme density unreachable.
        $this->assertStringNotContainsString('--profile-table-row-y:', $css);
        $this->assertStringNotContainsString('--profile-section-gap:', $css);
        $this->assertStringNotContainsString('--profile-control-height: 2.5rem', $css);

        // Touch-target floor must raise the density, not replace it.
        $this->assertStringContainsString('@media (max-width: 1023px)', $css);
        $this->assertStringContainsString('max(var(--profile-control-height), 2.75rem)', $css);

        // Workspace geometry that is genuinely SPJ-specific stays scoped here.
        $this->assertStringContainsString('--profile-card-radius: .75rem;', $css);
        $this->assertStringContainsString('--profile-control-radius: .5rem;', $css);
    }

    public function test_core_operator_lists_keep_desktop_and_mobile_source_fallbacks(): void
    {
        $employees = file_get_contents(resource_path('views/employees/index.blade.php'));
        $employeeComponent = file_get_contents(resource_path('views/livewire/employee-directory.blade.php'));
        $students = file_get_contents(resource_path('views/students/index.blade.php'));
        $spj = file_get_contents(resource_path('views/spj/index.blade.php'));
        $spjLivewire = implode("\n", array_map(
            fn ($view): string => (string) file_get_contents(resource_path('views/livewire/'.$view)),
            ['spj-preparation-filter.blade.php', 'spj-package-list.blade.php', 'spj-report-filter.blade.php', 'spj-monitoring-list.blade.php']
        ));

        $this->assertIsString($employees);
        $this->assertIsString($employeeComponent);
        $this->assertIsString($students);
        $this->assertIsString($spj);

        $this->assertStringContainsString('<livewire:employee-directory', $employees);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $employeeComponent);
        $this->assertStringContainsString('hidden md:block', $students);
        $this->assertStringContainsString('md:hidden', $students);
        $this->assertStringContainsString('lg:hidden', $spj.$spjLivewire);
        $this->assertStringContainsString('overflow-x-auto', $spj.$spjLivewire);
    }

    public function test_mobile_cards_and_document_rows_can_shrink_below_min_content(): void
    {
        // Regresi temuan browser QA 2026-10-05 (viewport 360px): kartu
        // transaksi 617px dan baris dokumen paket 406px mendorong viewport.
        $cards = file_get_contents(resource_path('views/livewire/transactions-table.blade.php'));
        $documents = file_get_contents(resource_path('views/spj/partials/package/documents.blade.php'));

        $this->assertIsString($cards);
        $this->assertIsString($documents);
        $this->assertStringContainsString('transaction-card-', $cards);
        $this->assertStringContainsString('min-w-0', $cards);
        $this->assertStringContainsString('flex-wrap', $documents);
    }

    public function test_scroll_to_top_button_does_not_overlay_the_center_action_column(): void
    {
        // Regresi temuan browser QA 2026-10-05: tombol "Ke atas" memakai
        // `fixed bottom-5 left-1/2` sehingga menutupi baris aksi terakhir pada
        // daftar panjang (terbukti menutupi link "Cetak" pada /laporan-periode,
        // overlap 2828px, elementFromPoint mengembalikan tombol).
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($app);
        $this->assertStringContainsString('app-scroll-to-top', $app);
        $this->assertStringNotContainsString(
            'bottom-5 left-1/2',
            $app,
            'Scroll-to-top must not sit at the horizontal center where row actions live.'
        );
        $this->assertStringContainsString('bottom-[5.5rem] right-5', $app);
        $this->assertStringContainsString('grid h-12 w-12', $app);
        $this->assertStringContainsString("aria-label', 'Kembali ke atas halaman'", $app);
    }

    public function test_ui_field_component_can_bind_generated_label_to_its_control(): void
    {
        // Penutup umum untuk field yang pemanggilnya tidak mengoper `for`:
        // primitive harus menghasilkan marker agar sisi client bisa mengikat
        // id ke kontrol submit (input pertama bernama, bukan input cermin).
        $field = file_get_contents(resource_path('views/components/ui/field.blade.php'));
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($field);
        $this->assertIsString($app);
        $this->assertStringContainsString('data-ui-field-bind-id', $field);
        $this->assertStringContainsString('bindGeneratedFieldIds', $app);
        $this->assertStringContainsString('input[name]:not([type="hidden"])', $app);
        $this->assertStringContainsString('livewire:navigated', $app);
    }

    public function test_topbar_action_group_can_wrap_on_narrow_viewports(): void
    {
        // Regresi browser QA 2026-10-05 (375px): grup tema + profil memakai
        // `flex-nowrap` sehingga mendorong dokumen scrollWidth ke 430px vs 360px.
        $layout = file_get_contents(resource_path('views/components/layouts/tailwind-app.blade.php'));

        $this->assertIsString($layout);
        $this->assertStringContainsString('flex min-w-0 flex-wrap items-center justify-end gap-2', $layout);
        $this->assertStringContainsString('max-w-[10rem] min-w-0 truncate', $layout);
    }

    public function test_page_header_decoration_stays_inside_header_on_small_screens(): void
    {
        // Regresi temuan browser QA 2026-10-05: dekorasi header meluber 43px
        // ke kanan pada viewport 375px (right dekorasi 403px vs viewport 360px).
        $css = file_get_contents(resource_path('css/token-native-components.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('.page-header-decoration-top', $css);
        $this->assertMatchesRegularExpression(
            '/@media\(max-width:639px\)\s*\{\s*\.page-header-decoration-top\s*\{[^}]*right:\s*-1rem/',
            $css,
            'Header decoration must be constrained inside the header on small screens.'
        );
    }

    public function test_every_visible_label_is_associated_with_a_control(): void
    {
        // Regresi temuan browser QA 2026-10-05: 17 kontrol pada Isian Manual
        // Paket tidak punya asosiasi label (klik label tidak memfokuskan input).
        // Label yang membungkus kontrol (implicit) tetap valid; yang terlarang
        // adalah label tanpa `for=` DAN tidak membungkus kontrol.
        $offenders = [];
        $viewPath = resource_path('views');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewPath));

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('/<label(?![^>]*\bfor=)[^>]*>(.*?)<\/label>/s', $content, $matches);

            foreach ($matches[1] as $body) {
                if (! preg_match('/<(input|select|textarea)\b/', $body)) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            'Labels must either use for= with a matching control id, or wrap the control.'
        );
    }

    public function test_package_form_fields_expose_ids_matching_their_labels(): void
    {
        // Kunci utama pada Isian Manual Paket: label harus punya `for` yang
        // menunjuk id kontrol, bukan hanya label visual.
        $common = file_get_contents(resource_path('views/spj/partials/package/common.blade.php'));

        $this->assertIsString($common);
        $this->assertStringContainsString('$fieldPrefix', $common);

        foreach ([
            'payment-description',
            'payment-method',
            'payment-reference',
            'vendor-name',
            'vendor-owner',
            'vendor-npwp',
            'receipt-recipient',
        ] as $suffix) {
            $this->assertStringContainsString(
                'for="{{ $fieldPrefix }}-'.$suffix.'"',
                $common,
                "Label for {$suffix} must reference its control id."
            );
            $this->assertStringContainsString(
                'id="{{ $fieldPrefix }}-'.$suffix.'"',
                $common,
                "Control {$suffix} must expose the id referenced by its label."
            );
        }
    }

    public function test_wide_data_views_keep_horizontal_overflow_or_shared_table_contracts(): void
    {
        $syncedData = file_get_contents(resource_path('views/synced-data/index.blade.php'));
        $numbering = file_get_contents(resource_path('views/spj/numbering.blade.php'));

        $this->assertIsString($syncedData);
        $this->assertIsString($numbering);
        $this->assertStringContainsString('<x-ui.table', $syncedData);
        $this->assertTrue(
            str_contains($numbering, '<x-ui.table') || str_contains($numbering, 'overflow-x-auto'),
            'Numbering workspace must retain a horizontally safe table contract.'
        );
    }

    public function test_panel_toggle_names_the_panel_it_collapses(): void
    {
        // Regresi temuan browser QA 2026-10-05: lima tombol "Tutup panel"
        // pada /pengaturan/template-dokumen sama-sama bernama "Tutup panel"
        // atau aria-label generik "Buka atau tutup panel", sehingga pengguna
        // screen reader tidak tahu tombol mana menutup bagian apa. aria-label
        // generik itu juga menimpa teks dinamis sehingga state hilang.
        $toggle = file_get_contents(resource_path('views/components/ui/panel-toggle.blade.php'));

        $this->assertIsString($toggle);
        $this->assertStringNotContainsString(
            'Buka atau tutup panel',
            $toggle,
            'Panel toggle must not override the dynamic visible label with a generic aria-label.'
        );
        $this->assertStringContainsString(
            ":aria-label=\"({{ \$state }} ? 'Tutup panel ' : 'Buka panel ') + @js(\$panel)\"",
            $toggle,
            'Panel toggle accessible name must be prefixed with the state and suffixed with the panel title.'
        );
        $this->assertStringContainsString('aria-controls="{{ $controls }}"', $toggle);
    }

    public function test_collapsible_panels_expose_an_aria_controls_target(): void
    {
        // Setiap panel yang punya tombol buka/tutup harus menunjuk body yang
        // dikendelnya lewat id, supaya aria-controls tidak pernah menggantung.
        $bodies = [
            'views/document-templates/index.blade.php' => [
                'document-templates-validation-results',
                'document-templates-available-list',
            ],
            'views/document-templates/partials/upload-limits.blade.php' => [
                'document-templates-upload-limits',
            ],
        ];

        foreach ($bodies as $relativePath => $ids) {
            $content = file_get_contents(resource_path($relativePath));

            $this->assertIsString($content);

            foreach ($ids as $id) {
                $this->assertStringContainsString(
                    'id="'.$id.'"',
                    $content,
                    "{$relativePath} must render the collapsible body with id {$id}."
                );
            }
        }

        // Header partial holds the toggle, page holds the body it controls.
        $toggles = [
            'views/document-templates/index.blade.php' => [
                'document-templates-available-list',
            ],
            'views/document-templates/partials/validation-header.blade.php' => [
                'document-templates-validation-results',
            ],
            'views/document-templates/partials/upload-limits.blade.php' => [
                'document-templates-upload-limits',
            ],
        ];

        foreach ($toggles as $relativePath => $ids) {
            $content = file_get_contents(resource_path($relativePath));

            $this->assertIsString($content);

            foreach ($ids as $id) {
                $this->assertStringContainsString(
                    'controls="'.$id.'"',
                    $content,
                    "{$relativePath} must point its panel toggle at {$id}."
                );
            }
        }

        $formSection = file_get_contents(resource_path('views/components/ui/form-section.blade.php'));

        $this->assertIsString($formSection);
        $this->assertStringContainsString('id="{{ $resolvedPanelId }}"', $formSection);
        $this->assertStringContainsString(':controls="$resolvedPanelId"', $formSection);
    }
}
