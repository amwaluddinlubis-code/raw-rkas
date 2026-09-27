<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class VendorRecommendationTest extends TestCase
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

    private function fetchRecommendation(array $params): TestResponse
    {
        return $this->withoutMiddleware()
            ->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1])
            ->get(route('transactions.vendor-recommendation', $params));
    }

    public function test_returns_latest_same_vendor_details(): void
    {
        $this->mirrorTransaction([
            'no_bukti' => 'BK-001', 'transaction_date' => '2026-01-10',
            'vendor_name' => 'UD. Putra Gemilang', 'vendor_owner' => 'Pemilik Lama',
            'receipt_recipient_name' => 'Penerima Lama',
        ]);
        $this->mirrorTransaction([
            'no_bukti' => 'BK-002', 'transaction_date' => '2026-02-10',
            'vendor_name' => 'ud. putra  gemilang', 'vendor_owner' => 'Pemilik Baru',
            'receipt_recipient_name' => 'Penerima Baru',
        ]);
        $current = $this->mirrorTransaction([
            'no_bukti' => 'BK-003', 'transaction_date' => '2026-03-10',
            'vendor_name' => 'UD. Putra Gemilang',
        ]);

        $response = $this->fetchRecommendation(['vendor' => 'UD. Putra Gemilang', 'transaction_id' => $current->id]);

        $response->assertOk();
        $response->assertJson([
            'found' => true,
            'vendor_owner' => 'Pemilik Baru',
            'receipt_recipient_name' => 'Penerima Baru',
            'no_bukti' => 'BK-002',
            'transaction_date' => '2026-02-10',
        ]);
    }

    public function test_returns_not_found_when_no_history(): void
    {
        $current = $this->mirrorTransaction([
            'no_bukti' => 'BK-003', 'transaction_date' => '2026-03-10',
            'vendor_name' => 'Toko Baru Jaya',
        ]);

        $response = $this->fetchRecommendation(['vendor' => 'Toko Baru Jaya', 'transaction_id' => $current->id]);

        $response->assertOk();
        $response->assertJson(['found' => false]);
    }

    public function test_rejects_out_of_context_transaction(): void
    {
        DB::connection('school')->table('fiscal_years')->insert([
            'id' => 2, 'year' => 2025, 'fund_source' => 'BOSP', 'fund_source_id' => 1,
            'is_active' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $other = $this->mirrorTransaction([
            'fiscal_year_id' => 2,
            'no_bukti' => 'BK-009', 'transaction_date' => '2025-03-10',
            'vendor_name' => 'UD. Putra Gemilang', 'vendor_owner' => 'X',
        ]);

        $this->fetchRecommendation(['vendor' => 'UD. Putra Gemilang', 'transaction_id' => $other->id])
            ->assertNotFound();
    }
}
