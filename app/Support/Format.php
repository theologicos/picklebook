<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Display helpers tuned for a Philippine audience: pesos, 12-hour clock.
 */
class Format
{
    /** ₱1,250 */
    public static function peso(int|float|null $amount): string
    {
        return '₱'.number_format((float) $amount, 0);
    }

    /** 6 AM, 12 PM, 10 PM */
    public static function hour(int $hour): string
    {
        return Carbon::createFromTime($hour % 24)->format('g A');
    }

    /** 6:00 PM */
    public static function time(CarbonInterface $moment): string
    {
        return $moment->format('g:i A');
    }

    /** 6:00 PM – 8:00 PM */
    public static function range(CarbonInterface $start, CarbonInterface $end): string
    {
        return self::time($start).' – '.self::time($end);
    }

    /** Mon, Nov 3 (follows the app locale) */
    public static function date(CarbonInterface $moment): string
    {
        return $moment->isoFormat('ddd, MMM D');
    }

    /** Monday, November 3, 2026 (follows the app locale) */
    public static function dateLong(CarbonInterface $moment): string
    {
        return $moment->isoFormat('dddd, MMMM D, YYYY');
    }
}
