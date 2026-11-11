<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Court;
use App\Support\Business;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Business $business): View
    {
        $today = today();

        $bookedToday = Appointment::query()->notCancelled()->onDate($today);
        $hoursBooked = (int) (clone $bookedToday)->sum('duration_hours');
        $hoursAvailable = Court::query()->active()->count() * count($business->bookableHours());

        $stats = [
            'bookings_today' => (clone $bookedToday)->count(),
            'hours_today' => $hoursBooked,
            'utilization' => $hoursAvailable > 0 ? (int) round($hoursBooked / $hoursAvailable * 100) : 0,
            'revenue_today' => (int) Appointment::query()->collected()->onDate($today)->sum('total_amount'),
            'revenue_month' => (int) Appointment::query()->collected()
                ->whereBetween('effective_datetime', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()])
                ->sum('total_amount'),
            'to_verify' => Appointment::query()->awaitingVerification()->count()
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
