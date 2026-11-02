<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    /** Slot is being held while the customer pays or the owner verifies the payment. */
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Confirmed => __('Confirmed'),
            self::Cancelled => __('Cancelled'),
        };
    }

    /** Colour name understood by the <x-badge> component. */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'amber',
            self::Confirmed => 'green',
            self::Cancelled => 'gray',
        };
    }
}
