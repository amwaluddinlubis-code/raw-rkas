<?php

namespace Tests\Feature;

use App\Livewire\TaxFilter;
use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class TaxFilterLivewireTest extends TestCase
{
    use RefreshDatabase, SeedsArkasMirror;

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_tax_search_and_month_filter_update_without_reload(): void
    {
        $this->seedTaxes();

        Livewire::test(TaxFilter::class)
            ->assertSee('BKU-PJK-001')
            ->assertSee('BKU-PJK-002')
            ->set('q', 'BKU-PJK-002')
            ->assertSee('BKU-PJK-002')
            ->assertDontSee('BKU-PJK-001')
            ->set('q', '')
            ->call('setMode', 'bulan')
            ->set('periode', 2)
            ->assertSee('BKU-PJK-002')
            ->assertDontSee('BKU-PJK-001');
    }

    public function test_header_summary_follows_active_filter(): void
    {
        $this->seedTaxes();

        Livewire::test(TaxFilter::class)
            ->assertSee('Total Tahunan 2026', false)
            ->assertDontSee('Subtotal Periode Terpilih', false)
            ->call('setMode', 'bulan')
            ->set('periode', 2)
            ->assertSee('Subtotal Periode Terpilih', false)
            ->assertDontSee('Total Tahunan 2026', false);
    }

    public function test_tax_reset_restores_full_list(): void
    {
        $this->seedTaxes();

        Livewire::test(TaxFilter::class)
            ->call('setMode', 'bulan')
            ->set('periode', 1)
            ->assertDontSee('BKU-PJK-002')
            ->call('resetFilters')
            ->assertSee('BKU-PJK-001')
            ->assertSee('BKU-PJK-002');
    }

    public function test_taxes_page_renders_header_and_livewire_component(): void
    {
        $this->seedTaxes();

        $this->withoutMiddleware()->get(route('taxes.index'))
            ->assertOk()
            ->assertSee('Total Tahunan 2026', false)
            ->assertSee('Daftar Pajak Tersinkron', false)
            ->assertSee('BKU-PJK-001', false);
    }

    public function test_tax_mount_reads_query_params(): void
    {
        $this->seedTaxes();

        Livewire::withQueryParams(['mode' => 'bulan', 'periode' => 2])
            ->test(TaxFilter::class)
            ->assertSet('mode', 'bulan')
            ->assertSet('periode', 2)
            ->assertSee('BKU-PJK-002')
            ->assertDontSee('BKU-PJK-001');
    }

    public function test_tax_mount_supports_legacy_month_param(): void
    {
        $this->seedTaxes();

        Livewire::withQueryParams(['month' => 2])
            ->test(TaxFilter::class)
            ->assertSet('mode', 'bulan')
            ->assertSet('periode', 2)
            ->assertSee('BKU-PJK-002')
            ->assertDontSee('BKU-PJK-001');
    }

    public function test_filter_panel_uses_aligned_grid_without_empty_columns(): void
    {
        $blade = file_get_contents(resource_path('views/livewire/tax-filter.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('lg:grid-cols-[auto_minmax(0,1fr)_minmax(0,1fr)_minmax(0,.7fr)_minmax(0,.7fr)_auto]', $blade);
        $this->assertStringNotContainsString('ui-filter-grid', $blade);
    }

    public function test_pagination_view_is_unified_with_theme(): void
    {
        $view = file_get_contents(resource_path('views/vendor/pagination/tailwind.blade.php'));

        $this->assertIsString($view);
        $styles = file_get_contents(resource_path('css/ui-generalization.css'));

        $this->assertIsString($styles);
        $this->assertStringContainsString('--theme-action-bg', $styles);
        $this->assertStringContainsString('var(--ui-line)', $styles);
        $this->assertStringContainsString('pagination.previous', $view);
        $this->assertStringContainsString('ui-pagination-group', $view);
        $this->assertStringContainsString('range(1, min(3, $lastPage))', $view);
        $this->assertStringContainsString('range(max(1, $lastPage - 2), $lastPage)', $view);
        $this->assertStringContainsString('data-pagination-standard="segmented"', $view);
        $this->assertStringNotContainsString('rounded-lg', $view);
        $this->assertStringNotContainsString('Showing', $view);
        $this->assertStringNotContainsString('bg-white', $view);
    }

    public function test_pagination_controls_render_in_indonesian_when_many_rows(): void
    {
        $this->seedTaxes(extra: 20);

        Livewire::test(TaxFilter::class)
            ->assertSee('Sebelumnya', false)
            ->assertSee('Berikutnya', false)
            ->assertSee('Menampilkan', false)
            ->assertSee('BKU-X020', false);
    }

    public function test_pagination_controls_navigate_to_second_page(): void
    {
        $this->seedTaxes(extra: 20);

        Livewire::test(TaxFilter::class)
            ->assertSee('BKU-X020', false)
            ->assertDontSee('BKU-PJK-001', false)
            ->call('gotoPage', 2)
            ->assertSee('BKU-PJK-001', false)
            ->assertDontSee('BKU-X020', false);
    }

    public function test_siplah_filter_and_column(): void
    {
        $this->seedTaxes(withSiplah: true);

        Livewire::test(TaxFilter::class)
            ->assertSee('Siplah', false)
            ->assertSee('BKU-SPL-001', false)
            ->set('siplah', 'ya')
            ->assertSee('BKU-SPL-001', false)
            ->assertDontSee('BKU-PJK-001', false)
            ->set('siplah', 'tidak')
            ->assertSee('BKU-PJK-001', false)
            ->assertDontSee('BKU-SPL-001', false);
    }

    public function test_tax_type_breakdown_uses_mirror_values(): void
    {
        $this->seedTaxes();

        Livewire::test(TaxFilter::class)
            ->assertSee('Rp 11.000', false)
            ->assertSee('Rp 22.000', false);
    }

    public function test_jenis_pajak_filter(): void
    {
        $this->seedTaxes(withSiplah: true);

        Livewire::test(TaxFilter::class)
            ->set('jenisPajak', 'pph23')
            ->assertSee('BKU-PPH23-001', false)
            ->assertDontSee('BKU-PJK-001', false)
            ->assertDontSee('BKU-PJK-002', false)
            ->set('jenisPajak', 'ppn')
            ->assertSee('BKU-PJK-001', false)
            ->assertDontSee('BKU-PPH23-001', false);
    }

    private function seedTaxes(int $extra = 0, bool $withSiplah = false): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Schema::connection('school')->create('fund_sources', function ($table): void {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('fiscal_years', function ($table): void {
            $table->id();
            $table->integer('year');
            $table->string('fund_source')->nullable();
            $table->foreignId('fund_source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::connection('school')->create('transactions', function ($table): void {
            $table->id();
            $table->foreignId('fiscal_year_id');
            $table->foreignId('fund_source_id')->nullable();
            $table->string('id_kas_umum')->nullable();
            $table->string('source_key', 64)->nullable();
            $table->string('no_bukti')->nullable();
            $table->date('transaction_date')->nullable();
            $table->text('description')->nullable();
            $table->string('recipient_name')->nullable();
            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('tax_total', 18, 2)->default(0);
            $table->decimal('net_amount', 18, 2)->default(0);
            $table->decimal('ppn', 18, 2)->default(0);
            $table->decimal('pph21', 18, 2)->default(0);
            $table->decimal('pph22', 18, 2)->default(0);
            $table->decimal('pph23', 18, 2)->default(0);
            $table->decimal('pph4', 18, 2)->default(0);
            $table->decimal('sspd', 18, 2)->default(0);
            $table->string('status')->nullable();
            $table->string('spj_category')->nullable();
            $table->timestamps();
        });

        Schema::connection('school')->create('transaction_items', function ($table): void {
            $table->id();
            $table->foreignId('transaction_id');
            $table->string('source_item_id')->nullable();
            $table->text('description')->nullable();
            $table->text('item_description')->nullable();
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();
        });

        $fundSource = FundSource::on('school')->create(['code' => 'BOS', 'name' => 'BOS Reguler']);
        $year = FiscalYear::on('school')->create([
            'year' => 2026,
            'fund_source' => 'BOS Reguler',
            'fund_source_id' => $fundSource->id,
            'is_active' => true,
        ]);

        $rows = [
            ['BKU-PJK-001', '2026-01-12', 'Toko ATK Maju', 11000],
            ['BKU-PJK-002', '2026-02-12', 'CV Berkah Jaya', 22000],
        ];
        foreach (range(1, $extra) as $number) {
            $rows[] = [sprintf('BKU-X%03d', $number), '2026-03-12', 'Pemasok '.$number, 5000];
        }
        foreach ($rows as [$noBukti, $date, $recipient, $tax]) {
            $this->mirrorTransaction([
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSource->id,
                'no_bukti' => $noBukti,
                'transaction_date' => $date,
                'description' => 'Belanja '.$noBukti,
                'recipient_name' => $recipient,
                'gross_amount' => 100000,
                'tax_total' => $tax,
                'net_amount' => 100000 - $tax,
                'ppn' => $tax,
                'status' => 'DITETAPKAN',
            ]);
        }

        if ($withSiplah) {
            $this->mirrorTransaction([
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSource->id,
                'no_bukti' => 'BKU-SPL-001',
                'transaction_date' => '2026-03-13',
                'description' => 'Belanja Siplah',
                'recipient_name' => 'Toko Siplah',
                'gross_amount' => 100000,
                'tax_total' => 11000,
                'net_amount' => 89000,
                'ppn' => 11000,
                'status' => 'DITETAPKAN',
                'mirror_IS_SIPLAH' => 1,
            ]);
            $this->mirrorTransaction([
                'fiscal_year_id' => $year->id,
                'fund_source_id' => $fundSource->id,
                'no_bukti' => 'BKU-PPH23-001',
                'transaction_date' => '2026-03-14',
                'description' => 'Sewa Alat',
                'recipient_name' => 'CV Sewa',
                'gross_amount' => 100000,
                'tax_total' => 4000,
                'net_amount' => 96000,
                'pph23' => 4000,
                'status' => 'DITETAPKAN',
            ]);
        }

        $this->actingAs(User::factory()->create(['role' => 'ADMIN']))
            ->withSession([
                'active_school_id' => 1,
                'active_fiscal_year_id' => $year->id,
                'active_fund_source_id' => $fundSource->id,
            ]);
    }
}
