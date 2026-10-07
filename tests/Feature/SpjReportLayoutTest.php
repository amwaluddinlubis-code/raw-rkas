<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpjReportLayoutTest extends TestCase
{
    public function test_report_filter_and_summary_share_horizontal_layout(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringNotContainsString('<livewire:spj-periodic-report-center', $blade);
        $this->assertStringContainsString('aria-label="Filter laporan"', $blade);
        $this->assertStringContainsString('aria-label="Ringkasan laporan"', $blade);
        $this->assertStringContainsString('xl:grid-cols-5', $blade);
        $this->assertStringContainsString('grid grid-cols-2', $blade);
        $this->assertStringContainsString('text-xl font-extrabold', $blade);
        $this->assertStringContainsString('wire:click="setMode(', $blade);
        $this->assertStringContainsString('wire:model.live="periode"', $blade);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $blade);
        $this->assertStringContainsString('Pratinjau Massal', $blade);
        $this->assertStringContainsString('$vendorName', $blade);
        $this->assertStringContainsString('$recipientName', $blade);
        $this->assertStringContainsString('wire:model.live="perPage"', $blade);
        $this->assertStringContainsString("'bulan' => ['month' => \$periode]", $blade);
        $this->assertStringContainsString("route('spj.honor-payments.select', \$exportQuery)", $blade);
        $this->assertStringContainsString("route('spj.service-recipients.select', \$exportQuery)", $blade);
        $this->assertStringContainsString('Susun Laporan', $blade);
        $this->assertStringContainsString('Honor Pegawai', $blade);
        $this->assertStringContainsString('Jasa Lainnya', $blade);
        $this->assertStringNotContainsString('Pratinjau PDF', $blade);
        $this->assertStringNotContainsString('Unduh Excel', $blade);
        $this->assertStringNotContainsString('Daftar Honor PDF', $blade);
        $this->assertStringNotContainsString('Daftar Penerima Jasa PDF', $blade);
    }

    public function test_last_three_report_rows_open_action_menu_upward(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));
        $menu = file_get_contents(resource_path('views/components/ui/action-menu.blade.php'));
        $css = file_get_contents(resource_path('css/ui-generalization.css'));

        $this->assertIsString($blade);
        $this->assertIsString($menu);
        $this->assertIsString($css);
        $this->assertStringContainsString('$packageIndex => $package', $blade);
        $this->assertStringContainsString(':drop-up="(($packages?->count() ?? 0) - $packageIndex) <= 3"', $blade);
        $this->assertStringContainsString("'dropUp' => false", $menu);
        $this->assertStringContainsString('ui-action-menu-panel-drop-up', $menu);
        $this->assertStringContainsString('.ui-action-menu-panel-drop-up', $css);
        $this->assertStringContainsString('bottom: calc(100% + .35rem)', $css);
    }

    public function test_periodic_report_center_lists_period_scopes_and_source_summary(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-periodic-report-center.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('Paket laporan periode', $blade);
        $this->assertStringContainsString('aria-label="Jenis paket laporan"', $blade);
        $this->assertStringContainsString('wire:click="setScope(', $blade);
        $this->assertStringContainsString('wire:model.live="periode"', $blade);
        $this->assertStringContainsString('Ringkasan sumber data', $blade);
        $this->assertStringContainsString('Siap dicetak', $blade);
    }

    public function test_spj_tabs_use_livewire_filters_without_full_page_reload(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($index);
        $this->assertStringContainsString('<livewire:spj-preparation-filter />', $index);
        $this->assertStringContainsString('<livewire:spj-package-list />', $index);
        $this->assertStringContainsString('<livewire:spj-report-filter />', $index);
        $this->assertStringContainsString('<livewire:spj-monitoring-list />', $index);
        $this->assertStringContainsString("route('spj.bulk-finalize')", $index);
        $this->assertStringContainsString('Finalkan paket', $index);
        $this->assertStringNotContainsString('relocateReportRowControl', $index);
        $this->assertStringNotContainsString('data-report-mode', $index);
    }

    public function test_report_preview_opens_in_shared_modal_partial(): void
    {
        $partial = file_get_contents(resource_path('views/spj/partials/preview-modal.blade.php'));
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $report = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($partial);
        $this->assertIsString($index);
        $this->assertIsString($report);
        $this->assertSame(1, substr_count($partial, 'id="template-preview-modal"'));
        $this->assertSame(1, substr_count($partial, 'id="template-preview-frame"'));
        $this->assertStringContainsString('name="template-preview-frame"', $partial);
        $this->assertStringContainsString('id="template-preview-download-pdf"', $partial);
        $this->assertStringContainsString('id="template-preview-download-excel"', $partial);
        $this->assertStringContainsString('class="h-full min-h-[760px] w-full border-0 bg-transparent"', $partial);
        $this->assertStringContainsString("@include('spj.partials.preview-modal')", $index);
        $this->assertStringNotContainsString('id="template-preview-modal"', $index);
        $this->assertStringContainsString('data-template-preview', $report);
        $this->assertStringContainsString('data-template-download-pdf', $report);
        $this->assertStringContainsString('data-template-download-excel', $report);
        $this->assertStringContainsString("route('spj.preview-package-excel'", $report);
        $this->assertStringContainsString('data-bulk-preview-form', $report);
        $this->assertStringContainsString('data-bulk-submit', $report);
        $this->assertStringContainsString('name="package_ids[]"', $report);
        $this->assertStringContainsString('data-bulk-select-all', $report);
        $this->assertStringContainsString("route('spj.preview-packages')", $report);
        $this->assertStringNotContainsString('target="_blank">Preview dokumen', $report);
        $this->assertStringContainsString('updateBulkSelection', $index);
        $this->assertStringContainsString('Maksimal 20; batalkan pilihan', $index);
        $this->assertStringContainsString('submit.disabled = selectedCount === 0 || overLimit', $index);
    }

    public function test_bulk_report_preview_uses_the_same_inline_pdf_pipeline_as_single_preview(): void
    {
        $templateService = file_get_contents(base_path('app/Services/SpjTemplateService.php'));
        $alignedTemplateService = file_get_contents(base_path('app/Services/PreviewAlignedSpjTemplateService.php'));
        $useCase = file_get_contents(base_path('app/UseCases/Spj/SpjDocumentUseCase.php'));
        $controller = file_get_contents(base_path('app/Http/Controllers/SpjController.php'));
        $routes = file_get_contents(base_path('routes/web.php'));

        $this->assertIsString($templateService);
        $this->assertIsString($alignedTemplateService);
        $this->assertIsString($useCase);
        $this->assertIsString($controller);
        $this->assertIsString($routes);
        $this->assertStringContainsString('packagesPreviewPdfBytes', $templateService);
        $this->assertStringContainsString('packagesPreviewPdfBytes', $alignedTemplateService);
        $this->assertStringContainsString('count($packageIds) > 20', $useCase);
        $this->assertStringContainsString("'transaction.payments'", $useCase);
        $this->assertStringContainsString("'transaction.spjPackage'", $useCase);
        $this->assertStringContainsString('$this->context->matchesTransaction($package->transaction)', $useCase);
        $this->assertStringContainsString("'Content-Type' => 'application/pdf'", $useCase);
        $this->assertStringContainsString("'PRATINJAU-MASSAL-PAKET-SPJ.pdf'", $useCase);
        $this->assertStringContainsString('public function previewPackages(Request $request, SpjDocumentUseCase $useCase): Response', $controller);
        $this->assertStringContainsString("Route::post('/spj/laporan/pratinjau-bulk'", $routes);
    }

    public function test_spj_main_tabs_render_as_segmented_control(): void
    {
        $css = file_get_contents(resource_path('css/spj-workspace-standardization.css'));
        $canonical = file_get_contents(resource_path('css/ui-generalization.css'));
        $tabs = file_get_contents(resource_path('views/components/tabs.blade.php'));

        $this->assertIsString($css);
        $this->assertIsString($canonical);
        $this->assertIsString($tabs);

        // Tab utama SPJ memakai segmented control bersama dengan tab internal paket.
        $this->assertStringContainsString('#spj-main-tabs .ui-tabs-list', $css);
        $this->assertStringContainsString('#spj-main-tabs .ui-tab', $css);

        // State aktif harus tetap dibedakan secara visual. Assertion lama melarang
        // .ui-tab-active di CSS, padahal markup tabs.blade.php memakai kelas itu
        // untuk tab terpilih. Yang dijaga adalah keberadaan penanda aktif, bukan
        // penghilangannya.
        $this->assertStringContainsString('ui-tab-active', $tabs);
        $this->assertStringContainsString('.ui-tab-active', $canonical);
    }

    public function test_all_workspace_tabs_use_circular_icons_equal_width_and_glass_hover(): void
    {
        $tabs = file_get_contents(resource_path('views/components/tabs.blade.php'));
        $databaseTabs = file_get_contents(resource_path('views/livewire/database-manager-tabs.blade.php'));
        $references = file_get_contents(resource_path('views/references/index.blade.php'));
        $spj = file_get_contents(resource_path('views/spj/index.blade.php'));
        $css = file_get_contents(resource_path('css/ui-generalization.css'));
        $spjCss = file_get_contents(resource_path('css/spj-workspace-standardization.css'));

        foreach ([$tabs, $databaseTabs, $references, $spj, $css, $spjCss] as $source) {
            $this->assertIsString($source);
        }

        $this->assertStringContainsString('class="ui-tab-icon"', $tabs);
        $this->assertStringContainsString('class="ui-tab-icon"', $databaseTabs);
        $this->assertStringContainsString('class="ui-tab-icon"', $references);
        $this->assertStringContainsString('class="ui-tab-icon"', $spj);
        $this->assertStringContainsString('flex: 1 1 0;', $css);
        $this->assertStringContainsString('border-radius: 9999px;', $css);
        $this->assertStringContainsString('backdrop-filter: blur(10px);', $css);
        $this->assertStringContainsString('overflow: hidden;', $css);
        $this->assertStringContainsString('class="ui-tabs-list"', $spj);
    }

    public function test_siplah_goods_number_strip_uses_marketplace_order_reference(): void
    {
        $blade = file_get_contents(resource_path('views/spj/partials/package/categories/barang.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('$paymentMethod = strtolower(trim((string) $transaction->payment_method));', $blade);
        $this->assertStringContainsString("\$isSiplah = \$paymentMethod === 'siplah'", $blade);
        $this->assertStringContainsString('$siplahOrder = $transaction->siplah_order_number', $blade);
        $this->assertStringContainsString('siplahResponse.invoice_number', $blade);
        $this->assertStringContainsString('$autoOrderNumber = $isSiplah', $blade);
        $this->assertStringContainsString('? $siplahOrder', $blade);
        $this->assertStringContainsString('$purchaseDetails?->order_number ?: $transaction->order_number', $blade);
        $this->assertStringContainsString('data-auto-number-pesanan="{{ $autoOrderNumber }}"', $blade);
    }

    public function test_siplah_metadata_uses_one_row_on_large_screens(): void
    {
        $blade = file_get_contents(resource_path('views/spj/index.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('sm:grid-cols-2 lg:grid-cols-5', $blade);
    }

    public function test_spj_preparation_filters_and_reset_share_one_row(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('sm:grid-cols-2 md:grid-cols-6', $blade);
        $this->assertStringContainsString('lg:grid-cols-[minmax(10rem,1fr)_minmax(7rem,0.8fr)_minmax(7rem,0.8fr)_minmax(26rem,2fr)_auto_auto]', $blade);
        $this->assertStringContainsString('wire:click="setMode(', $blade);
        $this->assertStringContainsString('id="spj-preparation-per-page"', $blade);
        $this->assertStringContainsString('icon="refresh"', $blade);
        $this->assertStringContainsString('>Bersihkan</x-ui.button>', $blade);
    }

    public function test_spj_category_labels_are_title_case_in_display_surfaces(): void
    {
        $displayFiles = [
            resource_path('views/livewire/spj-preparation-filter.blade.php'),
            resource_path('views/spj/index.blade.php'),
            resource_path('views/livewire/transactions-table.blade.php'),
            resource_path('views/livewire/spj-package-list.blade.php'),
            resource_path('views/spj/checklist.blade.php'),
            resource_path('views/spj/numbering.blade.php'),
            resource_path('views/livewire/reconciliation-list.blade.php'),
        ];

        foreach ($displayFiles as $file) {
            $blade = file_get_contents($file);

            $this->assertIsString($blade);
            $this->assertStringContainsString("'BARANG' => 'Barang'", $blade);
            $this->assertStringContainsString("'KONSUMSI' => 'Konsumsi'", $blade);
            $this->assertStringContainsString("'PEMELIHARAAN' => 'Pemeliharaan'", $blade);
            $this->assertStringContainsString("'JASA_LAINNYA' => 'Jasa Lainnya'", $blade);
            $this->assertStringNotContainsString("default => str_replace('_', ' ', (string) \$value)", $blade);
        }

        $this->assertStringContainsString("'JENIS_SPJ' => \$spjCategoryLabel", file_get_contents(base_path('app/Services/SpjTemplateService.php')));
        $this->assertStringContainsString('kategori <span class="font-bold">Honor Pegawai</span>', file_get_contents(resource_path('views/spj-reports/honor-select.blade.php')));
        $this->assertStringContainsString('kategori <span class="font-bold">Jasa Lainnya</span>', file_get_contents(resource_path('views/spj-reports/service-recipient-select.blade.php')));
    }

    public function test_spj_package_tabs_use_flat_theme_indicator_and_transition_panels(): void
    {
        $blade = file_get_contents(resource_path('views/spj/index.blade.php'));
        $css = file_get_contents(resource_path('css/ui-generalization.css'));

        $this->assertIsString($blade);
        $this->assertIsString($css);
        $this->assertStringContainsString('data-package-tab=', $blade);
        $this->assertStringContainsString('ui-tab ui-tab-active', $blade);
        $this->assertStringNotContainsString('spj-standard-tab', $blade);
        $this->assertStringContainsString('x-transition:enter-start="opacity-0 translate-y-1"', $blade);
        $this->assertStringContainsString('.ui-tab::after', $css);
        $this->assertStringContainsString('.ui-tab-active::after', $css);
        $this->assertStringContainsString('background: var(--theme-content-accent, var(--ui-accent));', $css);
    }

    public function test_spj_monitoring_surfaces_use_theme_tokens(): void
    {
        $index = file_get_contents(resource_path('views/spj/index.blade.php'));
        $monitoring = file_get_contents(resource_path('views/livewire/spj-monitoring-list.blade.php'));

        $this->assertIsString($index);
        $this->assertIsString($monitoring);
        $this->assertStringContainsString('bg-[var(--ui-surface-soft)]', $index);
        $this->assertStringNotContainsString('bg-amber-50/40', $index);
        $this->assertStringNotContainsString('bg-amber-50', $monitoring);
        $this->assertStringNotContainsString('bg-rose-50', $monitoring);
        $this->assertStringContainsString('spj-monitoring-table', $monitoring);
    }

    public function test_preparation_and_package_lists_render_one_pagination_summary_each(): void
    {
        $preparation = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));
        $packages = file_get_contents(resource_path('views/livewire/spj-package-list.blade.php'));
        $report = file_get_contents(resource_path('views/livewire/spj-report-filter.blade.php'));

        $this->assertIsString($preparation);
        $this->assertIsString($packages);
        $this->assertIsString($report);
        $this->assertSame(0, substr_count($preparation, 'Menampilkan'));
        $this->assertSame(0, substr_count($packages, 'Menampilkan'));
        $this->assertSame(0, substr_count($report, 'Menampilkan'));
        $this->assertStringContainsString('x-ui.server-pagination', $preparation);
        $this->assertStringContainsString('x-ui.server-pagination', $packages);
        $this->assertStringContainsString('x-ui.server-pagination', $report);
        $this->assertStringNotContainsString(':compact="true"', $preparation);
        $this->assertStringNotContainsString(':compact="true"', $packages);

        $paginationView = file_get_contents(resource_path('views/vendor/pagination/tailwind.blade.php'));
        $livewirePaginationView = file_get_contents(resource_path('views/vendor/livewire/tailwind.blade.php'));
        $this->assertStringNotContainsString('ui-pagination-summary', $paginationView);
        $this->assertStringNotContainsString('ui-pagination-summary', $livewirePaginationView);
    }
}
