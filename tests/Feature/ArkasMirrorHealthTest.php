<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Services\ArkasMirrorHealthService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArkasMirrorHealthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        DB::connection('school')->table('fund_sources')->updateOrInsert(['id' => 1], ['code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    public function test_detects_and_repairs_stale_mirror_rows(): void
    {
        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_kas_id' => 'KAS-NEW',
            'category' => 'BELANJA', 'amount' => 500000, 'payload' => json_encode([]),
        ]);
        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_kas_id' => 'TAX-1',
            'category' => 'PAJAK', 'amount' => 50000, 'payload' => json_encode([]),
        ]);
        $this->seedMirrorKas('KAS-NEW', ['ID_KAS_UMUM' => 'KAS-NEW', 'ID_REF_SUMBER_DANA' => 1, 'KATEGORI_BKU' => 'BELANJA', 'TANGGAL_TRANSAKSI' => '2026-03-11', 'JUMLAH' => 500000]);
        $this->seedMirrorKas('KAS-OLD', ['ID_KAS_UMUM' => 'KAS-OLD', 'ID_REF_SUMBER_DANA' => 1, 'KATEGORI_BKU' => 'BELANJA', 'TANGGAL_TRANSAKSI' => '2026-03-11', 'JUMLAH' => 500000]);
        $this->seedMirrorKas('TAX-1', ['ID_KAS_UMUM' => 'TAX-1', 'ID_REF_SUMBER_DANA' => 1, 'KATEGORI_BKU' => 'PAJAK', 'TANGGAL_TRANSAKSI' => '2026-03-11', 'JUMLAH' => 50000]);

        $service = app(ArkasMirrorHealthService::class);
        $report = $service->check();

        $this->assertFalse($report['ok']);
        $this->assertCount(2, $report['scopes']);
        $this->assertSame(2026, $report['scopes'][0]['year']);
        $this->assertSame(1, $report['scopes'][0]['fund']);
        $this->assertSame('BELANJA', $report['scopes'][0]['category']);
        $this->assertSame(2, $report['scopes'][0]['mirror_n']);
        $this->assertSame(1, $report['scopes'][0]['bku_n']);
        $this->assertSame(1, $report['scopes'][0]['stale_n']);
        $this->assertSame(500000.0, $report['scopes'][0]['stale_sum']);

        $deleted = $service->repair();

        $this->assertCount(1, $deleted);
        $this->assertSame(1, $deleted[0]['deleted_n']);
        $this->assertSame('BELANJA', $deleted[0]['category']);
        $this->assertSame(['KAS-NEW', 'TAX-1'], DB::connection('school')->table('arkas_mirror_kas_umum')->orderBy('source_key')->pluck('source_key')->all());
        $this->assertTrue($service->check()['ok']);
    }

    public function test_unscoped_rows_are_reported_but_never_deleted(): void
    {
        DB::connection('school')->table('arkas_bku_rows')->insert([
            'fiscal_year_id' => 1, 'fund_source_id' => 1, 'source_kas_id' => 'KAS-NEW',
            'category' => 'BELANJA', 'amount' => 500000, 'payload' => json_encode([]),
        ]);
        $this->seedMirrorKas('KAS-NEW', ['ID_KAS_UMUM' => 'KAS-NEW', 'ID_REF_SUMBER_DANA' => 1, 'KATEGORI_BKU' => 'BELANJA', 'TANGGAL_TRANSAKSI' => '2026-03-11', 'JUMLAH' => 500000]);
        $this->seedMirrorKas('KAS-NODATE', ['ID_KAS_UMUM' => 'KAS-NODATE', 'ID_REF_SUMBER_DANA' => 1, 'KATEGORI_BKU' => 'BELANJA', 'JUMLAH' => 100000]);

        $service = app(ArkasMirrorHealthService::class);
        $report = $service->check();

        $this->assertSame(1, $report['unscoped_n']);
        $this->assertTrue($report['ok']);

        $this->assertSame([], $service->repair());
        $this->assertTrue(DB::connection('school')->table('arkas_mirror_kas_umum')->where('source_key', 'KAS-NODATE')->exists());
    }

    private function seedMirrorKas(string $key, array $payload): void
    {
        DB::connection('school')->table('arkas_mirror_kas_umum')->insert([
            'source_key' => $key,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'source_hash' => hash('sha256', json_encode($payload)),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
