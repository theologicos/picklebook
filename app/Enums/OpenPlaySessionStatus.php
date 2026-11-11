<?php

namespace App\Enums;

enum OpenPlaySessionStatus: string
{
    // Created, not started yet. Players can already sign up.
    case Scheduled = 'scheduled';
    // The owner opened the doors, check-in, and games are running.
    case Live = 'live';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Upcoming'),
            self::Live => __('Live now'),
            self::Closed => __('Finished'),
            self::Cancelled => __('Cancelled'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Scheduled => 'blue',
            self::Live => 'green',
            self::Closed => 'gray',
            self::Cancelled => 'red',
        };
    }
}