<?php

namespace App\Support;

/**
 * Helpers for Philippine mobile numbers.
 *
 * Players type their number in many ways (0917 123 4567, +63 917 123 4567,
 * 917-123-4567). We always store the local 11 digit form, 09XXXXXXXXX, so the
 * unique index on users.mobile_number actually catches duplicates.
 */
class PhoneNumber
{
    /**
     * Normalise to 09XXXXXXXXX, or return null when the value is not a valid PH mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        if ($digits === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            // +63 917 123 4567 -> 0917 123 4567
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            // 917 123 4567 (leading zero left out)
            $digits = '0'.$digits;
        }

        return preg_match('/^09\d{9}$/', $digits) === 1 ? $digits : null;
    }

    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * Readable form for screens and messages: 0917 123 4567.
     */
    public static function format(?string $value): string
    {
        $number = self::normalize($value);

        if ($number === null) {
            return (string) $value;
        }

        return substr($number, 0, 4).' '.substr($number, 4, 3).' '.substr($number, 7);
    }

    /**
     * International form, useful for tel: links and SMS gateways: +639171234567.
     */
    public static function international(?string $value): ?string
    {
        $number = self::normalize($value);

        return $number === null ? null : '+63'.substr($number, 1);
    }
}
