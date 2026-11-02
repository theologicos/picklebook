<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    /** Customer submitted a GCash reference; waiting for the owner to check it. */
    case ForVerification = 'for_verification';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::ForVerification => __('Verifying payment'),
            self::Paid => __('Paid'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'red',
            self::ForVerification => 'blue',
            self::Paid => 'green',
        };
    }
}
