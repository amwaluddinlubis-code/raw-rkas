<?php

namespace Tests\Unit;

use App\Services\RkasPaguSimulationService;
use Tests\TestCase;

class RkasPaguSimulationTest extends TestCase
{
    public function test_simulate_reallocates_total_by_existing_quarter_split(): void
    {
        $rows = collect([
            ['activity_code' => '03.03.07', 'activity_name' => 'Ekskul', 'amount' => 100000, 'periods' => [
                ['__QUARTER_NUMBER' => 1, 'JUMLAH' => 60000],
                ['__QUARTER_NUMBER' => 3, 'JUMLAH' => 40000],
            ]],
        ]);
        $result = app(RkasPaguSimulationService::class)->simulate($rows, ['03.03.07' => 200000]);

        $this->assertSame(60000.0, $result['current_quarters'][1]);
        $this->assertSame(120000.0, $result['proposed_quarters'][1]);
        $this->assertSame(80000.0, $result['proposed_quarters'][3]);
        $this->assertSame(60000.0, $result['delta_quarters'][1]);
        $this->assertSame(40000.0, $result['delta_quarters'][3]);
        $this->assertSame(200000.0, $result['activities'][0]['proposed']);
    }

    public function test_simulate_keeps_current_when_no_override(): void
    {
        $rows = collect([
            ['activity_code' => '01', 'activity_name' => 'Program', 'amount' => 50000, 'periods' => [
                ['__QUARTER_NUMBER' => 2, 'JUMLAH' => 50000],
            ]],
        ]);
        $result = app(RkasPaguSimulationService::class)->simulate($rows, []);

        $this->assertSame(50000.0, $result['proposed_quarters'][2]);
        $this->assertSame(0.0, $result['delta_quarters'][2]);
    }
}
