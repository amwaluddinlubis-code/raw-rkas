<?php

namespace Tests\Feature;

use App\Services\BusinessPartnerNormalizer;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusinessPartnerNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    private function insert(array $row): int
    {
        return DB::connection('school')->table('business_partners')->insertGetId($row + [
            'is_business_entity' => false,
            'is_arkas_synced' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $this->insert(['name' => 'AHMAD RIPAI', 'npwp' => null]);
        $this->insert(['name' => 'Ahmad Ripai', 'npwp' => null]);

        $report = app(BusinessPartnerNormalizer::class)->normalize(true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame(1, $report['groups']);
        $this->assertSame(0, $report['merged']);
        $this->assertSame(2, DB::connection('school')->table('business_partners')->count());
    }

    public function test_fuse_merges_case_and_npwp_format_variants(): void
    {
        $this->insert(['name' => 'AHMAD RIPAI', 'npwp' => null]);
        $this->insert(['name' => 'Ahmad Ripai', 'npwp' => null, 'phone' => '0812']);
        $this->insert(['name' => 'PT. MAJU BERSAMA MADINA', 'npwp' => '20.770.583.1-118.000']);
        $this->insert(['name' => 'PT. MAJU BERSAMA MADINA', 'npwp' => '207705831118000']);

        $report = app(BusinessPartnerNormalizer::class)->normalize(false);

        $this->assertSame(2, $report['groups']);
        $this->assertSame(2, $report['merged']);
        $rows = DB::connection('school')->table('business_partners')->orderBy('id')->get();
        $this->assertCount(2, $rows);
        // Phone backfilled from the dropped variant.
        $this->assertSame('0812', $rows->firstWhere('name', 'Ahmad Ripai')->phone
            ?? $rows->firstWhere('name', 'AHMAD RIPAI')->phone);
        // Same digit sequence: the formatted NPWP variant wins.
        $this->assertSame('20.770.583.1-118.000', $rows->firstWhere('name', 'PT. MAJU BERSAMA MADINA')->npwp);
    }

    public function test_manual_rows_are_never_deleted(): void
    {
        $manual = $this->insert(['name' => 'Toko Manual', 'npwp' => null, 'is_arkas_synced' => false]);
        $this->insert(['name' => 'TOKO MANUAL', 'npwp' => null]);

        $report = app(BusinessPartnerNormalizer::class)->normalize(false);

        $this->assertSame(1, $report['groups']);
        $this->assertSame(1, $report['merged']);
        $this->assertSame($manual, $report['pairs'][0]['keep']);
        $remaining = DB::connection('school')->table('business_partners')->get();
        $this->assertCount(1, $remaining);
        $this->assertFalse((bool) $remaining->first()->is_arkas_synced);
    }
}
