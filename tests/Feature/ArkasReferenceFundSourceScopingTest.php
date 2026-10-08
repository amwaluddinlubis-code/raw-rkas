<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Services\ArkasBridgeClient;
use App\Services\ArkasReferenceSynchronizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * R1: synchronizeDerived() harus menyertakan fund_source_id dalam kunci
 * tulis activity_references & business_partners agar data dari sumber dana
 * berbeda tidak saling menimpa.
 */
class ArkasReferenceFundSourceScopingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        FundSource::query()->firstOrCreate(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP Reguler']);
        FundSource::query()->firstOrCreate(['id' => 2], ['code' => 'BOP', 'name' => 'BOP']);
        FiscalYear::query()->firstOrCreate(['id' => 1], [
            'year' => 2026,
            'fund_source' => 'BOSP',
            'fund_source_id' => 1,
        ]);

        // Isolasi antar-test: bersihkan tabel yang ditulis test ini.
        $db = DB::connection('school');
        foreach (['activity_references', 'business_partners', 'arkas_rkas_items', 'arkas_bku_rows'] as $table) {
            $db->table($table)->delete();
        }
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_activity_references_are_scoped_by_fund_source(): void
    {
        $db = DB::connection('school');
        foreach ([1 => 'Kegiatan BOSP', 2 => 'Kegiatan BOP'] as $fundSourceId => $name) {
            $db->table('arkas_rkas_items')->insert([
                'fiscal_year_id' => 1,
                'fund_source_id' => $fundSourceId,
                'source_rapbs_id' => 'RAPBS-'.$fundSourceId,
                'activity_code' => '03.03.07',
                'activity_name' => $name,
                'payload' => json_encode([]),
            ]);
        }

        $service = new ArkasReferenceSynchronizationService(Mockery::mock(ArkasBridgeClient::class));
        $service->synchronizeDerived(FiscalYear::query()->findOrFail(1));

        $rows = $db->table('activity_references')->where('activity_code', '03.03.07')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('Kegiatan BOSP', $rows->firstWhere('fund_source_id', 1)->activity_name);
        $this->assertSame('Kegiatan BOP', $rows->firstWhere('fund_source_id', 2)->activity_name);
    }

    public function test_business_partners_are_scoped_by_fund_source(): void
    {
        $db = DB::connection('school');
        foreach ([1, 2] as $fundSourceId) {
            $db->table('arkas_bku_rows')->insert([
                'fiscal_year_id' => 1,
                'fund_source_id' => $fundSourceId,
                'source_kas_id' => 'KAS-'.$fundSourceId,
                'payload' => json_encode([
                    'NAMA_TOKO' => 'Toko Maju',
                    'NPWP_REKANAN' => '01.234.567.8-901.000',
                    'NO_TELP_TOKO' => '0812'.$fundSourceId,
                    'ALAMAT_TOKO' => 'Jl. Merdeka '.$fundSourceId,
                ]),
            ]);
        }

        $service = new ArkasReferenceSynchronizationService(Mockery::mock(ArkasBridgeClient::class));
        $service->synchronizeDerived(FiscalYear::query()->findOrFail(1));

        $rows = $db->table('business_partners')->where('name', 'Toko Maju')->get();
        $this->assertCount(2, $rows);
        $this->assertSame('08121', $rows->firstWhere('fund_source_id', 1)->phone);
        $this->assertSame('08122', $rows->firstWhere('fund_source_id', 2)->phone);
    }

    public function test_resync_is_idempotent_per_fund_source(): void
    {
        $db = DB::connection('school');
        $db->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RAPBS-1',
            'activity_code' => '03.03.07',
            'activity_name' => 'Kegiatan BOSP',
            'payload' => json_encode([]),
        ]);

        $service = new ArkasReferenceSynchronizationService(Mockery::mock(ArkasBridgeClient::class));
        $year = FiscalYear::query()->findOrFail(1);
        $service->synchronizeDerived($year);
        $service->synchronizeDerived($year);

        $this->assertSame(1, $db->table('activity_references')->where('activity_code', '03.03.07')->count());
    }
}
