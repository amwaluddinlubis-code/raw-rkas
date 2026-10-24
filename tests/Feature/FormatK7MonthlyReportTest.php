<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SpjPeriodicReportPrintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FormatK7MonthlyReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');

        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        $fund = FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP Reguler']);
        $year = FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $school = School::query()->create([
            'npsn' => '10260756',
            'school_code' => 'SCH-K7',
            'name' => 'SMPN 2 Ranto Baek',
            'address' => 'Ranto Baek',
            'desa' => 'Muara Bangko',
            'district' => 'Ranto Baek',
            'regency' => 'Mandailing Natal',
            'province' => 'Sumatera Utara',
        ]);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Kepala Uji',
            'principal_nip' => '197901222008011002',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '199303052019032002',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs(User::factory()->create(['role' => 'ADMIN', 'school_id' => $school->id]));
        $this->withoutMiddleware();
        session()->put([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => $year->id,
            'active_fund_source_id' => $fund->id,
        ]);

        $this->seedKas();
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedMirrorRow(string $table, string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::connection('school')->table($table)->insert([
            'source_key' => $key,
            'payload' => $json,
            'source_hash' => hash('sha256', $json),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedKas(): void
    {
        // Belanja program 3 di Januari 1.000.000 dan Februari 2.500.000.
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-B1', [
            'ID_KAS_UMUM' => 'K7-B1', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU01', 'TANGGAL_TRANSAKSI' => '2026-01-15', 'URAIAN' => 'ATK Januari',
            'KODE_REKENING' => '5.1.02.01.01.0026', 'JUMLAH' => 1000000, 'ID_RAPBS' => 'K7-RA',
        ]);
        $this->seedMirrorRow('arkas_mirror_kas_umum', 'K7-B2', [
            'ID_KAS_UMUM' => 'K7-B2', 'KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar',
            'NO_BUKTI' => 'BPU02', 'TANGGAL_TRANSAKSI' => '2026-02-10', 'URAIAN' => 'ATK Februari',
            'KODE_REKENING' => '5.1.02.01.01.0026', 'JUMLAH' => 2500000, 'ID_RAPBS' => 'K7-RA',
        ]);
        $this->seedMirrorRow('arkas_mirror_rapbs', 'K7-RA', [
            'id_rapbs' => 'K7-RA', 'id_anggaran' => 'ANG-1', 'KODE_KEGIATAN' => '03.03.21',
            'kode_rekening' => '5.1.02.01.01.0026', 'uraian' => 'ATK', 'jumlah' => 3500000,
        ]);

        foreach (['K7-B1', 'K7-B2'] as $kasId) {
            $transaction = Transaction::query()->create([
                'fiscal_year_id' => 1,
                'fund_source_id' => 1,
                'id_kas_umum' => $kasId,
            ]);
            $transaction->items()->create([
                'source_item_id' => $kasId,
                'item_description' => 'Item '.$kasId,
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function k7Row(array $payload, string $label): array
    {
        foreach ($payload['k7']['rows'] as $row) {
            if ($row['label'] === $label) {
                return $row;
            }
        }

        $this->fail('Baris K7 tidak ditemukan: '.$label);
    }

    public function test_k7_monthly_usage_counts_only_selected_month(): void
    {
        $february = app(SpjPeriodicReportPrintService::class)->build('bulan', 'format_k7', 2);

        $this->assertSame('k7', $february['presentation']);
        $this->assertSame(2500000.0, $this->k7Row($february, 'Total Penggunaan Dana')['amounts']['jumlah']);

        $january = app(SpjPeriodicReportPrintService::class)->build('bulan', 'format_k7', 1);

        $this->assertSame(1000000.0, $this->k7Row($january, 'Total Penggunaan Dana')['amounts']['jumlah']);
    }

    public function test_k7_monthly_print_route_renders(): void
    {
        $response = $this->get('/laporan-periode/bulan/format_k7/cetak?periode_laporan=2');

        $response->assertOk();
        $html = (string) $response->getContent();
        $this->assertStringContainsString('Formulir Bos-K7', $html);
        $this->assertStringContainsString('Februari 2026', $html);
    }
}
