<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjPackageTransactionBoundaryTest extends TestCase
{
    use SeedsArkasMirror;

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

        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    public function test_updating_spj_package_does_not_change_transaction_tax_values(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BKU-TAX-001',
            'transaction_date' => '2026-01-15',
            'gross_amount' => 1000,
            'ppn' => 100,
            'ppn_rate' => 10,
            'pph21' => 50,
            'pph21_rate' => 5,
            'pph22' => 0,
            'pph22_rate' => 0,
            'pph23' => 0,
            'pph23_rate' => 0,
            'pph4' => 25,
            'pph4_rate' => 2.5,
            'sspd' => 20,
            'sspd_rate' => 2,
            'tax_total' => 195,
            'net_amount' => 805,
            'spj_category' => 'BARANG',
            'payment_method' => 'tunai',
        ]);

        $this->mirrorItem($transaction, [
            'description' => 'Kertas',
            'item_description' => 'Kertas HVS A4',
            'quantity' => 1,
            'unit' => 'rim',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);

        $package = $transaction->spjPackage()->create([
            'quarter_code' => 'TW-1',
            'semester_code' => 'SEM-I',
            'status' => 'DRAFT',
        ]);

        $response = $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->put(route('spj.update', $package->id), [
                'spj_category' => 'BARANG',
                'payment_description' => 'Pembelian kertas untuk kegiatan sekolah',
                'payment_method' => 'tunai',
                'receipt_recipient_name' => 'Toko Kertas',
                // Field lama tetap boleh terkirim dari DOM, tetapi harus diabaikan backend Paket SPJ.
                'ppn_rate' => 99,
                'pph21_rate' => 99,
                'pph22_rate' => 99,
                'pph23_rate' => 99,
                'pph4_rate' => 99,
                'sspd_rate' => 99,
            ]);

        $response->assertSessionHasNoErrors();

        $transaction->refresh();
        $this->assertEquals(100, $transaction->sourceValue('ppn'));
        $this->assertEquals(50, $transaction->sourceValue('pph21'));
        $this->assertEquals(0, $transaction->sourceValue('pph22'));
        $this->assertEquals(0, $transaction->sourceValue('pph23'));
        $this->assertEquals(25, $transaction->sourceValue('pph4'));
        $this->assertEquals(20, $transaction->sourceValue('sspd'));
        $this->assertEquals(195, $transaction->sourceValue('tax_total'));
        $this->assertEquals(805, $transaction->sourceValue('net_amount'));
        $this->assertSame('10.0000', $transaction->ppn_rate);
        $this->assertSame('5.0000', $transaction->pph21_rate);
        $this->assertSame('2.5000', $transaction->pph4_rate);
        $this->assertSame('2.0000', $transaction->sspd_rate);
    }
}
