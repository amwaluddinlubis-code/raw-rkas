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

    public function test_cash_ledger_uses_bku_shape_with_cash_only_balance(): void
    {
        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'buku_pembantu_kas', 4);

        $this->assertNotNull($payload);
        $this->assertSame('cash_ledger', $payload['presentation']);
        $this->assertSame('landscape', $payload['orientation']);
        $this->assertSame('folio', $payload['paper']);
        $this->assertSame(
            ['date', 'activity', 'account', 'evidence', 'description', 'incoming', 'outgoing', 'balance'],
            collect($payload['columns'])->pluck('key')->all()
        );

        $rows = $payload['rows'];
        $descriptions = collect($rows)->pluck('description')->map(fn ($value): string => strip_tags((string) $value))->all();
        // Tanpa sisi bank: tanpa Saldo Awal Bank dan Tarik Tunai.
        $this->assertCount(7, $rows);
        $this->assertFalse(collect($descriptions)->contains(fn (string $text): bool => str_contains($text, 'Saldo Bank Bulan Maret')));
        $this->assertFalse(collect($descriptions)->contains(fn (string $text): bool => str_contains($text, 'Tarik Tunai')));

        // Saldo berjalan hanya sisi tunai: 0 + 69.097.500 - 2.400.000 + 264.000 - 264.000.
        $this->assertSame(0.0, $rows[0]['incoming']);
        $this->assertSame(0.0, $rows[0]['balance']);
        $this->assertSame(69097500.0, $rows[1]['incoming']);
        $this->assertSame(69097500.0, $rows[1]['balance']);

        $jumlah = $rows[array_key_last($rows)];
        $this->assertSame('Jumlah', $jumlah['description']);
        $this->assertEquals(69097500 + 264000, $jumlah['incoming']);
        $this->assertEquals(2400000 + 264000, $jumlah['outgoing']);
        $this->assertSame(66697500.0, $jumlah['balance']);

        $closing = $payload['bkuClosing'];
        $this->assertSame(0.0, $closing['bank']);
        $this->assertSame(66697500.0, $closing['cash']);
        $this->assertSame(66697500.0, $closing['total']);
    }

    public function test_cash_ledger_print_route_renders_bku_shaped_layout(): void
    {
        $response = $this->get('/laporan-periode/bulan/buku_pembantu_kas/cetak?periode_laporan=4');

        $response->assertOk();
        $response->assertSee('bku-table', false);
        $response->assertSee('BUKU PEMBANTU KAS', false);
        $response->assertSee('Buku Pembantu Kas Ditutup', false);
        $response->assertSee('Saldo Kas Tunai', false);
        $response->assertSee('Menyetujui', false);
        $response->assertDontSee('Mengetahui,', false);
        $this->assertSame(1, substr_count((string) $response->getContent(), '<table class="signature-table'));
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
        // Blok generik "Mengetahui" tidak boleh ikut tampil (tanda tangan ganda).
        $response->assertDontSee('Mengetahui,', false);
        $response->assertDontSee('Bendahara BOSP', false);
        $this->assertSame(1, substr_count((string) $response->getContent(), '<table class="signature-table'));
    }

    public function test_bku_sisa_belanja_groups_and_counts_like_base_form(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-12', 'KODE_REKENING' => '5.1.02.01.01.0052', 'NO_BUKTI' => 'BPUSISA1'];
        // Baris pertama grup sengaja beruraian kosong (kasus baris duplikat
        // pasca-sinkronisasi): parent tetap wajib menampilkan uraian
        // non-kosong pertama, bukan sel kosong.
        $this->seedMirrorRow('SISA-1', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar Sisa', 'URAIAN' => '', 'JUMLAH' => 500000, 'ID_KAS_UMUM' => 'SISA-1']);
        $this->seedMirrorRow('SISA-2', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Belanja Sisa Real', 'JUMLAH' => 300000, 'ID_KAS_UMUM' => 'SISA-2']);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $rows = $payload['rows'];

        $parents = collect($rows)->filter(fn ($row): bool => ($row['evidence'] ?? '') === 'BPUSISA1' && ($row['row_class'] ?? '') === 'bku-parent')->values();
        $this->assertCount(1, $parents, 'Varian Sisa wajib menyatu dalam satu grup bukti.');
        $this->assertSame(800000.0, $parents->first()['outgoing']);
        $this->assertStringContainsString('Belanja Sisa Real', strip_tags((string) $parents->first()['description']));
        $this->assertDoesNotMatchRegularExpression('/<strong>\s*<\/strong>/', (string) $parents->first()['description']);
    }

    public function test_bku_tax_row_falls_back_to_uraian_pajak(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-13', 'KODE_REKENING' => '5.1.02.02.01.0013'];
        $this->seedMirrorRow('BELANJA-PJK', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Belanja Kena Pajak', 'JUMLAH' => 1000000, 'NO_BUKTI' => 'BPUPJK1', 'ID_KAS_UMUM' => 'BELANJA-PJK']);
        // Baris pajak ARKAS menyimpan keterangan di URAIAN_PAJAK dengan
        // URAIAN kosong — sebelumnya tampil sebagai baris tanpa uraian.
        $this->seedMirrorRow('PAJAK-PJK', $base + ['KATEGORI_BKU' => 'PAJAK', 'REK_BKU' => 'Pajak Belanja Terima', 'URAIAN' => '', 'URAIAN_PAJAK' => 'Terima PPN Brosur', 'JUMLAH' => 110000, 'ID_KAS_UMUM' => 'PAJAK-PJK', 'PARENT_ID_KAS_UMUM' => 'BELANJA-PJK']);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $descriptions = collect($payload['rows'])->pluck('description')->map(fn ($value): string => strip_tags((string) $value))->all();

        $this->assertTrue(collect($descriptions)->contains(fn (string $text): bool => str_contains($text, 'Terima PPN Brosur')));
    }

    public function test_bku_backfill_tax_shadow_is_deduped_against_canonical(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-14', 'KODE_REKENING' => '5.1.02.01.01.0024', 'NO_BUKTI' => 'BPUSHD1'];
        $this->seedMirrorRow('BELANJA-SHD', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Belanja Asli', 'JUMLAH' => 1000000, 'ID_KAS_UMUM' => 'BELANJA-SHD']);
        $this->seedMirrorRow('PBT-SHD', $base + ['KATEGORI_BKU' => 'PAJAK', 'REK_BKU' => 'Pajak Belanja Terima', 'URAIAN' => 'Terima PPN', 'JUMLAH' => 110000, 'ID_KAS_UMUM' => 'PBT-SHD', 'PARENT_ID_KAS_UMUM' => 'BELANJA-SHD', 'IS_PPN' => 1]);
        // Bayangan backfill (spj:backfill-mirror-from-local): tanpa REK dan
        // uraian, nominal sama persis dengan baris kanonis — sebelumnya
        // tampil sebagai baris kembar beruraian kosong.
        $this->seedMirrorRow('MIG-T-99-ppn', $base + ['KATEGORI_BKU' => 'PAJAK', 'JUMLAH' => 110000, 'ID_KAS_UMUM' => 'MIG-T-99-ppn', 'PARENT_ID_KAS_UMUM' => 'SOME-TXN', 'IS_PPN' => 1]);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $rows = collect($payload['rows']);

        $group = $rows->filter(fn ($row): bool => ($row['evidence'] ?? '') === 'BPUSHD1')->values();
        // Induk + rincian belanja + terima (tanpa baris bayangan).
        $this->assertCount(3, $group);
        $terima = $group->filter(fn ($row): bool => str_contains(strip_tags((string) ($row['description'] ?? '')), 'Terima PPN'))->values();
        $this->assertCount(1, $terima);
        $this->assertSame(110000.0, $terima->first()['incoming']);
        foreach ($group as $row) {
            $this->assertNotSame('', trim(strip_tags((string) ($row['description'] ?? ''))), 'Tidak boleh ada uraian kosong pada grup bukti.');
        }

        $jumlah = $payload['rows'][array_key_last($payload['rows'])];
        $this->assertSame(110000.0, $jumlah['incoming'], 'Bayangan tidak boleh terhitung ganda.');
    }

    public function test_bku_backfill_tax_shortfall_is_counted_as_terima(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-15', 'KODE_REKENING' => '5.1.02.01.01.0024', 'NO_BUKTI' => 'BPUMRN1'];
        $this->seedMirrorRow('BELANJA-MRN', $base + ['KATEGORI_BKU' => 'BELANJA', 'REK_BKU' => 'Kas Keluar', 'URAIAN' => 'Belanja Murni', 'JUMLAH' => 500000, 'ID_KAS_UMUM' => 'BELANJA-MRN']);
        // Shortfall murni: tanpa padanan kanonis, tetap tercatat Terima.
        $this->seedMirrorRow('MIG-T-98-ppn', $base + ['KATEGORI_BKU' => 'PAJAK', 'JUMLAH' => 55000, 'ID_KAS_UMUM' => 'MIG-T-98-ppn', 'PARENT_ID_KAS_UMUM' => 'TXN-98', 'IS_PPN' => 1]);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $rows = collect($payload['rows']);

        $group = $rows->filter(fn ($row): bool => ($row['evidence'] ?? '') === 'BPUMRN1')->values();
        $this->assertCount(3, $group);
        $terima = $group->filter(fn ($row): bool => str_contains(strip_tags((string) ($row['description'] ?? '')), 'Terima PPN'))->values();
        $this->assertCount(1, $terima);
        $this->assertSame(55000.0, $terima->first()['incoming']);
        $this->assertStringContainsString('(data lokal)', strip_tags((string) ($terima->first()['description'] ?? '')));

        $jumlah = $payload['rows'][array_key_last($payload['rows'])];
        $this->assertSame(55000.0, $jumlah['incoming']);
    }

    public function test_bku_bank_interest_and_tax_are_counted_on_bank_side(): void
    {
        $base = ['TANGGAL_TRANSAKSI' => '2026-05-16', 'KODE_REKENING' => ''];
        $this->seedMirrorRow('BUNGA-1', $base + ['KATEGORI_BKU' => 'BUNGA_BANK', 'REK_BKU' => 'Bunga Bank', 'URAIAN' => 'Bunga Bank', 'JUMLAH' => 50000, 'ID_KAS_UMUM' => 'BUNGA-1']);
        $this->seedMirrorRow('PBUNGA-1', $base + ['KATEGORI_BKU' => 'PAJAK_BANK', 'REK_BKU' => 'Pajak Bunga', 'URAIAN' => 'Pajak Bunga', 'JUMLAH' => 10000, 'ID_KAS_UMUM' => 'PBUNGA-1']);

        $payload = app(SpjPeriodicReportPrintService::class)->build('bulan', 'bku', 5);
        $rows = collect($payload['rows']);

        $this->assertCount(3, $rows);
        $bunga = $rows->firstWhere(fn ($row): bool => str_contains(strip_tags((string) ($row['description'] ?? '')), 'Bunga Bank'));
        $this->assertNotNull($bunga);
        $this->assertSame(50000.0, $bunga['incoming']);
        $pajak = $rows->firstWhere(fn ($row): bool => str_contains(strip_tags((string) ($row['description'] ?? '')), 'Pajak Bunga'));
        $this->assertNotNull($pajak);
        $this->assertSame(10000.0, $pajak['outgoing']);

        $jumlah = $payload['rows'][array_key_last($payload['rows'])];
        $this->assertSame(50000.0, $jumlah['incoming']);
        $this->assertSame(10000.0, $jumlah['outgoing']);
        $this->assertSame(40000.0, $jumlah['balance']);
        $this->assertSame(40000.0, $payload['bkuClosing']['bank']);
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
