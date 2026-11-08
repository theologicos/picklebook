<?php

namespace App\Http\Controllers;

use App\Services\AvailabilityService;
use App\Support\Business;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Throwable;

class CourtScheduleController extends Controller
{
    public function index(Request $request, AvailabilityService $availability, Business $business): View
    {
        $date = $this->resolveDate($request->query('date'), $business);

        return view('book.index', [
            'date' => $date,
            'grid' => $availability->dayGrid($date),
            // Day picker: today up to the last day that can be booked.
            'dates' => collect(range(0, $business->advanceDays()))->map(fn (int $i) => today()->addDays($i)),
        ]);
    }

    // Invalid date range fall back to the nearest valid day.
    protected function resolveDate(mixed $value, Business $business): CarbonInterface
    {
        try {
            $date = Carbon::createFromFormat('!Y-m-d', (string) $value);
        } catch (Throwable) {
            return today();
        }

        if (! $date) {
            return today();
        }

        $first = today();
        $last = $business->lastBookableDate()->startOfDay();

        return $date->lt($first) ? $first : ($date->gt($last) ? $last : $date);
    }
}