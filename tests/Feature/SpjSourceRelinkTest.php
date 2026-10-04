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
}
