<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BookingException;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Appointment;
use App\Models\Court;
use App\Rules\GcashReference;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\PriceCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(
        protected BookingService $bookings,
        protected AvailabilityService $availability,
        protected PriceCalculator $prices,
    ) {
        //
    }

    // My bookings
    public function index(Request $request): View
    {
        $all = $request->user()->appointments()->with('court')->orderByDesc('effective_datetime')->get();

        $upcoming = $all->filter(fn (Appointment $b) => ! $b->isCancelled() && ! $b->hasEnded())->values();
        $past = $all->diff($upcoming)->values();

        return view('bookings.index', compact('upcoming', 'past'));
    }

    // The "confirm your booking" page, reached by tapping a free slot.
    public function create(Request $request): View|RedirectResponse
    {
        $data = $request->validate([
            'court' => ['required', 'integer', 'exists:courts,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'hour' => ['required', 'integer', 'between:0,23'],
        ]);

        $court = Court::query()->active()->findOrFail($data['court']);
        $start = Carbon::createFromFormat('!Y-m-d', $data['date'])->setTime((int) $data['hour'], 0);

        try {
            $this->bookings->assertBookable($court, $start, 1);
        } catch (BookingException $e) {
            return redirect()->route('book', ['date' => $data['date']])->with('error', $e->getMessage());
        }

        $maxHours = $this->availability->maxHours($court, $start);

        return view('book.create', [
            'court' => $court,
            'start' => $start,
            'maxHours' => $maxHours,
            // [hours => total price], so the page can show the price for each length.
            'quotes' => collect(range(1, $maxHours))->mapWithKeys(fn (int $h) => [$h => $this->prices->total($court, $start, $h)]),
        ]);
    }

    public function store(StoreBookingRequest $request): RedirectResponse
    {
        $court = Court::query()->findOrFail($request->integer('court_id'));

        try {
            $booking = $this->bookings->book(
                $court,
                $request->user(),
                $request->startsAt(),
                $request->integer('hours'),
                PaymentMethod::from($request->input('payment_method')),
                $request->input('notes'),
            );
        } catch (BookingException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.show', $booking)->with('success', __('Booking saved.'));
    }

    public function show(Request $request, Appointment $appointment): View
    {
        $this->ownBooking($request, $appointment);

        return view('bookings.show', [
            'booking' => $appointment->load('court'),
            'breakdown' => $this->prices->breakdown($appointment->court, $appointment->effective_datetime, $appointment->duration_hours),
        ]);
    }

    public function submitPayment(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ownBooking($request, $appointment);

        $data = $request->validate(['gcash_reference' => ['required', 'string', new GcashReference]]);

        try {
            $this->bookings->submitGcashReference($appointment, $data['gcash_reference']);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', __('Thanks! We will confirm your booking once we see the payment.'));
    }

    public function cancel(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->ownBooking($request, $appointment);

        try {
            $this->bookings->cancel($appointment);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('bookings.index')->with('success', __('Booking cancelled.'));
    }

    // A player may only see and change their own bookings.
    protected function ownBooking(Request $request, Appointment $appointment): void
    {
        abort_unless((int) $appointment->user_id === (int) $request->user()->id, 404);
    }
}