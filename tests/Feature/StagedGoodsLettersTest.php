<?php

namespace Tests\Feature;

use App\Models\FiscalYear;
use App\Models\FundSource;
use App\Models\School;
use App\Models\Transaction;
use App\Services\SpjDocumentNumberService;
use App\Services\SpjNumberingPolicyService;
use App\Services\SpjTemplateService;
use App\Services\TransactionSettlementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\SeedsArkasMirror;
use Tests\TestCase;

class StagedGoodsLettersTest extends TestCase
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
        $this->withoutMiddleware()->withSession(['active_fiscal_year_id' => 1, 'active_fund_source_id' => 1]);
    }

    protected function tearDown(): void
    {
        DB::purge('school');
        parent::tearDown();
    }

    private function barangTransaction(): Transaction
    {
        $transaction = $this->mirrorTransaction([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'no_bukti' => 'BNU33',
            'transaction_date' => '2026-09-25',
            'gross_amount' => 660000,
            'net_amount' => 660000,
            'spj_category' => 'BARANG',
        ]);
        foreach ([['Juli', '2026-07-05'], ['Agustus', '2026-08-05']] as [$month, $date]) {
            $item = $this->mirrorItem($transaction, [
                'description' => 'Pulsa internet '.$month,
                'item_description' => 'Pulsa internet '.$month,
                'quantity' => 2,
                'unit' => 'paket',
                'unit_price' => 110000,
                'amount' => 220000,
            ]);
            $item->goods()->create([]);
        }

        return $transaction->fresh();
    }

    public function test_receipt_letter_dates_are_validated_and_stored(): void
    {
        $transaction = $this->barangTransaction();
        $itemId = $transaction->items()->first()->id;

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'order_date' => '2026-07-01',
            'bap_date' => '2026-06-01',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2]],
        ])->assertSessionHasErrors('bap_date');

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'order_date' => '2026-07-01',
            'bap_date' => '2026-07-03',
            'bast_date' => '2026-07-04',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $receipt = $transaction->goodsReceipts()->firstOrFail();
        $this->assertSame('RECEIPT:1', $receipt->scope_key);
        $this->assertSame('TAHAP:1', $receipt->documentScopeKey());
        $this->assertSame('2026-07-01', $receipt->order_date->format('Y-m-d'));
        $this->assertSame('2026-07-03', $receipt->bap_date->format('Y-m-d'));
    }

    public function test_staged_pesanan_numbered_per_tahap(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        foreach ([['2026-07-05', '2026-07-01', 0], ['2026-08-05', '2026-08-01', 1]] as [$receiptDate, $orderDate, $index]) {
            $this->post(route('spj.receipts.store', $transaction->id), [
                'receipt_date' => $receiptDate,
                'order_date' => $orderDate,
                'bap_date' => $receiptDate,
                'bast_date' => $receiptDate,
                'items' => [['transaction_item_id' => $items[$index]->id, 'quantity_received' => 2]],
            ])->assertSessionHas('success');
        }

        $policy = app(SpjNumberingPolicyService::class);
        $this->assertSame('2026-07-01', $policy->documentEventDateValue($transaction->fresh(), 'PESANAN', 'TAHAP:1')->format('Y-m-d'));
        $this->assertSame('2026-08-01', $policy->documentEventDateValue($transaction->fresh(), 'PESANAN', 'TAHAP:2')->format('Y-m-d'));

        $package = $transaction->spjPackage()->firstOrFail();
        $result = app(SpjDocumentNumberService::class)->assignAutomaticNumbers($package, 'TEST', null, ['PESANAN']);

        $this->assertSame(2, $result['created']);
        $numbers = $package->documents()->where('document_type', 'PESANAN')->orderBy('scope_key')->pluck('document_number', 'scope_key');
        $this->assertCount(2, $numbers);
        $this->assertNotSame($numbers['TAHAP:1'], $numbers['TAHAP:2']);

        $goodsNumbers = $transaction->goods()->orderBy('transaction_item_id')->pluck('order_number');
        $this->assertSame($numbers['TAHAP:1'], $goodsNumbers[0]);
        $this->assertSame($numbers['TAHAP:2'], $goodsNumbers[1]);
    }

    public function test_render_scope_filters_items_and_goods(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'order_date' => '2026-07-01',
            'items' => [['transaction_item_id' => $items[0]->id, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $templates = app(SpjTemplateService::class);
        $package = $package->fresh();
        $receipt = $transaction->goodsReceipts()->firstOrFail();

        $this->assertSame([$items[0]->id, $items[1]->id], $templates->renderItems($package)->pluck('id')->all());
        $this->assertSame([$items[0]->id], $templates->renderItems($package, $receipt)->pluck('id')->all());
        $this->assertSame('2026-07-01', $receipt->order_date->format('Y-m-d'));
    }

    public function test_template_placeholders_use_tahap_receipt(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $package = $transaction->spjPackage()->create(['status' => 'DRAFT']);
        $school = School::query()->firstOrCreate(['npsn' => '10208246'], ['name' => 'Sekolah Uji']);

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'order_date' => '2026-07-01',
            'bap_date' => '2026-07-03',
            'bast_date' => '2026-07-04',
            'items' => [['transaction_item_id' => $items[0]->id, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $receipt = $transaction->goodsReceipts()->firstOrFail();
        $values = app(SpjTemplateService::class)->placeholders($package->fresh(), $school, $receipt);

        $this->assertSame('01 Juli 2026', $values['TANGGAL_PESANAN']);
        $this->assertSame('03 Juli 2026', $values['TANGGAL_BAP']);
        $this->assertSame('04 Juli 2026', $values['TANGGAL_PENYERAHAN']);
        $this->assertStringContainsString('Pulsa internet Juli', $values['RINCIAN_BELANJA'] ?? '');
        $this->assertStringNotContainsString('Pulsa internet Agustus', $values['RINCIAN_BELANJA'] ?? '');
    }

    public function test_delete_receipt_removes_stage_and_items(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'items' => [['transaction_item_id' => $items[0]->id, 'quantity_received' => 2]],
        ])->assertSessionHas('success');
        $receiptId = $transaction->goodsReceipts()->firstOrFail()->id;

        $this->delete(route('spj.receipts.destroy', [$transaction->id, $receiptId]))
            ->assertSessionHas('success');

        $this->assertSame(0, $transaction->goodsReceipts()->count());
        $this->assertDatabaseMissing('goods_receipt_items', ['goods_receipt_id' => $receiptId], 'school');
        $this->assertDatabaseHas('operational_audit_logs', [
            'entity_type' => 'TRANSACTION',
            'entity_id' => $transaction->id,
            'action' => 'HAPUS_PENERIMAAN',
        ], 'school');
    }

    public function test_delete_receipt_blocked_by_active_tahap_numbers(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $transaction->spjPackage()->create(['status' => 'DRAFT']);

        foreach ([['2026-07-05', '2026-07-01', 0], ['2026-08-05', '2026-08-01', 1]] as [$receiptDate, $orderDate, $index]) {
            $this->post(route('spj.receipts.store', $transaction->id), [
                'receipt_date' => $receiptDate,
                'order_date' => $orderDate,
                'bap_date' => $receiptDate,
                'bast_date' => $receiptDate,
                'items' => [['transaction_item_id' => $items[$index]->id, 'quantity_received' => 2]],
            ])->assertSessionHas('success');
        }
        app(SpjDocumentNumberService::class)->assignAutomaticNumbers($transaction->spjPackage()->firstOrFail(), 'TEST', null, ['PESANAN']);

        $receiptId = $transaction->goodsReceipts()->where('receipt_sequence', 1)->firstOrFail()->id;
        $this->delete(route('spj.receipts.destroy', [$transaction->id, $receiptId]))
            ->assertSessionHas('error');

        $this->assertSame(2, $transaction->goodsReceipts()->count());
    }

    public function test_receivable_items_exclude_fully_received(): void
    {
        $transaction = $this->barangTransaction();
        $items = $transaction->items()->orderBy('id')->get();
        $settlements = app(TransactionSettlementService::class);

        $this->assertCount(2, $settlements->receivableItems($transaction));

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'items' => [['transaction_item_id' => $items[0]->id, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $remaining = $settlements->receivableItems($transaction->fresh());
        $this->assertCount(1, $remaining);
        $this->assertSame($items[1]->id, $remaining[0]['id']);
    }

    public function test_received_amount_is_canonical_from_source_price(): void
    {
        $transaction = $this->barangTransaction();
        $itemId = $transaction->items()->orderBy('id')->first()->id;

        // Nominal ngawur dari client harus ditimpa hitungan kanonis 2 × 110000.
        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2, 'amount_received' => 1]],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('goods_receipt_items', [
            'transaction_item_id' => $itemId,
            'quantity_received' => 2,
            'amount_received' => 220000,
        ], 'school');
    }

    public function test_over_receive_is_rejected_with_error(): void
    {
        $transaction = $this->barangTransaction();
        $itemId = $transaction->items()->orderBy('id')->first()->id;

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-07-05',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $this->post(route('spj.receipts.store', $transaction->id), [
            'receipt_date' => '2026-08-05',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 1]],
        ])->assertSessionHas('error');

        $this->assertSame(1, $transaction->goodsReceipts()->count());
    }

    public function test_receipt_date_defaults_to_bap_date(): void
    {
        $transaction = $this->barangTransaction();
        $itemId = $transaction->items()->orderBy('id')->first()->id;

        $this->post(route('spj.receipts.store', $transaction->id), [
            'order_date' => '2026-07-01',
            'bap_date' => '2026-07-03',
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2]],
        ])->assertSessionHas('success');

        $receipt = $transaction->goodsReceipts()->firstOrFail();
        $this->assertSame('2026-07-03', $receipt->receipt_date->format('Y-m-d'));
    }

    public function test_receipt_without_any_date_is_rejected(): void
    {
        $transaction = $this->barangTransaction();
        $itemId = $transaction->items()->orderBy('id')->first()->id;

        $this->post(route('spj.receipts.store', $transaction->id), [
            'items' => [['transaction_item_id' => $itemId, 'quantity_received' => 2]],
        ])->assertSessionHas('error');

        $this->assertSame(0, $transaction->goodsReceipts()->count());
    }
}
