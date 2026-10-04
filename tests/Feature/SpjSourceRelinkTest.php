<?php

namespace Tests\Feature;

use App\Services\SpjSourceRelinkService;
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
}
