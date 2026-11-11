<?php

namespace App\Enums;

enum SkillLevel: string
{
    case All = 'all';
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Advanced = 'advanced';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All levels'),
            self::Beginner => __('Beginner'),
            self::Intermediate => __('Intermediate'),
            self::Advanced => __('Advanced'),
        };
    }
}