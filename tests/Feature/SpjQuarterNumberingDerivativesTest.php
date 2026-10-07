<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\SpjMaintenance;
use App\Models\SpjPackage;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class SpjQuarterNumberingDerivativesTest extends TestCase
{
    use SeedsArkasMirror;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', ['--database' => 'school', '--path' => 'database/migrations/school', '--force' => true]);
        FundSource::query()->create(['id' => 1, 'code' => 'BOSP', 'name' => 'BOSP']);
        FiscalYear::query()->create(['id' => 1, 'year' => 2026, 'fund_source' => 'BOSP', 'fund_source_id' => 1]);
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
        $school = School::query()->create([
            'npsn' => '102'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
            'school_code' => 'SDN-DER-'.uniqid(),
            'name' => 'Sekolah Uji Derivatif',
        ]);
        $user = User::factory()->create(['role' => 'ADMIN', 'school_id' => $school->id]);
        $this->actingAs($user)->withSession([
            'active_school_id' => $school->id,
            'active_fiscal_year_id' => 1,
            'active_fund_source_id' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    private function readyBarangPackage(): SpjPackage
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-DER-'.uniqid(),
            'transaction_date' => '2026-01-15',
            'gross_amount' => 1000,
            'spj_category' => 'BARANG',
            'payment_description' => 'Pembelian barang uji',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Uji',
            'vendor_name' => 'Toko Uji',
            'rkas_date' => '2026-01-01',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
        $item = $this->mirrorItem($transaction, [
            'description' => 'Kertas uji',
            'item_description' => 'Kertas uji',
            'quantity' => 1,
            'unit' => 'rim',
            'unit_price' => 1000,
            'amount' => 1000,
        ]);
        $item->goods()->create([
            'order_date' => '2026-01-10',
            'bap_date' => '2026-01-11',
            'bast_date' => '2026-01-12',
        ]);

        return $transaction->spjPackage()->create(['status' => 'READY']);
    }

    public function test_quarter_numbering_issues_derivative_numbers_for_single_delivery_barang(): void
    {
        $package = $this->readyBarangPackage();
        $this->assertSame(0, $package->transaction->goodsReceipts()->count());

        $response = $this->post(route('spj.quarter-numbering'), ['quarter' => 1]);
        $response->assertSessionHas('success');

        $package = $package->fresh();
        $this->assertSame('NUMBERED', $package->status);
        $this->assertNotNull($package->document_number);

        foreach (['PESANAN', 'BAP', 'BAST'] as $type) {
            $this->assertNotNull(
                $package->documents()->where('document_type', $type)->whereNotNull('document_number')->first()?->document_number,
                "Dokumen {$type} seharusnya bernomor pada penomoran triwulan."
            );
        }

        $goods = $package->transaction->goods()->firstOrFail();
        $this->assertNotNull($goods->order_number);
        $this->assertNotNull($goods->bap_number);
        $this->assertNotNull($goods->bast_number);
    }

    public function test_quarter_numbering_issues_spk_rab_for_pemeliharaan(): void
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BPU-PEL-'.uniqid(),
            'transaction_date' => '2026-02-10',
            'gross_amount' => 5000,
            'spj_category' => 'PEMELIHARAAN',
            'payment_description' => 'Perbaikan atap uji',
            'payment_method' => 'tunai',
            'receipt_recipient_name' => 'Penerima Uji',
            'vendor_name' => 'Tukang Uji',
            'rkas_date' => '2026-02-01',
            'source_status' => 'ACTIVE',
            'requires_reconciliation' => false,
        ]);
        $this->mirrorItem($transaction, [
            'description' => 'Upah perbaikan',
            'item_description' => 'Upah perbaikan',
            'quantity' => 1,
            'unit' => 'paket',
            'unit_price' => 5000,
            'amount' => 5000,
        ]);
        $workOrder = $transaction->workOrder()->create([
            'maintenance_id' => SpjMaintenance::query()->create([
                'fiscal_year_id' => 1,
                'name' => 'Pemeliharaan uji',
            ])->id,
            'spk_date' => '2026-02-05',
            'rab_date' => '2026-02-03',
            'expense_type' => 'Belanja Pemeliharaan',
            'work_description' => 'Perbaikan atap uji',
            'work_location' => 'Sekolah uji',
        ]);
        $workOrder->workers()->create([
            'name' => 'Tukang Uji',
            'work_days' => 2,
            'daily_rate' => 2500,
        ]);
        $package = $transaction->spjPackage()->create(['status' => 'READY']);

        $response = $this->post(route('spj.quarter-numbering'), ['quarter' => 1]);
        $response->assertSessionHas('success');

        $package = $package->fresh();
        $this->assertSame('NUMBERED', $package->status);
        foreach (['SPK', 'RAB'] as $type) {
            $this->assertNotNull(
                $package->documents()->where('document_type', $type)->whereNotNull('document_number')->first()?->document_number,
                "Dokumen {$type} seharusnya bernomor pada penomoran triwulan."
            );
        }
    }
}
