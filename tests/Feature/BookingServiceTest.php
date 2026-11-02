<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Court;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingService;
use App\Support\Business;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BookingService $bookings;

    protected Court $court;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:30:00'));

        app(Business::class)->save(['gcash_number' => '09171234567']);

        $this->bookings = app(BookingService::class);
        $this->court = Court::factory()->create(['hourly_rate' => 300, 'peak_hourly_rate' => 450]);
        $this->customer = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    protected function book(string $start = '2026-11-03 09:00', int $hours = 1, PaymentMethod $method = PaymentMethod::Gcash, bool $asStaff = false): Appointment
    {
        return $this->bookings->book($this->court, $this->customer, Carbon::parse($start), $hours, $method, asStaff: $asStaff);
    }

    public function test_a_gcash_booking_is_pending_and_priced(): void
    {
        $booking = $this->book('2026-11-03 16:00', 2); // 4pm normal + 5pm peak

        $this->assertSame(AppointmentStatus::Pending, $booking->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
        $this->assertSame(750, $booking->total_amount);
        $this->assertSame('2026-11-03 18:00:00', $booking->ends_at->toDateTimeString());
        $this->assertMatchesRegularExpression('/^PB-/', $booking->reference_code);
    }

    public function test_a_pay_at_court_booking_is_confirmed_immediately(): void
    {
        $booking = $this->book(method: PaymentMethod::Cash);

        $this->assertSame(AppointmentStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Unpaid, $booking->payment_status);
    }

    public function test_pay_at_court_can_be_switched_off(): void
    {
        app(Business::class)->save(['allow_pay_at_court' => false]);

        $this->expectException(BookingException::class);
        $this->book(method: PaymentMethod::Cash);
    }

    public function test_gcash_requires_the_owner_to_have_set_a_number(): void
    {
        Setting::where('key', 'gcash_number')->delete();
        app(Business::class)->refresh();

        $this->expectException(BookingException::class);
        $this->book();
    }

    public function test_the_same_slot_cannot_be_booked_twice(): void
    {
        $this->book('2026-11-03 18:00', 2);

        $this->expectException(BookingException::class);
        $this->book('2026-11-03 19:00');
    }

    public function test_a_conflict_creates_no_second_booking(): void
    {
        $this->book('2026-11-03 18:00');

        try {
            $this->book('2026-11-03 18:00');
        } catch (BookingException) {
            //
        }

        $this->assertSame(1, Appointment::count());
    }

    public function test_back_to_back_bookings_are_allowed(): void
    {
        $this->book('2026-11-03 18:00');
        $second = $this->book('2026-11-03 19:00');

        $this->assertSame(2, Appointment::count());
        $this->assertSame('2026-11-03 19:00:00', $second->effective_datetime->toDateTimeString());
    }

    public function test_an_expired_unpaid_hold_frees_the_slot_for_someone_else(): void
    {
        $this->book('2026-11-03 18:00');
        Carbon::setTestNow(now()->addMinutes(61));

        $other = $this->bookings->book($this->court, User::factory()->create(), Carbon::parse('2026-11-03 18:00'), 1, PaymentMethod::Cash);

        $this->assertSame(2, Appointment::count());
        $this->assertSame(AppointmentStatus::Confirmed, $other->status);
    }

    #[DataProvider('brokenRules')]
    public function test_booking_rules_are_enforced(string $start, int $hours): void
    {
        $this->expectException(BookingException::class);
        $this->book($start, $hours);
    }

    public static function brokenRules(): array
    {
        return [
            'in the past' => ['2026-11-02 09:00', 1],
            'already started this hour' => ['2026-11-02 10:00', 1],
            'before opening' => ['2026-11-03 05:00', 1],
            'runs past closing' => ['2026-11-03 21:00', 2],
            'too long' => ['2026-11-03 09:00', 5],
            'zero hours' => ['2026-11-03 09:00', 0],
            'not on the hour' => ['2026-11-03 09:30', 1],
            'too far ahead' => ['2026-12-25 09:00', 1],
        ];
    }

    public function test_staff_can_log_a_walk_in_that_already_started(): void
    {
        $booking = $this->book('2026-11-02 10:00', 1, PaymentMethod::Cash, asStaff: true);

        $this->assertSame(AppointmentStatus::Confirmed, $booking->status);
    }

    public function test_staff_still_cannot_double_book(): void
    {
        $this->book('2026-11-03 09:00');

        $this->expectException(BookingException::class);
        $this->book('2026-11-03 09:00', 1, PaymentMethod::Cash, asStaff: true);
    }

    public function test_submitting_a_gcash_reference_moves_the_booking_to_verification(): void
    {
        $booking = $this->book();

        $this->bookings->submitGcashReference($booking, '1234 567 890123');

        $booking->refresh();
        $this->assertSame('1234567890123', $booking->gcash_reference);
        $this->assertSame(PaymentStatus::ForVerification, $booking->payment_status);
        $this->assertSame(AppointmentStatus::Pending, $booking->status);
    }

    public function test_a_reference_number_cannot_pay_for_two_bookings(): void
    {
        $first = $this->book('2026-11-03 09:00');
        $second = $this->book('2026-11-03 11:00');
        $this->bookings->submitGcashReference($first, '1234567890123');

        $this->expectException(BookingException::class);
        $this->bookings->submitGcashReference($second, '1234567890123');
    }

    public function test_a_late_payment_is_accepted_if_the_slot_is_still_free(): void
    {
        $booking = $this->book();
        Carbon::setTestNow(now()->addMinutes(90));

        $this->bookings->submitGcashReference($booking, '1234567890123');

        $this->assertSame(PaymentStatus::ForVerification, $booking->fresh()->payment_status);
    }

    public function test_a_late_payment_is_refused_if_someone_else_took_the_slot(): void
    {
        $booking = $this->book();
        Carbon::setTestNow(now()->addMinutes(90));
        $this->bookings->book($this->court, User::factory()->create(), Carbon::parse('2026-11-03 09:00'), 1, PaymentMethod::Cash);

        $this->expectException(BookingException::class);
        $this->bookings->submitGcashReference($booking, '1234567890123');
    }

    public function test_confirming_payment_marks_the_booking_confirmed_and_paid(): void
    {
        $booking = $this->book();
        $this->bookings->submitGcashReference($booking, '1234567890123');

        $this->bookings->confirmPayment($booking);

        $booking->refresh();
        $this->assertSame(AppointmentStatus::Confirmed, $booking->status);
        $this->assertSame(PaymentStatus::Paid, $booking->payment_status);
    }

    public function test_cancelled_bookings_cannot_be_confirmed(): void
    {
        $booking = $this->book();
        $this->bookings->cancel($booking);

        $this->expectException(BookingException::class);
        $this->bookings->confirmPayment($booking);
    }

    public function test_customers_cannot_cancel_inside_the_cutoff_but_staff_can(): void
    {
        $booking = $this->book('2026-11-02 13:00', 1, PaymentMethod::Cash); // 2.5 hours away

        try {
            $this->bookings->cancel($booking);
            $this->fail('Expected the cancellation to be refused.');
        } catch (BookingException) {
            $this->assertSame(AppointmentStatus::Confirmed, $booking->fresh()->status);
        }

        $this->bookings->cancel($booking, byStaff: true);
        $this->assertSame(AppointmentStatus::Cancelled, $booking->fresh()->status);
    }

    public function test_cancelling_keeps_the_payment_status_so_refunds_are_visible(): void
    {
        $booking = $this->book();
        $this->bookings->submitGcashReference($booking, '1234567890123');
        $this->bookings->confirmPayment($booking);

        $this->bookings->cancel($booking);

        $this->assertSame(PaymentStatus::Paid, $booking->fresh()->payment_status);
    }

    public function test_rescheduling_recalculates_the_price_and_ignores_its_own_slot(): void
    {
        $booking = $this->book('2026-11-03 09:00'); // 300

        $this->bookings->reschedule($booking, $this->court, Carbon::parse('2026-11-03 09:00'), 2);
        $this->assertSame(600, $booking->fresh()->total_amount);

        $this->bookings->reschedule($booking, $this->court, Carbon::parse('2026-11-03 18:00'), 1);
        $booking->refresh();
        $this->assertSame(450, $booking->total_amount);
        $this->assertSame('2026-11-03 19:00:00', $booking->ends_at->toDateTimeString());
    }

    public function test_rescheduling_into_another_booking_is_refused(): void
    {
        $this->book('2026-11-03 18:00');
        $movable = $this->book('2026-11-03 09:00');

        $this->expectException(BookingException::class);
        $this->bookings->reschedule($movable, $this->court, Carbon::parse('2026-11-03 18:00'), 1);
    }

    public function test_expire_unpaid_releases_only_stale_unpaid_gcash_bookings(): void
    {
        $stale = $this->book('2026-11-03 09:00');
        $paying = $this->book('2026-11-03 11:00');
        $this->bookings->submitGcashReference($paying, '1234567890123');
        $cash = $this->book('2026-11-03 13:00', 1, PaymentMethod::Cash);

        Carbon::setTestNow(now()->addMinutes(61));
        $fresh = $this->book('2026-11-03 15:00');

        $this->assertSame(1, $this->bookings->expireUnpaid());
        $this->assertSame(AppointmentStatus::Cancelled, $stale->fresh()->status);
        $this->assertSame(AppointmentStatus::Pending, $paying->fresh()->status);
        $this->assertSame(AppointmentStatus::Confirmed, $cash->fresh()->status);
        $this->assertSame(AppointmentStatus::Pending, $fresh->fresh()->status);
    }
}
