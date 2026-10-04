<?php

namespace Tests\Unit;

use App\Services\ArkasReconciliationService;
use Tests\TestCase;

class ArkasReconciliationDiffTest extends TestCase
{
    public function test_collect_diff_reports_numeric_delta_for_changed_row(): void
    {
        $service = app(ArkasReconciliationService::class);
        $result = $service->collectDiff([], ['id_rapbs' => 'R1', 'JUMLAH' => 150000], 'R1', 'changed', ['id_rapbs' => 'R1', 'jumlah' => 100000, 'uraian' => 'Batu Kali']);

        $this->assertCount(1, $result);
        $this->assertSame('R1', $result[0]['source_key']);
        $this->assertSame('changed', $result[0]['type']);
        $this->assertSame([['field' => 'JUMLAH', 'old' => 100000.0, 'new' => 150000.0, 'delta' => 50000.0]], $result[0]['deltas']);
    }

    public function test_collect_diff_new_row_uses_zero_old(): void
    {
        $service = app(ArkasReconciliationService::class);
        $result = $service->collectDiff([], ['id_rapbs' => 'R2', 'VOLUME' => 5], 'R2', 'new', []);

        $this->assertCount(1, $result);
        $this->assertSame('new', $result[0]['type']);
        $this->assertSame([['field' => 'VOLUME', 'old' => null, 'new' => 5.0, 'delta' => 5.0]], $result[0]['deltas']);
    }

    public function test_collect_diff_skips_non_numeric_rows(): void
    {
        $service = app(ArkasReconciliationService::class);
        $result = $service->collectDiff([], ['id_rapbs' => 'R3', 'uraian' => 'Tanah'], 'R3', 'changed', ['uraian' => 'Bata']);

        $this->assertSame([], $result);
    }
}
