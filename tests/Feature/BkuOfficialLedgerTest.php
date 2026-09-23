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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BkuOfficialLedgerTest extends TestCase
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
        $school = School::query()->create(['npsn' => '10260756', 'school_code' => 'SCH-BKU', 'name' => 'SMPN 2 Ranto Baek', 'address' => 'Ranto Baek']);
        DB::connection('school')->table('school_profiles')->insert([
            'fiscal_year_id' => $year->id,
            'principal_name' => 'Kepala Uji',
            'principal_nip' => '198001012000011001',
            'treasurer_name' => 'Bendahara Uji',
            'treasurer_nip' => '198202022002022002',
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

        $this->seedAprilMirror();
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    private function seedMirrorRow(string $key, array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        DB::connection('school')->table('arkas_mirror_kas_umum')->insert([
            'source_key' => $key,
            'payload' => $json,
            'source_hash' => hash('sha256', $json),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedAprilMirror(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-04-07', 'KODE_REKENING' => '5.1.02.02.01.0013'];
        $this->seedMirrorRow('SALDO-BANK', $base + ['TANGGAL_TRANSAKSI' => '2026-04-01', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Bank', 'URAIAN' => 'Saldo Bank Bulan Maret 2026', 'JUMLAH' => 138195000, 'ID_KAS_UMUM' => 'SALDO-BANK']);
        $this->seedMirrorRow('SALDO-TUNAI', $base + ['TANGGAL_TRANSAKSI' => '2026-04-01', 'KATEGORI_BKU' => 'SALDO_AWAL', 'REK_BKU' => 'Saldo Awal Tunai', 'URAIAN' => 'Saldo Tunai Bulan Maret 2026', 'JUMLAH' => 0, 'ID_KAS_UMUM' => 'SALDO-TUNAI']);
        $this->seedMirrorRow('TARIK', $base + ['TANGGAL_TRANSAKSI' => '2026-04-06', 'KATEGORI_BKU' => 'PERGESERAN', 'REK_BKU' => 'Tarik Tunai', 'URAIAN' => 'Tarik Tunai', 'JUMLAH' => 69097500, 'ID_KAS_UMUM' => 'TARIK']);
        $this->seedMirrorRow('GESER', $base + ['TANGGAL_TRANSAKSI' => '2026-04-06', 'KATEGORI_BKU' => 'PERGESERAN', 'REK_BKU' => 'Pergeseran Tunai', 'URAIAN' => 'Pergeseran Uang di Bank', 'JUMLAH' => 69097500, 'ID_KAS_UMUM' => 'GESER', 'PARENT_ID_KAS_UMUM' => 'TARIK']);
        $this->seedMirrorRow('BELANJA-1', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Paket Data', 'JUMLAH' => 2400000, 'NO_BUKTI' => 'BPU01', 'ID_KAS_UMUM' => 'BELANJA-1', 'ID_RAPBS_PERIODE' => 'RP-1']);
        $this->seedMirrorRow('PAJAK-T', $base + ['KATEGORI_BKU' => 'PAJAK', 'REK_BKU' => 'Pajak Belanja Terima', 'URAIAN' => 'Terima PPN', 'JUMLAH' => 264000, 'ID_KAS_UMUM' => 'PAJAK-T', 'PARENT_ID_KAS_UMUM' => 'BELANJA-1']);
        $this->seedMirrorRow('PAJAK-S', $base + ['KATEGORI_BKU' => 'PAJAK', 'REK_BKU' => 'Pajak Belanja Setor', 'URAIAN' => 'Setor PPN', 'JUMLAH' => 264000, 'ID_KAS_UMUM' => 'PAJAK-S', 'PARENT_ID_KAS_UMUM' => 'BELANJA-1']);

        // Rantai kegiatan: rapbs_periode RP-1 → rapbs RB-1 → snapshot RKAS.
        DB::connection('school')->table('arkas_mirror_rapbs_periode')->insert([
            'source_key' => 'RP-1',
            'payload' => json_encode(['id_rapbs_periode' => 'RP-1', 'id_rapbs' => 'RB-1']),
            'source_hash' => hash('sha256', 'RP-1'),
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::connection('school')->table('arkas_rkas_items')->insert([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'source_rapbs_id' => 'RB-1',
            'activity_code' => '07.12.01.',
            'activity_name' => 'Kegiatan Uji',
            'account_code' => '5.1.02.02.01',
            'description' => 'Belanja uji',
            'amount' => 2400000,
            'payload' => json_encode(['source_rapbs_id' => 'RB-1']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Isian operator tertaut baris BELANJA-1.
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'BELANJA-1',
            'payment_description' => 'Pembayaran Paket Data Bulan April',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create([
            'source_item_id' => 'BELANJA-1',
            'item_description' => 'Paket Data Operator 20GB',
        ]);
    }

    public function test_bku_monthly_matches_official_shape_and_balances(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 4);

        $this->assertNotNull($payload);
        $this->assertSame('bku_ledger', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);
        $this->assertSame(
            ['date', 'activity', 'account', 'evidence', 'description', 'incoming', 'outgoing', 'balance'],
            collect($payload['columns'])->pluck('key')->all()
        );
        $this->assertSame('nowrap', collect($payload['columns'])->firstWhere('key', 'account')['type']);

        $rows = $payload['rows'];
        // Saldo awal bank + tunai, tarik, geser, grup BPU01
        // (induk + rincian + terima + setor), jumlah.
        $this->assertCount(9, $rows);
        $this->assertSame(138195000.0, $rows[0]['incoming']);
        $this->assertSame(138195000.0, $rows[0]['balance']);

        $parent = collect($rows)->firstWhere(fn ($row): bool => str_contains((string) ($row['description'] ?? ''), '<strong>'));
        $this->assertNotNull($parent);
        $this->assertSame('bku-parent', $parent['row_class'] ?? null);
        $this->assertSame('BPU01', $parent['evidence']);
        $this->assertSame(2400000.0, $parent['outgoing']);
        $this->assertStringContainsString('Pembayaran Paket Data Bulan April', $parent['description']);
        $this->assertSame('07.12.01.', $parent['activity']);

        $terima = collect($rows)->firstWhere(fn ($row): bool => str_contains((string) ($row['description'] ?? ''), 'Terima PPN'));
        $this->assertNotNull($terima);
        $this->assertSame('BPU01', $terima['evidence']);
        $this->assertSame(264000.0, $terima['incoming']);
        $this->assertSame('', $terima['balance']);

        $child = collect($rows)->firstWhere(fn ($row): bool => str_contains((string) ($row['description'] ?? ''), 'Paket Data Operator 20GB'));
        $this->assertNotNull($child);
        $this->assertStringContainsString('2.400.000', $child['description']);
        $this->assertSame('', $child['outgoing']);
        $this->assertSame('', $child['balance']);

        $jumlah = $rows[array_key_last($rows)];
        $this->assertSame('Jumlah', $jumlah['description']);
        $this->assertEquals(138195000 + 264000 + 69097500, $jumlah['incoming']);
        $this->assertEquals(2400000 + 264000 + 69097500, $jumlah['outgoing']);

        $closing = $payload['bkuClosing'];
        $this->assertEquals(138195000 - 69097500, $closing['bank']);
        $this->assertEquals(69097500 + 264000 - 2400000 - 264000, $closing['cash']);
        $this->assertEquals($closing['bank'] + $closing['cash'], $closing['total']);
        $this->assertEquals($closing['total'], $jumlah['balance']);
    }

    public function test_bku_groups_shared_evidence_into_bold_parent_and_amount_children(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-10', 'KODE_REKENING' => '5.1.02.02.01.0013', 'NO_BUKTI' => 'BPUX1'];
        $this->seedMirrorRow('GX-1', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Baris Satu', 'JUMLAH' => 800000, 'ID_KAS_UMUM' => 'GX-1']);
        $this->seedMirrorRow('GX-2', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Baris Dua', 'JUMLAH' => 800000, 'ID_KAS_UMUM' => 'GX-2']);

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'GX-HEADER',
            'payment_description' => 'Dibayarkan Honorarium Uji',
            'spj_category' => 'HONOR_PEGAWAI',
        ]);
        $transaction->items()->create(['source_item_id' => 'GX-1', 'item_description' => 'Orang Satu']);
        $transaction->items()->create(['source_item_id' => 'GX-2', 'item_description' => 'Orang Dua']);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $rows = $payload['rows'];

        // Induk + 2 anak + jumlah (tanpa saldo awal Mei).
        $this->assertCount(4, $rows);
        $parent = $rows[0];
        $this->assertStringContainsString('<strong>Dibayarkan Honorarium Uji</strong>', $parent['description']);
        $this->assertSame(1600000.0, $parent['outgoing']);
        $this->assertSame(-1600000.0, $parent['balance']);

        $child = $rows[1];
        $this->assertStringContainsString('Orang Satu', $child['description']);
        $this->assertStringContainsString('800.000', $child['description']);
        $this->assertSame('', $child['incoming']);
        $this->assertSame('', $child['outgoing']);
        $this->assertSame('', $child['balance']);
    }

    public function test_bku_available_for_all_period_scopes(): void
    {
        $service = app(SpjPeriodicReportPrintService::class);

        foreach ([['triwulan', 2], ['semester', 1], ['tahunan', null]] as [$scope, $period]) {
            $payload = $service->build($scope, 'bku', $period);
            $this->assertNotNull($payload, "BKU {$scope} tidak terbangun");
            $this->assertSame('bku_ledger', $payload['presentation']);
            $this->assertNotEmpty($payload['rows']);
        }
    }

    public function test_bku_print_route_renders_official_layout(): void
    {
        $response = $this->get('/laporan-periode/triwulan/bku/cetak?periode_laporan=2');

        $response->assertOk();
        $response->assertSee('bku-table', false);
        $response->assertSee('Kode Kegiatan', false);
        $response->assertSee('BUKU KAS UMUM (BKU)', false);
        $response->assertSee('Desa / Kelurahan', false);
        $response->assertSee('Kabupaten / Kota', false);
        $response->assertSee('Jumlah Transaksi', false);
        $response->assertSee('Buku Kas Umum Ditutup', false);
        $response->assertSee('Terdiri Dari', false);
        $response->assertSee('Menyetujui', false);
    }

    public function test_bku_table_layout_is_fixed_with_wrapping_description_only(): void
    {
        $document = file_get_contents(resource_path('views/periodic-reports/partials/document.blade.php'));
        $styles = file_get_contents(resource_path('views/periodic-reports/partials/document-styles.blade.php'));

        $this->assertIsString($document);
        $this->assertIsString($styles);
        $this->assertStringContainsString('bku-table', $document);
        $this->assertStringContainsString('<colgroup>', $document);
        $this->assertStringContainsString('table-layout: fixed', $styles);
        $this->assertStringContainsString('text-overflow: ellipsis', $styles);
        $this->assertStringContainsString('td.stacked', $styles);
        $this->assertStringContainsString('.report-table.bku-table thead th', $styles);
        $this->assertStringContainsString('counter(page)', $styles);
        $this->assertStringContainsString('counter(pages)', $styles);
    }
}
