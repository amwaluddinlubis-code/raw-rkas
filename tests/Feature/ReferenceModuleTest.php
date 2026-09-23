<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ReferenceLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReferenceModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['arkas_mirror_ref_rekening', 'arkas_mirror_ref_acuan_barang'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function ($blueprint): void {
                    $blueprint->id();
                    $blueprint->string('source_key', 255)->unique();
                    $blueprint->longText('payload');
                    $blueprint->string('source_hash', 64)->nullable();
                    $blueprint->timestamp('synced_at')->nullable();
                    $blueprint->timestamps();
                });
            }
            DB::table($table)->delete();
        }

        DB::table('arkas_mirror_ref_rekening')->insert([
            [
                'source_key' => 'rek-1',
                'payload' => json_encode(['kode_rekening' => '5.1.02.01.01.0001', 'rekening' => 'Belanja Alat Tulis', 'tahun' => '2026', 'is_ppn' => '1', 'is_pph21' => '0', 'is_pph22' => '0', 'is_pph23' => '0', 'is_pph4' => '0', 'is_sspd' => '0']),
                'source_hash' => 'a',
            ],
            [
                'source_key' => 'rek-2',
                'payload' => json_encode(['kode_rekening' => '5.2.1.01.002', 'rekening' => 'Honorarium Panitia', 'tahun' => '2026', 'is_ppn' => '0', 'is_pph21' => '1', 'is_pph22' => '0', 'is_pph23' => '0', 'is_pph4' => '0', 'is_sspd' => '0']),
                'source_hash' => 'b',
            ],
            [
                'source_key' => 'rek-3',
                'payload' => json_encode(['kode_rekening' => '5.1.02.01.01.0002', 'rekening' => 'Belanja Kertas Lama', 'tahun' => '2025', 'is_ppn' => '0', 'is_pph21' => '0', 'is_pph22' => '0', 'is_pph23' => '0', 'is_pph4' => '0', 'is_sspd' => '0']),
                'source_hash' => 'c',
            ],
        ]);

        DB::table('arkas_mirror_ref_acuan_barang')->insert([
            [
                'source_key' => 'brg-1',
                'payload' => json_encode(['id_barang' => 'B1', 'kode_rekening' => '5.1.02.01.01.0001', 'nama_barang' => 'Kertas HVS A4', 'satuan' => 'Rim', 'harga_barang' => '65000', 'batas_atas' => '65000', 'tahun' => '2026']),
                'source_hash' => 'd',
            ],
            [
                'source_key' => 'brg-2',
                'payload' => json_encode(['id_barang' => 'B2', 'kode_rekening' => '5.1.02.01.01.0001', 'nama_barang' => 'Cat Tembok', 'satuan' => 'Kaleng', 'harga_barang' => '150000', 'batas_atas' => '150000', 'tahun' => '2026']),
                'source_hash' => 'e',
            ],
        ]);
    }

    public function test_available_years_lists_mirror_years_descending(): void
    {
        $years = app(ReferenceLookupService::class)->availableYears();

        $this->assertSame(['2026', '2025'], $years);
    }

    public function test_accounts_filtered_by_year_and_search(): void
    {
        $service = app(ReferenceLookupService::class);

        $page = $service->paginateAccounts('2026', '', 15);
        $this->assertSame(2, $page->total());

        $filtered = $service->paginateAccounts('2026', 'honor', 15);
        $this->assertSame(1, $filtered->total());
        $this->assertSame('5.2.1.01.002', $filtered->items()[0]->kode_rekening);
    }

    public function test_prices_filtered_by_search(): void
    {
        $service = app(ReferenceLookupService::class);

        $filtered = $service->paginatePriceReferences('2026', 'cat', 15);
        $this->assertSame(1, $filtered->total());
        $this->assertSame('Cat Tembok', $filtered->items()[0]->nama_barang);
    }

    public function test_prices_merge_same_name_and_unit(): void
    {
        DB::table('arkas_mirror_ref_acuan_barang')->insert([
            [
                'source_key' => 'brg-3',
                'payload' => json_encode(['id_barang' => 'B3', 'kode_rekening' => '5.1.02.01.01.0001', 'nama_barang' => 'Bel Listrik', 'satuan' => 'Buah', 'harga_barang' => '50000', 'batas_atas' => '50000', 'tahun' => '2026']),
                'source_hash' => 'f',
            ],
            [
                'source_key' => 'brg-4',
                'payload' => json_encode(['id_barang' => 'B4', 'kode_rekening' => '5.1.02.01.01.0001', 'nama_barang' => 'Bel Listrik', 'satuan' => 'Buah', 'harga_barang' => '55000', 'batas_atas' => '55000', 'tahun' => '2026']),
                'source_hash' => 'g',
            ],
        ]);

        $service = app(ReferenceLookupService::class);
        $page = $service->paginatePriceReferences('2026', 'bel listrik', 15);

        $this->assertSame(1, $page->total());
        $row = $page->items()[0];
        $this->assertSame('Bel Listrik', $row->nama_barang);
        $this->assertSame(2, (int) $row->varian);
        $this->assertEquals(50000, (float) $row->harga_min);
        $this->assertEquals(55000, (float) $row->harga_max);
    }

    public function test_reference_page_renders_both_tabs(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->withoutMiddleware()
            ->get(route('references.index', ['tab' => 'rekening', 'tahun' => '2026']))
            ->assertOk()
            ->assertSee('Belanja Alat Tulis', false)
            ->assertSee('Honorarium Panitia', false);

        $this->withoutMiddleware()
            ->get(route('references.index', ['tab' => 'harga', 'tahun' => '2026']))
            ->assertOk()
            ->assertSee('Kertas HVS A4', false)
            ->assertSee('Cat Tembok', false);

        $this->withoutMiddleware()
            ->get(route('references.index', ['tab' => 'harga', 'tahun' => '2026', 'q' => 'cat']))
            ->assertOk()
            ->assertSee('Cat Tembok', false)
            ->assertDontSee('Kertas HVS A4', false);
    }

    public function test_reference_view_uses_canonical_primitives(): void
    {
        $blade = (string) file_get_contents(resource_path('views/references/index.blade.php'));

        $this->assertStringContainsString('<x-page-header', $blade);
        $this->assertStringContainsString('<x-ui.table', $blade);
        $this->assertStringContainsString('<x-ui.server-pagination', $blade);
        $this->assertStringContainsString('<x-ui.input', $blade);
        $this->assertStringContainsString('<x-ui.select', $blade);
        $this->assertStringNotContainsString('<style>', $blade);
    }
}
