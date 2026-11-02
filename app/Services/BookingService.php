<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Court;
use App\Models\User;
use App\Support\Business;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * All the rules about creating and changing bookings live here, so the
 * customer screens, the admin screens and any future Messenger bot behave
 * exactly the same way.
 */
class BookingService
{
    public function __construct(
        protected Business $business,
        protected AvailabilityService $availability,
        protected PriceCalculator $prices,
    ) {
        //
    }

    /**
     * Throw a BookingException (with a friendly message) unless this slot can be booked.
     *
     * Staff may log bookings that already started or are far in the future
     * (walk-ins, regulars who booked by phone); everything else still applies.
     */
    public function assertBookable(
        Court $court,
        CarbonInterface $start,
        int $hours,
        bool $asStaff = false,
        ?int $ignoreAppointmentId = null,
    ): void {
        if (! $court->is_active) {
            throw new BookingException(__('This court is not available for booking.'));
        }

        if ($hours < 1 || $hours > $this->business->maxDuration()) {
            throw new BookingException(__('You can book 1 to :max hours at a time.', ['max' => $this->business->maxDuration()]));
        }

        if ($start->minute !== 0 || $start->second !== 0) {
            throw new BookingException(__('Bookings must start on the hour.'));
        }

        if ($start->hour < $this->business->openHour() || $start->hour + $hours > $this->business->closeHour()) {
            throw new BookingException(__('We are open from :open to :close.', [
                'open' => Format::hour($this->business->openHour()),
                'close' => Format::hour($this->business->closeHour()),
            ]));
        }

        if (! $asStaff) {
            if ($start->lte(now())) {
                throw new BookingException(__('That time has already started. Please pick a later slot.'));
            }

            if ($start->gt($this->business->lastBookableDate())) {
                throw new BookingException(__('You can only book up to :days days ahead.', ['days' => $this->business->advanceDays()]));
            }
        }

        if (! $this->availability->isAvailable($court, $start, $hours, $ignoreAppointmentId)) {
            throw new BookingException(__('Sorry, that slot was just taken. Please choose another time.'));
        }
    }

    /**
     * Create a booking.
     *
     * Customers paying by GCash get a pending booking that holds the slot while
     * they pay. Pay-at-the-court bookings and anything entered by staff are
     * confirmed straight away.
     */
    public function book(
        Court $court,
        User $customer,
        CarbonInterface $start,
        int $hours,
        PaymentMethod $method,
        ?string $notes = null,
        bool $asStaff = false,
        PaymentStatus $paymentStatus = PaymentStatus::Unpaid,
    ): Appointment {
        if (! $asStaff) {
            if ($method === PaymentMethod::Cash && ! $this->business->allowPayAtCourt()) {
                throw new BookingException(__('Paying at the court is not available. Please pay with GCash.'));
            }

            if ($method === PaymentMethod::Gcash && ! $this->business->hasGcash()) {
                throw new BookingException(__('GCash payments are not set up yet. Please message us to book.'));
            }
        }

        return DB::transaction(function () use ($court, $customer, $start, $hours, $method, $notes, $asStaff, $paymentStatus) {
            // Lock the court row: a second request for the same court waits here
            // until this one is saved, then sees the new booking and is turned away.
            Court::query()->whereKey($court->getKey())->lockForUpdate()->first();

            $this->assertBookable($court, $start, $hours, $asStaff);

            $needsPaymentFirst = ! $asStaff && $method === PaymentMethod::Gcash;

            return Appointment::create([
                'reference_code' => Appointment::generateReferenceCode(),
                'court_id' => $court->getKey(),
                'user_id' => $customer->getKey(),
                'effective_datetime' => $start,
                'duration_hours' => $hours,
                'ends_at' => $start->copy()->addHours($hours),
                'status' => $needsPaymentFirst ? AppointmentStatus::Pending : AppointmentStatus::Confirmed,
                'total_amount' => $this->prices->total($court, $start, $hours),
                'payment_method' => $method,
                'payment_status' => $asStaff ? $paymentStatus : PaymentStatus::Unpaid,
                'notes' => filled($notes) ? $notes : null,
            ]);
        });
    }

