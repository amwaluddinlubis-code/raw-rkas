<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Services\ArkasMirrorIntegrityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArkasMirrorIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOS', 'name' => 'BOS']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOS', 'fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedRow(string $table, string $key, array $payload): void
    {
        DB::connection('school')->table($table)->insert(['source_key' => $key, 'payload' => json_encode($payload), 'source_hash' => hash('sha256', json_encode($payload)), 'synced_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_reports_orphan_kas_and_period_rows(): void
    {
        $this->seedRow('arkas_mirror_kas_umum', 'K1', ['kategori_bku' => 'BELANJA', 'id_ref_sumber_dana' => '1', 'id_rapbs' => 'R-MISSING', 'jumlah' => 100]);
        $this->seedRow('arkas_mirror_rapbs_periode', 'P1', ['id_rapbs' => 'R-MISSING', 'id_periode' => 81, 'jumlah' => 100]);
        $this->seedRow('arkas_mirror_anggaran', 'A1', ['id_anggaran' => 'A1', 'tahun_anggaran' => '2026', 'id_ref_sumber_dana' => '1', 'is_approve' => '1']);

        $result = app(ArkasMirrorIntegrityService::class)->summarize(2026, 1);
        $byLabel = collect($result['checks'])->keyBy('label');

        $this->assertFalse($result['ok']);
        $this->assertSame(1, $byLabel['Kas tanpa baris RKAS']['count']);
        $this->assertSame(1, $byLabel['Periode RKAS yatim']['count']);
    }

    public function test_flags_kas_from_other_year_as_foreign_context(): void
    {
        $this->seedRow('arkas_mirror_anggaran', 'A2024', ['id_anggaran' => 'A2024', 'tahun_anggaran' => '2024', 'id_ref_sumber_dana' => '1', 'is_approve' => '1']);
        $this->seedRow('arkas_mirror_rapbs', 'R2024', ['id_rapbs' => 'R2024', 'id_anggaran' => 'A2024', 'jumlah' => 100]);
        $this->seedRow('arkas_mirror_kas_umum', 'K1', ['kategori_bku' => 'BELANJA', 'id_ref_sumber_dana' => '1', 'id_rapbs' => 'R2024', 'jumlah' => 100]);

        $result = app(ArkasMirrorIntegrityService::class)->summarize(2026, 1);
        $byLabel = collect($result['checks'])->keyBy('label');

        $this->assertSame(1, $byLabel['Kas tertaut ke RKAS tahun/dana lain (tidak dihitung)']['count']);
        $this->assertSame(0, $byLabel['Kas tanpa baris RKAS']['count']);
    }

    public function test_flags_approved_anggaran_without_rapbs(): void
    {
        $this->seedRow('arkas_mirror_anggaran', 'A1', ['id_anggaran' => 'A1', 'tahun_anggaran' => '2026', 'id_ref_sumber_dana' => '1', 'is_approve' => '1']);

        $result = app(ArkasMirrorIntegrityService::class)->summarize(2026, 1);
        $byLabel = collect($result['checks'])->keyBy('label');

        $this->assertSame(1, $byLabel['Anggaran tersetujui tanpa RKAS']['count']);
    }

    public function test_healthy_scope_has_no_findings(): void
    {
        $this->seedRow('arkas_mirror_anggaran', 'A1', ['id_anggaran' => 'A1', 'tahun_anggaran' => '2026', 'id_ref_sumber_dana' => '1', 'is_approve' => '1']);
        $this->seedRow('arkas_mirror_rapbs', 'R1', ['id_rapbs' => 'R1', 'id_anggaran' => 'A1', 'jumlah' => 100]);
        $this->seedRow('arkas_mirror_rapbs_periode', 'P1', ['id_rapbs' => 'R1', 'id_periode' => 81, 'jumlah' => 100]);
        $this->seedRow('arkas_mirror_kas_umum', 'K1', ['kategori_bku' => 'BELANJA', 'id_ref_sumber_dana' => '1', 'id_rapbs' => 'R1', 'jumlah' => 100]);

        $result = app(ArkasMirrorIntegrityService::class)->summarize(2026, 1);

        $this->assertTrue($result['ok']);
        foreach ($result['checks'] as $check) {
            $this->assertTrue($check['ok']);
            $this->assertSame(0, $check['count']);
        }
    }
}
