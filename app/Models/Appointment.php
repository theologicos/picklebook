<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Support\Business;
use Carbon\CarbonInterface;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A court booking. The table is called "appointments"; on screen we say "booking".
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $fillable = [
        'reference_code',
        'court_id',
        'user_id',
        'effective_datetime',
        'duration_hours',
        'ends_at',
        'status',
        'total_amount',
        'payment_method',
        'payment_status',
        'gcash_reference',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_datetime' => 'datetime',
            'ends_at' => 'datetime',
            'duration_hours' => 'integer',
            'total_amount' => 'integer',
            'status' => AppointmentStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
        ];
    }

    public function court(): BelongsTo
    {
        return $this->belongsTo(Court::class);
    }

    /** The customer who booked. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A short, readable, hard-to-confuse code (no 0/O or 1/I) such as PB-7K3QX2.
     */
    public static function generateReferenceCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $suffix = '';
            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $code = 'PB-'.$suffix;
        } while (static::query()->where('reference_code', $code)->exists());

        return $code;
    }

    // ---- Scopes ------------------------------------------------------------

    /**
     * Bookings that currently occupy their time slot.
     *
     * Confirmed bookings always do. A pending booking does too, unless it is an
     * unpaid GCash booking whose payment hold has run out - then the slot is
     * free again, even before the clean-up command marks it cancelled.
     */
    public function scopeBlocking(Builder $query, ?int $holdMinutes = null): Builder
    {
        $holdMinutes ??= app(Business::class)->holdMinutes();
        $holdStart = now()->subMinutes($holdMinutes);

        return $query->where(function (Builder $q) use ($holdStart) {
            $q->where('status', AppointmentStatus::Confirmed->value)
                ->orWhere(function (Builder $q) use ($holdStart) {
                    $q->where('status', AppointmentStatus::Pending->value)
                        ->where(function (Builder $q) use ($holdStart) {
                            $q->where('payment_status', '!=', PaymentStatus::Unpaid->value)
                                ->orWhere('created_at', '>=', $holdStart);
                        });
                });
        });
    }

    /** Bookings whose time range intersects [start, end). Back-to-back bookings do not overlap. */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query->where('effective_datetime', '<', $end)->where('ends_at', '>', $start);
    }

    /** Bookings that start on the given calendar day. */
    public function scopeOnDate(Builder $query, CarbonInterface $date): Builder
    {
        return $query
            ->where('effective_datetime', '>=', $date->copy()->startOfDay())
            ->where('effective_datetime', '<', $date->copy()->addDay()->startOfDay());
    }

    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', AppointmentStatus::Cancelled->value);
    }

    /** The customer sent a GCash reference and the owner still has to check it. */
    public function scopeAwaitingVerification(Builder $query): Builder
    {
        return $query
            ->where('status', AppointmentStatus::Pending->value)
            ->where('payment_status', PaymentStatus::ForVerification->value);
    }

    /** Money actually received: confirmed bookings that are paid. */
    public function scopeCollected(Builder $query): Builder
    {
        return $query
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('payment_status', PaymentStatus::Paid->value);
    }

    /** Bookings that have not finished yet. */
    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('ends_at', '>', now());
    }

    // ---- State helpers -----------------------------------------------------

    /**
     * When an unpaid GCash booking stops holding its slot, or null if no hold applies.
     */
    public function holdExpiresAt(): ?CarbonInterface
    {
        if ($this->status !== AppointmentStatus::Pending || $this->payment_status !== PaymentStatus::Unpaid) {
            return null;
        }

        return $this->created_at?->copy()->addMinutes(app(Business::class)->holdMinutes());
    }

    public function isHoldExpired(): bool
    {
        return $this->holdExpiresAt()?->isPast() ?? false;
    }

    /** The customer still needs to pay and submit a GCash reference. */
    public function isAwaitingPayment(): bool
    {
        return $this->status === AppointmentStatus::Pending
            && $this->payment_method === PaymentMethod::Gcash
            && $this->payment_status === PaymentStatus::Unpaid
            && ! $this->isHoldExpired();
    }

    /** The customer paid and is waiting for the owner to check the GCash reference. */
    public function isAwaitingVerification(): bool
    {
        return $this->status === AppointmentStatus::Pending
            && $this->payment_status === PaymentStatus::ForVerification;
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function hasEnded(): bool
    {
        return $this->ends_at->isPast();
    }

    /**
     * Customers may cancel on their own until the cancellation cut-off.
     * A booking that hasn't been paid for can always be dropped.
     */
    public function canBeCancelledByClient(): bool
    {
        if ($this->isCancelled() || $this->hasEnded()) {
            return false;
        }

        if ($this->payment_status === PaymentStatus::Unpaid && $this->status === AppointmentStatus::Pending) {
            return true;
        }

        $cutoff = $this->effective_datetime->copy()->subHours(app(Business::class)->cancellationHours());

        return now()->lt($cutoff);
    }
}
