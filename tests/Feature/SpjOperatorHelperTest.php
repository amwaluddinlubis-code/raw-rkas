<?php

namespace Tests\Feature;

use App\Http\Controllers\SpjQuarterRecapController;
use App\Models\SpjOperatorNote;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Services\SpjOperatorHintService;
use App\Services\SpjProcurementPolicyService;
use App\UseCases\Spj\SpjOperatorNoteUseCase;
use App\UseCases\Spj\SpjQuarterRecapUseCase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SpjOperatorHelperTest extends TestCase
{
    private function makePackage(array $attributes): SpjPackage
    {
        // Kolom fakta sumber (gross_amount, account_name, dsb) bukan
        // fillable — pakai forceFill seperti state sync/mirror.
        $transaction = (new Transaction)->forceFill($attributes);
        $transaction->setRelation('items', collect());

        $package = new SpjPackage(['status' => 'DRAFT']);
        $package->setRelation('transaction', $transaction);

        return $package;
    }

    private function hints(array $attributes): SpjOperatorHintService
    {
        return new SpjOperatorHintService(new SpjProcurementPolicyService);
    }

    public function test_materai_hint_appears_above_five_million(): void
    {
        $package = $this->makePackage(['gross_amount' => 6000000, 'spj_category' => 'BARANG']);

        $keys = collect($this->hints([])->hints($package))->pluck('key')->all();

        $this->assertContains('materai', $keys);
    }

    public function test_no_materai_hint_for_small_amount(): void
    {
        $package = $this->makePackage(['gross_amount' => 1000000, 'spj_category' => 'BARANG', 'ppn' => 110000]);

        $keys = collect($this->hints([])->hints($package))->pluck('key')->all();

        $this->assertNotContains('materai', $keys);
        $this->assertNotContains('ppn_check', $keys);
    }

    public function test_ppn_hint_for_barang_above_two_million_without_ppn(): void
    {
        $package = $this->makePackage(['gross_amount' => 3000000, 'spj_category' => 'BARANG', 'ppn' => 0]);

        $keys = collect($this->hints([])->hints($package))->pluck('key')->all();

        $this->assertContains('ppn_check', $keys);
    }

    public function test_konsumsi_tax_hint_without_pph23(): void
    {
        $package = $this->makePackage(['gross_amount' => 1500000, 'spj_category' => 'KONSUMSI', 'pph23' => 0]);

        $keys = collect($this->hints([])->hints($package))->pluck('key')->all();

        $this->assertContains('konsumsi_tax', $keys);
    }

    public function test_category_suggestion_from_account_name(): void
    {
        $service = new SpjOperatorHintService(new SpjProcurementPolicyService);
        $transaction = (new Transaction)->forceFill(['account_name' => 'Honor GTT bulanan', 'spj_category' => null]);
        $transaction->setRelation('items', collect());

        $suggestion = $service->categorySuggestion($transaction);

        $this->assertNotNull($suggestion);
        $this->assertSame('HONOR_PEGAWAI', $suggestion['category']);
    }

    public function test_no_category_suggestion_when_already_matching(): void
    {
        $service = new SpjOperatorHintService(new SpjProcurementPolicyService);
        $transaction = (new Transaction)->forceFill(['account_name' => 'Honor GTT bulanan', 'spj_category' => 'HONOR_PEGAWAI']);

        $this->assertNull($service->categorySuggestion($transaction));
    }

    public function test_bku_match_returns_overlay_comparison_rows(): void
    {
        $service = new SpjOperatorHintService(new SpjProcurementPolicyService);
        $package = $this->makePackage([
            'no_bukti' => 'BNU-1',
            'gross_amount' => 1000000,
            'recipient_name' => 'Toko Sumber',
            'description' => 'Belanja ATK',
            'payment_description' => '',
            'receipt_recipient_name' => '',
            'spj_category' => 'BARANG',
            'ppn' => 0,
        ]);

        $match = $service->bkuMatch($package);

        $this->assertArrayHasKey('rows', $match);
        $this->assertArrayHasKey('all_match', $match);
        $labels = collect($match['rows'])->pluck('label')->all();
        $this->assertContains('Nomor bukti', $labels);
        $this->assertContains('Nilai bruto', $labels);
        $this->assertContains('Penerima', $labels);
        $this->assertContains('Uraian', $labels);
        // Overlay penerima + uraian masih kosong → belum cocok semua.
        $this->assertFalse($match['all_match']);
    }

    public function test_checklist_blade_renders_helper_panels(): void
    {
        $blade = file_get_contents(resource_path('views/spj/checklist.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('Cocokkan BKU', $blade);
        $this->assertStringContainsString('Saran pemeriksa', $blade);
        $this->assertStringContainsString('Riwayat paket', $blade);
        $this->assertStringContainsString('Cetak pendamping', $blade);
        $this->assertStringContainsString('tidak memblokir penomoran', $blade);
    }

    public function test_preparation_search_is_wired(): void
    {
        $useCase = file_get_contents(app_path('UseCases/Spj/SpjWorkspaceUseCase.php'));
        $livewire = file_get_contents(app_path('Livewire/SpjPreparationFilter.php'));
        $blade = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));

        $this->assertIsString($useCase);
        $this->assertIsString($livewire);
        $this->assertIsString($blade);
        $this->assertStringContainsString("'search' => ['nullable', 'string', 'max:100']", $useCase);
        $this->assertStringContainsString("orWhere('transactions.vendor_name', 'like', '%'.\$search.'%')", $useCase);
        $this->assertStringContainsString('public string $search', $livewire);
        $this->assertStringContainsString('spj-preparation-search', $blade);
        $this->assertStringContainsString('wire:model.live.debounce.300ms="search"', $blade);
    }

    public function test_checklist_controller_passes_helper_data(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/SpjPackageChecklistController.php'));

        $this->assertIsString($controller);
        $this->assertStringContainsString('SpjOperatorHintService', $controller);
        $this->assertStringContainsString('helperHints', $controller);
        $this->assertStringContainsString('bkuMatch', $controller);
        $this->assertStringContainsString('categorySuggestion', $controller);
        $this->assertStringContainsString('packageTimeline', $controller);
        $this->assertStringContainsString('operatorNotes', $controller);
        $this->assertStringContainsString('canNote', $controller);
    }

    public function test_operator_note_routes_are_registered(): void
    {
        $this->assertTrue(Route::has('spj.operator-notes.store'));
        $this->assertTrue(Route::has('spj.operator-notes.destroy'));
        $this->assertTrue(Route::has('spj.quarter-recap'));
    }

    public function test_operator_note_migration_and_model_exist(): void
    {
        $this->assertFileExists(database_path('migrations/school/2026_10_01_000001_create_spj_operator_notes_table.php'));
        $this->assertTrue(class_exists(SpjOperatorNote::class));
        $this->assertTrue(class_exists(SpjOperatorNoteUseCase::class));
        $this->assertTrue(method_exists(SpjOperatorNoteUseCase::class, 'store'));
        $this->assertTrue(method_exists(SpjOperatorNoteUseCase::class, 'destroy'));
    }

    public function test_checklist_blade_renders_note_panel(): void
    {
        $blade = file_get_contents(resource_path('views/spj/checklist.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('Catatan operator', $blade);
        $this->assertStringContainsString('spj.operator-notes.store', $blade);
        $this->assertStringContainsString('spj.operator-notes.destroy', $blade);
        $this->assertStringContainsString('tidak tercetak ke dokumen SPJ', $blade);
    }

    public function test_quarter_recap_view_and_entry_link_exist(): void
    {
        $recap = file_get_contents(resource_path('views/spj/quarter-recap.blade.php'));
        $filter = file_get_contents(resource_path('views/livewire/spj-preparation-filter.blade.php'));

        $this->assertIsString($recap);
        $this->assertIsString($filter);
        $this->assertStringContainsString('Rekap Triwulan', $recap);
        $this->assertStringContainsString('Siap dinomori', $recap);
        $this->assertStringContainsString('spj.checklist', $recap);
        $this->assertStringContainsString('spj.quarter-recap', $filter);
        $this->assertTrue(class_exists(SpjQuarterRecapUseCase::class));
        $this->assertTrue(class_exists(SpjQuarterRecapController::class));
    }
}
