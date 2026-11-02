<?php

namespace App\Models;

use Database\Factories\CourtFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Court extends Model
{
    /** @use HasFactory<CourtFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'hourly_rate',
        'peak_hourly_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'hourly_rate' => 'integer',
            'peak_hourly_rate' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Price per hour when it's a peak hour. Courts without a peak rate charge the normal rate.
     */
    public function effectivePeakRate(): int
    {
        return $this->peak_hourly_rate ?? $this->hourly_rate;
    }

    public function hasPeakPricing(): bool
    {
        return $this->peak_hourly_rate !== null && $this->peak_hourly_rate !== $this->hourly_rate;
    }
}
