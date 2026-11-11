<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(protected BookingService $bookings)
    {
        // pass
    }

    public function index(Request $request): View
    {
        $filter = in_array($request->query('filter'), ['verify', 'upcoming', 'cancelled', 'all'], true)
            ? $request->query('filter')
            : 'upcoming';

        $query = Appointment::query()->with(['court', 'user']);

        match ($filter) {
            'verify' => $query->awaitingVerification(),
            'upcoming' => $query->notCancelled()->upcoming(),
            'cancelled' => $query->where('status', AppointmentStatus::Cancelled->value),
            default => null,
        };

        $bookings = $query
            ->orderBy('effective_datetime', in_array($filter, ['verify', 'upcoming'], true) ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        $verifyCount = Appointment::query()->awaitingVerification()->count();

        return view('admin.bookings.index', compact('bookings', 'filter', 'verifyCount'));
    }

    // The owner checked the GCash payment (or collected cash).
    public function confirm(Appointment $appointment): RedirectResponse
    {
        try {
            $this->bookings->confirmPayment($appointment);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Booking :code confirmed.', ['code' => $appointment->reference_code]));
    }

    public function cancel(Appointment $appointment): RedirectResponse
    {
        try {
            $this->bookings->cancel($appointment, byStaff: true);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Booking :code cancelled.', ['code' => $appointment->reference_code]));
    }
}