<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjPackage;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DocumentTemplateLibraryService;
use App\Services\SpjPackageTemplateSelector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentTemplateOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Storage::fake('local');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        $year = FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $school = School::query()->create(['npsn' => '10208183', 'school_code' => 'SCH-ORD', 'name' => 'SD Urutan', 'address' => 'Jl. Urut']);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN', 'school_id' => $school->id]));
        $this->withoutMiddleware();
        session()->put([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        foreach (['SPJ_COVER', 'KUITANSI_A2', 'BAP'] as $type) {
            DocumentTemplate::query()->create([
                'fiscal_year_id' => $year->id,
                'document_type' => $type,
                'name' => 'Template '.$type,
                'format' => 'xlsx',
                'file_path' => 'document-templates/t-'.$type.'.xlsx',
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_move_swaps_order_with_neighbor(): void
    {
        $library = app(DocumentTemplateLibraryService::class);

        $this->assertSame(
            ['SPJ_COVER', 'KUITANSI_A2', 'BAP'],
            DocumentTemplate::query()->orderBy('sort_order')->pluck('document_type')->all()
        );

        $this->assertTrue($library->moveTemplate((string) DocumentTemplate::query()->where('document_type', 'BAP')->firstOrFail()->id, 'up'));

        $this->assertSame(
            ['SPJ_COVER', 'BAP', 'KUITANSI_A2'],
            DocumentTemplate::query()->orderBy('sort_order')->pluck('document_type')->all()
        );

        // Tepi: paling atas tidak berubah, id tak dikenal gagal.
        $this->assertTrue($library->moveTemplate((string) DocumentTemplate::query()->orderBy('sort_order')->firstOrFail()->id, 'up'));
        $this->assertFalse($library->moveTemplate('999999', 'up'));
        $this->assertFalse($library->moveTemplate((string) DocumentTemplate::query()->firstOrFail()->id, 'sideways'));
    }

    public function test_package_preview_follows_operator_order(): void
    {
        $library = app(DocumentTemplateLibraryService::class);
        $kuitansi = DocumentTemplate::query()->where('document_type', 'KUITANSI_A2')->firstOrFail();
        $library->moveTemplate((string) $kuitansi->id, 'up');
        $library->moveTemplate((string) $kuitansi->id, 'up');

        $transaction = new Transaction(['fiscal_year_id' => 1, 'fund_source_id' => 1, 'spj_category' => 'BARANG']);
        $package = new SpjPackage;
        $package->setRelation('transaction', $transaction);

        $this->assertSame(
            ['KUITANSI_A2', 'SPJ_COVER', 'BAP'],
            app(SpjPackageTemplateSelector::class)->forPackage($package)->pluck('document_type')->all()
        );
    }

    public function test_move_requires_administrator(): void
    {
        $operator = User::factory()->create(['role' => 'OPERATOR']);
        $this->actingAs($operator);

        $id = (string) DocumentTemplate::query()->firstOrFail()->id;
        Livewire::test('document-template-list')->call('moveUp', $id)->assertForbidden();
    }
}
