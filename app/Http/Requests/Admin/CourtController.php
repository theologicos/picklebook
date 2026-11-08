<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CourtRequest;
use App\Models\Court;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CourtController extends Controller
{
    public function index(): View
    {
        $courts = Court::query()
            ->withCount(['appointments as upcoming_bookings_count' => fn ($q) => $q->notCancelled()->upcoming()])
            ->orderBy('id')
            ->get();

        return view('admin.courts.index', compact('courts'));
    }

    public function create(): View
    {
        return view('admin.courts.create', ['court' => new Court(['is_active' => true])]);
    }

    public function store(CourtRequest $request): RedirectResponse
    {
        Court::create($request->validated());

        return redirect()->route('admin.courts.index')->with('success', __('Court added.'));
    }

    public function edit(Court $court): View
    {
        return view('admin.courts.edit', compact('court'));
    }

    public function update(CourtRequest $request, Court $court): RedirectResponse
    {
        // Old bookings keep the price they were made with so changing rates is safe.
        $court->update($request->validated());

        return redirect()->route('admin.courts.index')->with('success', __('Court updated.'));
    }

    // Courts are hidden and never deleted so booking history stays intact.
    public function toggle(Court $court): RedirectResponse
    {
        $court->update(['is_active' => ! $court->is_active]);

        return back()->with('success', $court->is_active ? __('Court is open for booking.') : __('Court is hidden from players.'));
    }
}