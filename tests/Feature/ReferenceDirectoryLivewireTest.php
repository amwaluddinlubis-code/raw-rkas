<?php

namespace Tests\Feature;

use App\Livewire\ReferenceDirectory;
use App\Models\User;
use App\Services\ReferenceLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

class ReferenceDirectoryLivewireTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['arkas_mirror_ref_rekening', 'arkas_mirror_ref_acuan_barang', 'arkas_mirror_ref_kode'] as $table) {
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

        DB::table('arkas_mirror_ref_kode')->insert([
            [
                'source_key' => 'prog-1',
                'payload' => json_encode(['id_ref_kode' => 'REF-P1', 'id_kode' => '01', 'uraian_kode' => 'Program Pengembangan Standar Isi', 'id_level_kode' => '1', 'tahun' => '2026']),
                'source_hash' => 'p1',
            ],
            [
                'source_key' => 'sub-1',
                'payload' => json_encode(['id_ref_kode' => 'REF-S1', 'id_kode' => '01.01', 'uraian_kode' => 'Penyusunan Kurikulum', 'id_level_kode' => '2', 'parent_kode' => 'REF-P1', 'tahun' => '2026']),
                'source_hash' => 's1',
            ],
            [
                'source_key' => 'act-1',
                'payload' => json_encode(['id_ref_kode' => 'REF-A1', 'id_kode' => '01.01.01', 'uraian_kode' => 'Workshop Kurikulum Satuan Pendidikan', 'id_level_kode' => '3', 'parent_kode' => 'REF-S1', 'tahun' => '2026']),
                'source_hash' => 'a1',
            ],
        ]);
    }

    public function test_component_mounts_with_default_tab_and_available_year(): void
    {
        Livewire::test(ReferenceDirectory::class)
            ->assertSet('tab', ReferenceLookupService::TAB_ACCOUNTS)
            ->assertSet('tahun', '2026')
            ->assertSee('Belanja Alat Tulis')
            ->assertSee('Honorarium Panitia');
    }

    public function test_tab_switching_loads_prices_and_activities(): void
    {
        Livewire::test(ReferenceDirectory::class)
            ->call('setTab', ReferenceLookupService::TAB_PRICES)
            ->assertSet('tab', ReferenceLookupService::TAB_PRICES)
            ->assertSee('Kertas HVS A4')
            ->assertSee('Cat Tembok')
            ->call('setTab', ReferenceLookupService::TAB_ACTIVITIES)
            ->assertSet('tab', ReferenceLookupService::TAB_ACTIVITIES)
            ->assertSee('Program Pengembangan Standar Isi')
            ->assertSee('Penyusunan Kurikulum')
            ->assertSee('Workshop Kurikulum Satuan Pendidikan');
    }

    public function test_search_filters_accounts_reactively(): void
    {
        Livewire::test(ReferenceDirectory::class)
            ->set('q', 'honor')
            ->assertSee('Honorarium Panitia')
            ->assertDontSee('Belanja Alat Tulis');
    }

    public function test_search_filters_prices_reactively(): void
    {
        Livewire::test(ReferenceDirectory::class, ['tab' => ReferenceLookupService::TAB_PRICES])
            ->set('q', 'cat')
            ->assertSee('Cat Tembok')
            ->assertDontSee('Kertas HVS A4');
    }

    public function test_activity_hierarchy_filters_and_options(): void
    {
        Livewire::test(ReferenceDirectory::class, ['tab' => ReferenceLookupService::TAB_ACTIVITIES])
            ->set('program', '01')
            ->assertSet('program', '01')
            ->set('sub', '01.01')
            ->assertSet('sub', '01.01')
            ->set('kegiatan', '01.01.01')
            ->assertSee('Workshop Kurikulum Satuan Pendidikan')
            ->call('resetFilters')
            ->assertSet('program', '')
            ->assertSet('sub', '')
            ->assertSet('kegiatan', '')
            ->assertSet('q', '');
    }

    public function test_per_page_updates_pagination_limit(): void
    {
        Livewire::test(ReferenceDirectory::class)
            ->set('perPage', 25)
            ->assertSet('perPage', 25);
    }

    public function test_authenticated_route_renders_livewire_component(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]));

        $this->withoutMiddleware()
            ->get(route('references.index', ['tab' => 'rekening', 'tahun' => '2026']))
            ->assertOk()
            ->assertSeeLivewire(ReferenceDirectory::class)
            ->assertSee('Belanja Alat Tulis')
            ->assertSee('Honorarium Panitia');
    }
}