    /**
     * The customer paid by GCash and sends the reference number from their receipt.
     */
    public function submitGcashReference(Appointment $appointment, string $reference): Appointment
    {
        $reference = preg_replace('/\D+/', '', $reference) ?? '';

        if ($appointment->isCancelled()) {
            throw new BookingException(__('This booking was cancelled because payment did not arrive in time. If you already paid, please message us.'));
        }

        if ($appointment->payment_method !== PaymentMethod::Gcash || $appointment->payment_status === PaymentStatus::Paid) {
            throw new BookingException(__('This booking is not waiting for a GCash payment.'));
        }

        // Paying a little late is fine as long as nobody else took the slot meanwhile.
        if ($appointment->isHoldExpired()
            && ! $this->availability->isAvailable($appointment->court, $appointment->effective_datetime, $appointment->duration_hours, $appointment->id)) {
            throw new BookingException(__('The hold on this slot ran out and it has been taken. If you already paid, please message us.'));
        }

        try {
            $appointment->update([
                'gcash_reference' => $reference,
                'payment_status' => PaymentStatus::ForVerification,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new BookingException(__('That GCash reference number was already used for another booking.'));
        }

        return $appointment;
    }

    /**
     * The owner checked the money arrived (GCash) or collected it (cash): booking is confirmed and paid.
     */
    public function confirmPayment(Appointment $appointment): Appointment
    {
        if ($appointment->isCancelled()) {
            throw new BookingException(__('A cancelled booking cannot be confirmed. Please create a new booking instead.'));
        }

        $appointment->update([
            'status' => AppointmentStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
        ]);

        return $appointment;
    }

    /**
     * Cancel a booking. Customers are held to the cancellation cut-off; staff are not.
     * The payment status is left alone so the owner can see a paid booking needs a refund.
     */
    public function cancel(Appointment $appointment, bool $byStaff = false): Appointment
    {
        if ($appointment->isCancelled()) {
            throw new BookingException(__('This booking is already cancelled.'));
        }

        if (! $byStaff && ! $appointment->canBeCancelledByClient()) {
            throw new BookingException(__('This booking can no longer be cancelled online. Please message us.'));
        }

        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        return $appointment;
    }

    /**
     * Move a booking to another court or time (staff only). The price is recalculated.
     */
    public function reschedule(Appointment $appointment, Court $court, CarbonInterface $start, int $hours, ?string $notes = null): Appointment
    {
        return DB::transaction(function () use ($appointment, $court, $start, $hours, $notes) {
            Court::query()->whereKey($court->getKey())->lockForUpdate()->first();

            $this->assertBookable($court, $start, $hours, asStaff: true, ignoreAppointmentId: $appointment->id);

            $appointment->update([
                'court_id' => $court->getKey(),
                'effective_datetime' => $start,
                'duration_hours' => $hours,
                'ends_at' => $start->copy()->addHours($hours),
                'total_amount' => $this->prices->total($court, $start, $hours),
                'notes' => filled($notes) ? $notes : null,
            ]);

            return $appointment;
        });
    }

    /**
     * Cancel unpaid GCash bookings whose payment hold ran out. Returns how many were released.
     */
    public function expireUnpaid(): int
    {
        return Appointment::query()
            ->where('status', AppointmentStatus::Pending->value)
            ->where('payment_method', PaymentMethod::Gcash->value)
            ->where('payment_status', PaymentStatus::Unpaid->value)
            ->where('created_at', '<', now()->subMinutes($this->business->holdMinutes()))
            ->update([
                'status' => AppointmentStatus::Cancelled->value,
                'updated_at' => now(),
            ]);
    }
}
