<?php

namespace Tests\Feature;

use App\Models\Court;
use App\Services\PriceCalculator;
use App\Support\Business;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PriceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected function calculator(): PriceCalculator
    {
        return new PriceCalculator(new Business([]));
    }

    public function test_off_peak_hours_use_the_normal_rate(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => 450]);

        $this->assertSame(600, $this->calculator()->total($court, Carbon::parse('2026-11-03 09:00'), 2));
    }

    public function test_peak_hours_use_the_peak_rate(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => 450]);

        $this->assertSame(900, $this->calculator()->total($court, Carbon::parse('2026-11-03 18:00'), 2));
    }

    public function test_a_booking_that_crosses_into_peak_is_charged_per_hour(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => 450]);

        // 4pm is off-peak, 5pm is peak (default peak hours 17-21)
        $this->assertSame(750, $this->calculator()->total($court, Carbon::parse('2026-11-03 16:00'), 2));
    }

    public function test_courts_without_a_peak_rate_charge_the_normal_rate_all_day(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => null]);

        $this->assertSame(600, $this->calculator()->total($court, Carbon::parse('2026-11-03 18:00'), 2));
    }

    public function test_breakdown_lists_each_hour(): void
    {
        $court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => 450]);

        $this->assertSame([
            ['hour' => 16, 'rate' => 300, 'peak' => false],
            ['hour' => 17, 'rate' => 450, 'peak' => true],
        ], $this->calculator()->breakdown($court, Carbon::parse('2026-11-03 16:00'), 2));
    }
}
