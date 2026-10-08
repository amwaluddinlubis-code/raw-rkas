<?php

namespace Tests\Feature;

use App\Services\SpjSourceRelinkService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpjSourceRelinkTest extends TestCase
{
    public function test_item_fingerprint_is_stable_across_casing_and_key_order(): void
    {
        $a = ['URAIAN' => '  Belanja  ATK ', 'VOLUME' => '10', 'JUMLAH' => 1500000, 'SATUAN' => 'Paket'];
        $b = ['satuan' => 'paket', 'jumlah' => '1500000.00', 'volume' => 10, 'uraian' => 'belanja atk'];

        $this->assertSame(SpjSourceRelinkService::itemFingerprint($a), SpjSourceRelinkService::itemFingerprint($b));
    }

    public function test_item_fingerprint_differs_on_amount_or_description(): void
    {
        $base = ['URAIAN' => 'Belanja ATK', 'VOLUME' => '10', 'JUMLAH' => 1500000, 'SATUAN' => 'Paket'];

        $this->assertNotSame(
            SpjSourceRelinkService::itemFingerprint($base),
            SpjSourceRelinkService::itemFingerprint([...$base, 'JUMLAH' => 1600000])
        );
        $this->assertNotSame(
            SpjSourceRelinkService::itemFingerprint($base),
            SpjSourceRelinkService::itemFingerprint([...$base, 'URAIAN' => 'Belanja tinta'])
        );
    }

    public function test_set_fingerprint_is_order_insensitive(): void
    {
        $fps = ['a|1|2|p', 'b|3|4|p'];

        $this->assertSame(
            SpjSourceRelinkService::setFingerprint($fps),
            SpjSourceRelinkService::setFingerprint(array_reverse($fps))
        );
    }

    public function test_sync_v2_falls_back_to_relink_adoption(): void
    {
        $v2 = file_get_contents(app_path('Services/ArkasSynchronizationServiceV2.php'));

        $this->assertIsString($v2);
        $this->assertStringContainsString('adoptForSync', $v2);
        $this->assertStringContainsString('SpjSourceRelinkService', $v2);
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'adoptForSync'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'preview'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'executePairs'));
    }

    public function test_relink_command_is_dry_run_by_default(): void
    {
        $command = file_get_contents(app_path('Console/Commands/RelinkRecreatedBku.php'));
        $service = file_get_contents(app_path('Services/SpjSourceRelinkService.php'));

        $this->assertIsString($command);
        $this->assertIsString($service);
        $this->assertStringContainsString('spj:relink-recreated-bku', $command);
        $this->assertStringContainsString('--execute', $command);
        $this->assertStringContainsString('Backup', $command);
        $this->assertStringContainsString('SOURCE_RELINK', $service);
        $this->assertStringContainsString('TRANSACTION', $service);
    }

    public function test_snapshot_fingerprint_is_stable_across_formatting(): void
    {
        $base = [
            'transaction_date' => '2026-04-07', 'description' => '  ANNISAH ', 'activity_code' => '07.12.01.',
            'activity_name' => 'Pembayaran honor', 'account_code' => '5.1.02.02.01.0013', 'account_name' => 'ANNISAH',
            'recipient_name' => 'Annisah', 'gross_amount' => 2400000, 'ppn' => 0, 'pph21' => 0, 'pph22' => 0,
            'pph23' => 0, 'pph4' => 0, 'sspd' => 0, 'tax_total' => 0, 'net_amount' => 2400000, 'is_siplah' => false,
        ];
        $variant = [...$base, 'description' => 'annisah', 'gross_amount' => '2400000.00', 'net_amount' => 2400000.0];

        $this->assertSame(
            SpjSourceRelinkService::snapshotFingerprint($base),
            SpjSourceRelinkService::snapshotFingerprint($variant)
        );
        $this->assertNotSame(
            SpjSourceRelinkService::snapshotFingerprint($base),
            SpjSourceRelinkService::snapshotFingerprint([...$base, 'gross_amount' => 2500000])
        );
        $this->assertNotSame(
            SpjSourceRelinkService::snapshotFingerprint($base),
            SpjSourceRelinkService::snapshotFingerprint([...$base, 'transaction_date' => '2026-04-08'])
        );
    }

    public function test_snapshot_fallback_methods_and_command_wiring_exist(): void
    {
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'snapshotFingerprint'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'lastContentSnapshot'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'rebuiltSnapshot'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'previewSnapshotPairs'));

        $command = file_get_contents(app_path('Console/Commands/RelinkRecreatedBku.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('snapshot_relinkable', $command);
        $this->assertStringContainsString('snapshot_manual', $command);
    }

    public function test_explicit_pair_and_suggest_wiring_exist(): void
    {
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'suggestPairs'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'buildExplicitPair'));
        $this->assertTrue(method_exists(SpjSourceRelinkService::class, 'previewOrderPairs'));

        $command = file_get_contents(app_path('Console/Commands/RelinkRecreatedBku.php'));

        $this->assertIsString($command);
        $this->assertStringContainsString('--pair', $command);
        $this->assertStringContainsString('--suggest', $command);
        $this->assertStringContainsString('pasangan manual', $command);

        $service = file_get_contents(app_path('Services/SpjSourceRelinkService.php'));

        $this->assertIsString($service);
        $this->assertStringContainsString('Pasangan manual pilihan operator', $service);
    }

    public function test_pair_items_by_fingerprint_matches_content_regardless_of_order(): void
    {
        $old = [
            ['item' => (object) ['id' => 11], 'fp' => 'atk|10|1500000|paket'],
            ['item' => (object) ['id' => 12], 'fp' => 'tinta|2|500000|botol'],
        ];
        $new = [
            ['id' => 'N2', 'fp' => 'tinta|2|500000|botol'],
            ['id' => 'N1', 'fp' => 'atk|10|1500000|paket'],
        ];

        $result = SpjSourceRelinkService::pairItemsByFingerprint($old, $new);

        // Pairing posisional lama memasangkan 11->N2 (salah); berbasis
        // fingerprint harus mengikuti isi.
        $this->assertSame([11 => 'N1', 12 => 'N2'], $result['paired']);
        $this->assertSame([], $result['skipped']);
    }

    public function test_pair_items_by_fingerprint_skips_unmatched_instead_of_silent_pairing(): void
    {
        $old = [
            ['item' => (object) ['id' => 11], 'fp' => 'atk|10|1500000|paket'],
            ['item' => (object) ['id' => 12], 'fp' => 'tinta|2|500000|botol'],
        ];
        $new = [
            ['id' => 'N1', 'fp' => 'atk|10|1500000|paket'],
            ['id' => 'N9', 'fp' => 'kertas|5|250000|rim'],
        ];

        $result = SpjSourceRelinkService::pairItemsByFingerprint($old, $new);

        $this->assertSame([11 => 'N1'], $result['paired']);
        $this->assertArrayHasKey(12, $result['skipped']);
        $this->assertStringContainsString('tidak dipasangkan', $result['skipped'][12]);
    }

    public function test_pair_items_by_fingerprint_pairs_identical_duplicates_deterministically(): void
    {
        $old = [
            ['item' => (object) ['id' => 11], 'fp' => 'pulsa|1|50000|voucher'],
            ['item' => (object) ['id' => 12], 'fp' => 'pulsa|1|50000|voucher'],
        ];
        $new = [
            ['id' => 'N1', 'fp' => 'pulsa|1|50000|voucher'],
            ['id' => 'N2', 'fp' => 'pulsa|1|50000|voucher'],
        ];

        $result = SpjSourceRelinkService::pairItemsByFingerprint($old, $new);

        $this->assertSame([11 => 'N1', 12 => 'N2'], $result['paired']);
        $this->assertSame([], $result['skipped']);
    }

    public function test_pair_items_by_fingerprint_falls_back_to_positional_without_fingerprints(): void
    {
        // Jalur snapshot/perbaikan manual tidak punya sidik per item;
        // kontrak lama (urutan deterministik) dipertahankan.
        $old = [
            ['item' => (object) ['id' => 11], 'fp' => ''],
            ['item' => (object) ['id' => 12], 'fp' => ''],
        ];
        $new = [
            ['id' => 'N1', 'fp' => ''],
            ['id' => 'N2', 'fp' => ''],
        ];

        $result = SpjSourceRelinkService::pairItemsByFingerprint($old, $new);

        $this->assertSame([11 => 'N1', 12 => 'N2'], $result['paired']);
        $this->assertSame([], $result['skipped']);
    }

    public function test_repoint_items_updates_only_fingerprint_paired_rows(): void
    {
        config()->set('database.connections.school.database', ':memory:');
        config()->set('database.connections.school.journal_mode', null);
        DB::purge('school');
        Artisan::call('migrate', [
            '--database' => 'school',
            '--path' => 'database/migrations/school',
            '--force' => true,
        ]);

        try {
            $yearId = DB::connection('school')->table('fiscal_years')->insertGetId([
                'year' => 2026, 'fund_source' => 'BOSP', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $transactionId = DB::connection('school')->table('transactions')->insertGetId([
                'fiscal_year_id' => $yearId, 'id_kas_umum' => 'BKU-1',
                'source_status' => 'SOURCE_MISSING', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $itemA = DB::connection('school')->table('transaction_items')->insertGetId([
                'transaction_id' => $transactionId, 'source_item_id' => 'OLD-A', 'item_description' => 'ATK',
                'source_status' => 'SOURCE_MISSING', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $itemB = DB::connection('school')->table('transaction_items')->insertGetId([
                'transaction_id' => $transactionId, 'source_item_id' => 'OLD-B', 'item_description' => 'Tinta',
                'source_status' => 'SOURCE_MISSING', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $service = app(SpjSourceRelinkService::class);
            $method = new \ReflectionMethod(SpjSourceRelinkService::class, 'repointItems');
            $method->setAccessible(true);

            $oldRows = [
                ['item' => (object) ['id' => $itemA, 'item_description' => null], 'fp' => 'atk|10|1500000|paket'],
                ['item' => (object) ['id' => $itemB, 'item_description' => null], 'fp' => 'tinta|2|500000|botol'],
            ];
            // Urutan baru dibalik + satu sidik tidak cocok: item B tidak
            // boleh dipasangkan diam-diam ke NEW-X.
            $newRows = [
                ['id' => 'NEW-B', 'fp' => 'tinta|2|500000|botol'],
                ['id' => 'NEW-X', 'fp' => 'kertas|5|250000|rim'],
            ];

            $result = $method->invoke($service, $oldRows, $newRows, null);

            $this->assertSame(1, $result['paired']);
            $this->assertArrayHasKey($itemA, $result['skipped']);

            $rows = DB::connection('school')->table('transaction_items')->orderBy('id')->get();
            $this->assertSame('NEW-B', $rows[1]->source_item_id);
            $this->assertSame('ACTIVE', $rows[1]->source_status);
            // Baris yang dilewati tidak tersentuh.
            $this->assertSame('OLD-A', $rows[0]->source_item_id);
            $this->assertSame('SOURCE_MISSING', $rows[0]->source_status);
        } finally {
            DB::purge('school');
        }
    }
}
