<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Court;
use App\Services\AvailabilityService;
use App\Support\Business;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:30:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function service(): AvailabilityService
    {
        return new AvailabilityService(new Business([]));
    }

    public function test_the_grid_has_a_slot_for_every_open_hour_on_every_active_court(): void
    {
        Court::factory()->count(2)->create();
        Court::factory()->inactive()->create();

        $grid = $this->service()->dayGrid(Carbon::parse('2026-11-03'));

        $this->assertCount(2, $grid);
        $this->assertCount(16, $grid[0]['slots']); // 6am to 10pm
        $this->assertSame(6, $grid[0]['slots'][0]['hour']);
        $this->assertSame(21, $grid[0]['slots'][15]['hour']);
    }

    public function test_slots_in_the_past_are_marked_past(): void
    {
        Court::factory()->create();

        $slots = $this->service()->dayGrid(Carbon::parse('2026-11-02'))[0]['slots'];
        $byHour = collect($slots)->keyBy('hour');

        $this->assertSame(AvailabilityService::PAST, $byHour[10]['state']); // 10:00 already started
        $this->assertSame(AvailabilityService::AVAILABLE, $byHour[11]['state']);
    }

    public function test_every_hour_of_a_multi_hour_booking_is_marked_booked(): void
    {
        $court = Court::factory()->create();
        Appointment::factory()->for($court)->at(Carbon::parse('2026-11-03 18:00'), 2)->create();

        $byHour = collect($this->service()->dayGrid(Carbon::parse('2026-11-03'))[0]['slots'])->keyBy('hour');

        $this->assertSame(AvailabilityService::AVAILABLE, $byHour[17]['state']);
        $this->assertSame(AvailabilityService::BOOKED, $byHour[18]['state']);
        $this->assertSame(AvailabilityService::BOOKED, $byHour[19]['state']);
        $this->assertSame(AvailabilityService::AVAILABLE, $byHour[20]['state']);
        $this->assertNotNull($byHour[19]['appointment']);
    }

    public function test_is_available_detects_overlaps_but_allows_back_to_back(): void
    {
        $court = Court::factory()->create();
        Appointment::factory()->for($court)->at(Carbon::parse('2026-11-03 18:00'), 2)->create();
        $service = $this->service();

        $this->assertFalse($service->isAvailable($court, Carbon::parse('2026-11-03 17:00'), 2)); // 5-7 clips 6-8
        $this->assertFalse($service->isAvailable($court, Carbon::parse('2026-11-03 19:00'), 1));
        $this->assertTrue($service->isAvailable($court, Carbon::parse('2026-11-03 16:00'), 2));  // ends exactly at 6
        $this->assertTrue($service->isAvailable($court, Carbon::parse('2026-11-03 20:00'), 1));  // starts exactly at 8
    }

    public function test_other_courts_are_unaffected(): void
    {
        [$one, $two] = Court::factory()->count(2)->create();
        Appointment::factory()->for($one)->at(Carbon::parse('2026-11-03 18:00'))->create();

        $this->assertTrue($this->service()->isAvailable($two, Carbon::parse('2026-11-03 18:00'), 1));
    }

    public function test_a_booking_can_be_ignored_when_checking_its_own_reschedule(): void
    {
        $court = Court::factory()->create();
        $booking = Appointment::factory()->for($court)->at(Carbon::parse('2026-11-03 18:00'), 2)->create();

        $this->assertFalse($this->service()->isAvailable($court, Carbon::parse('2026-11-03 19:00'), 2));
        $this->assertTrue($this->service()->isAvailable($court, Carbon::parse('2026-11-03 19:00'), 1, $booking->id));
    }

    public function test_max_hours_stops_at_the_next_booking_closing_time_and_the_limit(): void
    {
        $court = Court::factory()->create();
        Appointment::factory()->for($court)->at(Carbon::parse('2026-11-03 12:00'))->create();
        $service = $this->service();

        $this->assertSame(2, $service->maxHours($court, Carbon::parse('2026-11-03 10:00'))); // blocked at noon
        $this->assertSame(0, $service->maxHours($court, Carbon::parse('2026-11-03 12:00'))); // taken
        $this->assertSame(4, $service->maxHours($court, Carbon::parse('2026-11-03 14:00'))); // max length
        $this->assertSame(1, $service->maxHours($court, Carbon::parse('2026-11-03 21:00'))); // closes at 10pm
    }
}
