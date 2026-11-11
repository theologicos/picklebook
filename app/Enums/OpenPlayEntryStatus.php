<?php

namespace App\Enums;

enum OpenPlayEntryStatus: string
{
    // Signed up, but not in the line (not checked in yet, or stepped out).
    case Registered = 'registered';
    // In the line, waiting for the next game.
    case Waiting = 'waiting';
    // On the court right now.
    case Playing = 'playing';
    case Left = 'left';

    public function label(): string
    {
        return match ($this) {
            self::Registered => __('Signed up'),
            self::Waiting => __('In the queue'),
            self::Playing => __('Playing'),
            self::Left => __('Left'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Registered => 'blue',
            self::Waiting => 'amber',
            self::Playing => 'green',
            self::Left => 'gray',
        };
    }
}