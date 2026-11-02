<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Gcash = 'gcash';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Gcash => __('GCash'),
            self::Cash => __('Pay at the court'),
        };
    }
}
