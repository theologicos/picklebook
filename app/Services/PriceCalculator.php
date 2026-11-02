<?php

namespace App\Services;

use App\Models\Court;
use App\Support\Business;
use Carbon\CarbonInterface;

/**
 * Prices a booking hour by hour, so a 4pm-6pm booking is charged one normal
 * hour and one peak hour when peak starts at 5pm.
 */
class PriceCalculator
{
    public function __construct(protected Business $business)
    {
        //
    }

    /**
     * @return list<array{hour: int, rate: int, peak: bool}>
     */
    public function breakdown(Court $court, CarbonInterface $start, int $hours): array
    {
        $lines = [];

        for ($i = 0; $i < $hours; $i++) {
            $hour = $start->copy()->addHours($i)->hour;
            $peak = $this->business->isPeak($hour);

            $lines[] = [
                'hour' => $hour,
                'rate' => $peak ? $court->effectivePeakRate() : $court->hourly_rate,
                'peak' => $peak,
            ];
        }

        return $lines;
    }

    public function total(Court $court, CarbonInterface $start, int $hours): int
    {
        return array_sum(array_column($this->breakdown($court, $start, $hours), 'rate'));
    }
}
