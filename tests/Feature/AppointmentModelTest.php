<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Court;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reference_codes_are_short_and_readable(): void
    {
        $code = Appointment::generateReferenceCode();

        $this->assertMatchesRegularExpression('/^PB-[A-HJ-NP-Z2-9]{6}$/', $code);
    }

    public function test_confirmed_and_recent_pending_bookings_block_their_slot(): void
    {
        $court = Court::factory()->create();
        $start = now()->addDay()->setTime(18, 0);

        Appointment::factory()->for($court)->at($start)->create();
        Appointment::factory()->for($court)->at($start->copy()->addHour())->pending()->create();

        $this->assertSame(2, Appointment::blocking()->count());
    }

    public function test_unpaid_pending_booking_stops_blocking_after_the_hold_expires(): void
    {
        $court = Court::factory()->create();
        $start = now()->addDay()->setTime(18, 0);

        $booking = Appointment::factory()->for($court)->at($start)->pending()->create();
        $this->assertSame(1, Appointment::blocking()->count());
        $this->assertTrue($booking->isAwaitingPayment());

        Carbon::setTestNow(now()->addMinutes(61));

        $this->assertSame(0, Appointment::blocking()->count());
        $this->assertTrue($booking->fresh()->isHoldExpired());
    }

    public function test_bookings_waiting_for_verification_keep_blocking_indefinitely(): void
    {
        Appointment::factory()->at(now()->addDay()->setTime(18, 0))->forVerification()->create();

        Carbon::setTestNow(now()->addHours(5));

        $this->assertSame(1, Appointment::blocking()->count());
    }

    public function test_cancelled_bookings_never_block(): void
    {
        Appointment::factory()->cancelled()->create();

        $this->assertSame(0, Appointment::blocking()->count());
    }

    public function test_back_to_back_bookings_do_not_overlap(): void
    {
        $court = Court::factory()->create();
        $six = now()->addDay()->setTime(18, 0);
        Appointment::factory()->for($court)->at($six, 2)->create(); // 6pm - 8pm

        $this->assertSame(1, Appointment::overlapping($six->copy()->addHour(), $six->copy()->addHours(2))->count());
        $this->assertSame(0, Appointment::overlapping($six->copy()->addHours(2), $six->copy()->addHours(3))->count());
        $this->assertSame(0, Appointment::overlapping($six->copy()->subHour(), $six)->count());
    }

    public function test_clients_can_cancel_until_the_cutoff_but_not_after(): void
    {
        $far = Appointment::factory()->at(now()->addDay()->setTime(18, 0))->create();
        $soon = Appointment::factory()->at(now()->addHours(3))->create();

        $this->assertTrue($far->canBeCancelledByClient());
        $this->assertFalse($soon->canBeCancelledByClient()); // inside the 6 hour window
    }

    public function test_unpaid_pending_bookings_can_always_be_dropped(): void
    {
        $booking = Appointment::factory()->at(now()->addHours(2))->pending()->create();

        $this->assertTrue($booking->canBeCancelledByClient());
    }
}
