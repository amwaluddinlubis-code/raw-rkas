<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ArkasMirrorResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArkasMirrorSourceValueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->actingAs(User::factory()->create(['role' => 'ADMIN']));
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedMirrorRow(string $key, array $payload): void
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

    public function test_transaction_source_aggregates_belanja_and_pajak_children_from_mirror(): void
    {
        $this->seedMirrorRow('KAS-1', ['ID_KAS_UMUM' => 'KAS-1', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-001', 'TANGGAL_TRANSAKSI' => '2026-02-10', 'URAIAN' => 'Belanja ATK', 'KODE_REKENING' => '5.2.1', 'NAMA_TOKO' => 'Toko Maju', 'JUMLAH' => 1000000]);
        $this->seedMirrorRow('KAS-2', ['ID_KAS_UMUM' => 'KAS-2', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-001', 'TANGGAL_TRANSAKSI' => '2026-02-10', 'URAIAN' => 'Belanja ATK', 'KODE_REKENING' => '5.2.1', 'NAMA_TOKO' => 'Toko Maju', 'JUMLAH' => 500000]);
        $this->seedMirrorRow('KAS-3', ['ID_KAS_UMUM' => 'KAS-3', 'KATEGORI_BKU' => 'PAJAK', 'PARENT_ID_KAS_UMUM' => 'KAS-1', 'NO_BUKTI' => 'BK-001', 'JUMLAH' => 110000, 'IS_PPN' => 1]);

        $source = app(ArkasMirrorResolver::class)->transactionSource(['KAS-1', 'KAS-2']);

        $this->assertSame('BK-001', $source['no_bukti']);
        $this->assertSame('Belanja ATK', $source['description']);
        $this->assertSame('Toko Maju', $source['recipient_name']);
        $this->assertEquals(1500000, $source['gross_amount']);
        $this->assertEquals(110000, $source['ppn']);
        $this->assertEquals(110000, $source['tax_total']);
        $this->assertEquals(1390000, $source['net_amount']);
    }

    public function test_transaction_source_filters_tax_children_by_generated_category_column(): void
    {
        $this->seedMirrorRow('KAS-10', [
            'ID_KAS_UMUM' => 'KAS-10',
            'KATEGORI_BKU' => 'BELANJA',
            'NO_BUKTI' => 'BK-010',
            'TANGGAL_TRANSAKSI' => '2026-02-10',
            'JUMLAH' => 1000000,
        ]);
        $this->seedMirrorRow('KAS-11', [
            'ID_KAS_UMUM' => 'KAS-11',
            'KATEGORI_BKU' => 'PAJAK',
            'PARENT_ID_KAS_UMUM' => 'KAS-10',
            'JUMLAH' => 110000,
            'IS_PPN' => 1,
        ]);

        $taxQueries = [];
        DB::listen(function ($query) use (&$taxQueries): void {
            if (str_contains($query->sql, 'arkas_mirror_kas_umum') && str_contains($query->sql, 'sx_kategori')) {
                $taxQueries[] = $query->sql;
            }
        });

        $source = app(ArkasMirrorResolver::class)->transactionSource(['KAS-10']);

        $this->assertNotNull($source);
        $this->assertNotEmpty($taxQueries);
    }

    public function test_transaction_source_counts_backfill_tax_only_once_when_canonical_twin_exists(): void
    {
        $this->seedMirrorRow('KAS-20', ['ID_KAS_UMUM' => 'KAS-20', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-020', 'TANGGAL_TRANSAKSI' => '2026-04-01', 'JUMLAH' => 1000000]);
        $this->seedMirrorRow('MIG-T-20-ppn', ['ID_KAS_UMUM' => 'MIG-T-20-ppn', 'KATEGORI_BKU' => 'PAJAK', 'PARENT_ID_KAS_UMUM' => 'KAS-20', 'NO_BUKTI' => 'BK-020', 'JUMLAH' => 110000, 'IS_PPN' => 1]);
        $this->seedMirrorRow('KAS-21', ['ID_KAS_UMUM' => 'KAS-21', 'KATEGORI_BKU' => 'PAJAK', 'KODE_BKU' => 'PBT', 'PARENT_ID_KAS_UMUM' => 'KAS-20', 'NO_BUKTI' => 'BK-020', 'URAIAN' => 'Terima PPN', 'JUMLAH' => 110000, 'IS_PPN' => 1]);
        $this->seedMirrorRow('KAS-22', ['ID_KAS_UMUM' => 'KAS-22', 'KATEGORI_BKU' => 'PAJAK', 'KODE_BKU' => 'PBS', 'PARENT_ID_KAS_UMUM' => 'KAS-20', 'NO_BUKTI' => 'BK-020', 'URAIAN' => 'Setor PPN', 'JUMLAH' => 110000, 'IS_PPN' => 1]);

        $source = app(ArkasMirrorResolver::class)->transactionSource(['KAS-20']);

        $this->assertNotNull($source);
        $this->assertEquals(110000, $source['ppn']);
        $this->assertEquals(110000, $source['tax_total']);
        $this->assertEquals(890000, $source['net_amount']);
    }

    public function test_transaction_source_keeps_genuine_backfill_shortfall(): void
    {
        $this->seedMirrorRow('KAS-30', ['ID_KAS_UMUM' => 'KAS-30', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-030', 'TANGGAL_TRANSAKSI' => '2026-04-02', 'JUMLAH' => 1000000]);
        $this->seedMirrorRow('MIG-T-30-ppn', ['ID_KAS_UMUM' => 'MIG-T-30-ppn', 'KATEGORI_BKU' => 'PAJAK', 'PARENT_ID_KAS_UMUM' => 'KAS-30', 'NO_BUKTI' => 'BK-030', 'JUMLAH' => 50000, 'IS_PPN' => 1]);

        $source = app(ArkasMirrorResolver::class)->transactionSource(['KAS-30']);

        $this->assertNotNull($source);
        $this->assertEquals(50000, $source['ppn']);
        $this->assertEquals(50000, $source['tax_total']);
    }

    public function test_transaction_source_dedups_backfill_shadow_across_duplicate_parents(): void
    {
        $this->seedMirrorRow('KAS-40', ['ID_KAS_UMUM' => 'KAS-40', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-040', 'TANGGAL_TRANSAKSI' => '2026-05-01', 'JUMLAH' => 2000000]);
        $this->seedMirrorRow('KAS-41', ['ID_KAS_UMUM' => 'KAS-41', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-040', 'TANGGAL_TRANSAKSI' => '2026-05-01', 'JUMLAH' => 2000000]);
        $this->seedMirrorRow('MIG-T-40-ppn', ['ID_KAS_UMUM' => 'MIG-T-40-ppn', 'KATEGORI_BKU' => 'PAJAK', 'PARENT_ID_KAS_UMUM' => 'KAS-40', 'NO_BUKTI' => 'BK-040', 'JUMLAH' => 220000, 'IS_PPN' => 1]);
        $this->seedMirrorRow('KAS-42', ['ID_KAS_UMUM' => 'KAS-42', 'KATEGORI_BKU' => 'PAJAK', 'KODE_BKU' => 'PBT', 'PARENT_ID_KAS_UMUM' => 'KAS-41', 'NO_BUKTI' => 'BK-040', 'URAIAN' => 'Terima PPN', 'JUMLAH' => 220000, 'IS_PPN' => 1]);

        $source = app(ArkasMirrorResolver::class)->transactionSource(['KAS-40', 'KAS-41']);

        $this->assertNotNull($source);
        $this->assertEquals(220000, $source['ppn']);
        $this->assertEquals(220000, $source['tax_total']);
    }

    public function test_transaction_source_value_prefers_mirror_over_local_columns(): void
    {
        $this->seedMirrorRow('KAS-9', ['ID_KAS_UMUM' => 'KAS-9', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-009', 'TANGGAL_TRANSAKSI' => '2026-03-01', 'URAIAN' => 'Uraian Mirror', 'KODE_REKENING' => '5.2.2', 'NAMA_TOKO' => 'Toko Mirror', 'JUMLAH' => 200000]);

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-9',
        ]);
        $transaction->items()->create(['source_item_id' => 'KAS-9', 'item_description' => 'Overlay']);

        $this->assertSame('BK-009', $transaction->sourceValue('no_bukti'));
        $this->assertSame('Uraian Mirror', $transaction->sourceValue('description'));
        $this->assertEquals(200000, $transaction->sourceValue('gross_amount'));

        $item = $transaction->items()->first();
        $this->assertSame('Uraian Mirror', $item->sourceValue('description'));
        $this->assertEquals(200000, $item->sourceValue('amount'));
    }

    public function test_mirror_resolver_is_shared_per_request(): void
    {
        // Cache per-request pada resolver hanya berfungsi bila container
        // mengembalikan instance yang sama selama satu request.
        $this->assertSame(app(ArkasMirrorResolver::class), app(ArkasMirrorResolver::class));
    }

    public function test_repeated_source_value_reads_do_not_requery_mirror(): void
    {
        $this->seedMirrorRow('KAS-9', ['ID_KAS_UMUM' => 'KAS-9', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-009', 'TANGGAL_TRANSAKSI' => '2026-03-01', 'URAIAN' => 'Uraian Mirror', 'KODE_REKENING' => '5.2.2', 'NAMA_TOKO' => 'Toko Mirror', 'JUMLAH' => 200000]);

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-9',
        ]);
        $transaction->items()->create(['source_item_id' => 'KAS-9', 'item_description' => 'Overlay']);

        $this->assertSame('BK-009', $transaction->sourceValue('no_bukti'));

        $mirrorQueries = 0;
        DB::listen(function ($query) use (&$mirrorQueries): void {
            if (str_contains($query->sql, 'arkas_mirror')) {
                $mirrorQueries++;
            }
        });

        $this->assertSame('Uraian Mirror', $transaction->sourceValue('description'));
        $this->assertEquals(200000, $transaction->sourceValue('gross_amount'));
        $this->assertSame('BK-009', $transaction->sourceValue('no_bukti'));

        $this->assertSame(0, $mirrorQueries);
    }

    public function test_source_value_returns_null_when_mirror_missing(): void
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-ORPHAN',
        ]);

        $this->assertNull($transaction->sourceValue('no_bukti'));
        $this->assertNull($transaction->sourceValue('gross_amount'));
    }

    public function test_with_mirror_source_decorates_clone_and_preserves_overlay(): void
    {
        $this->seedMirrorRow('KAS-7', ['ID_KAS_UMUM' => 'KAS-7', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-007', 'TANGGAL_TRANSAKSI' => '2026-04-01', 'URAIAN' => 'Uraian Mirror', 'KODE_REKENING' => '5.2.3', 'NAMA_TOKO' => 'Toko Mirror', 'JUMLAH' => 300000, 'VOLUME' => 2]);

        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => 'KAS-7',
            'payment_description' => 'Overlay Operator',
            'spj_category' => 'BARANG',
        ]);
        $transaction->items()->create(['source_item_id' => 'KAS-7', 'item_description' => 'Overlay Barang']);

        $decorated = $transaction->withMirrorSource();

        $this->assertSame('BK-007', $decorated->no_bukti);
        $this->assertSame('Uraian Mirror', $decorated->description);
        $this->assertEquals(300000, $decorated->gross_amount);
        $this->assertSame('Overlay Operator', $decorated->payment_description);
        $this->assertSame('BARANG', $decorated->spj_category);

        $decoratedItem = $decorated->items->first();
        $this->assertSame('Uraian Mirror', $decoratedItem->description);
        $this->assertSame('Overlay Barang', $decoratedItem->item_description);
        $this->assertEquals(300000, $decoratedItem->amount);

        $this->assertSame('BK-007', $transaction->fresh()->sourceValue('no_bukti'));
    }
}
