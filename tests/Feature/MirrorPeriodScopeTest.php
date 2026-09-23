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

class MirrorPeriodScopeTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        DB::purge('school');

        parent::tearDown();
    }

    private function seedKas(string $key, array $payload): void
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

    private function makeTransaction(string $kasKey, ?string $itemKey = null): Transaction
    {
        $transaction = Transaction::query()->create([
            'fiscal_year_id' => 1,
            'fund_source_id' => 1,
            'id_kas_umum' => $kasKey,
        ]);
        if ($itemKey !== null) {
            $transaction->items()->create(['source_item_id' => $itemKey, 'item_description' => 'Overlay']);
        }

        return $transaction;
    }

    public function test_period_scope_includes_orphan_transaction_via_item_mirror_date(): void
    {
        $this->seedKas('KAS-H', ['ID_KAS_UMUM' => 'KAS-H', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-H', 'TANGGAL_TRANSAKSI' => '2026-04-10', 'JUMLAH' => 100000]);
        $this->seedKas('KAS-O', ['ID_KAS_UMUM' => 'KAS-O', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-O', 'TANGGAL_TRANSAKSI' => '2026-04-11', 'JUMLAH' => 200000]);
        $this->seedKas('KAS-M', ['ID_KAS_UMUM' => 'KAS-M', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-M', 'TANGGAL_TRANSAKSI' => '2026-05-01', 'JUMLAH' => 300000]);

        $healthy = $this->makeTransaction('KAS-H', 'KAS-H');
        $orphan = $this->makeTransaction('STALE-KEY', 'KAS-O');
        $otherMonth = $this->makeTransaction('KAS-M', 'KAS-M');

        $query = Transaction::query();
        ArkasMirrorResolver::joinKasUmum($query);
        ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%m', {d}) AS INTEGER) = ?", [4]);
        $ids = $query->pluck('transactions.id')->all();

        $this->assertContains($healthy->id, $ids);
        $this->assertContains($orphan->id, $ids);
        $this->assertNotContains($otherMonth->id, $ids);
    }

    public function test_period_scope_keeps_single_period_for_healthy_rows(): void
    {
        $this->seedKas('KAS-H', ['ID_KAS_UMUM' => 'KAS-H', 'KATEGORI_BKU' => 'BELANJA', 'NO_BUKTI' => 'BK-H', 'TANGGAL_TRANSAKSI' => '2026-04-10', 'JUMLAH' => 100000]);

        $healthy = $this->makeTransaction('KAS-H', 'KAS-H');

        $query = Transaction::query();
        ArkasMirrorResolver::joinKasUmum($query);
        ArkasMirrorResolver::whereMirrorDate($query, "CAST(strftime('%m', {d}) AS INTEGER) = ?", [5]);
        $ids = $query->pluck('transactions.id')->all();

        $this->assertNotContains($healthy->id, $ids);
    }
}
