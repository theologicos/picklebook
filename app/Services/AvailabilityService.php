<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Court;
use App\Support\Business;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use App\Models\OpenPlaySession;

class AvailabilityService
{
    public const AVAILABLE = 'available';
    public const BOOKED = 'booked';
    public const PAST = 'past';
    public const OPEN_PLAY = 'open_play';

    public function __construct(protected Business $business)
    {
        //
    }

    /**
     * Every active court with one slot per bookable hour for the given day.
     *
     * Each slot: ['hour' => 18, 'start' => Carbon, 'state' => available|booked|past, 'appointment' => ?Appointment].
     * The appointment is only filled when the slot is booked.
     *
     * return Collection<int, array{court: Court, slots: list<array{hour: int, start: CarbonInterface, state: string, appointment: ?Appointment}>}>
     */

    // Get every active court with one slot per bookable hour for the given day.
    public function dayGrid(CarbonInterface $date): Collection
    {
        $courts = Court::query()
            ->active()
            ->orderBy('id')
            ->get();

        $appointments = Appointment::query()
            ->with('user')
            ->blocking()
            ->onDate($date)
            ->get();

        $sessions = OpenPlaySession::query()
            ->notCancelled()
            ->where('starts_at', '>=', $date->copy()->startOfDay())
            ->where('starts_at', '<', $date->copy()->addDay()->startOfDay())
            ->get();

        // [court id][hour] => appointment, so each slot is a cheap lookup.
        $occupied = [];

        foreach ($appointments as $appointment) {
            for ($i = 0; $i < $appointment->duration_hours; $i++) {
                $hour = $appointment->effective_datetime->copy()->addHours($i)->hour;
                $occupied[$appointment->court_id][$hour] = $appointment;
            }
        }

        // Same idea for open play: [court id][hour] => session.
        $openPlay = [];

        foreach ($sessions as $session) {
            for ($t = $session->starts_at->copy(); $t->lt($session->ends_at); $t->addHour()) {
                $openPlay[$session->court_id][$t->hour] = $session;
            }
        }

        return $courts->map(function (Court $court) use ($date, $occupied, $openPlay) {
            $slots = [];

            foreach ($this->business->bookableHours() as $hour) {
                $start = $date->copy()->setTime($hour, 0);
                $appointment = $occupied[$court->id][$hour] ?? null;
                $session = $openPlay[$court->id][$hour] ?? null;

                $slots[] = [
                    'hour' => $hour,
                    'start' => $start,
                    'state' => match (true) {
                        $appointment !== null => self::BOOKED,
                        $session !== null => self::OPEN_PLAY,
                        $start->lte(now()) => self::PAST,
                        default => self::AVAILABLE,
                    },
                    'appointment' => $appointment,
                    'open_play_session' => $session,
                ];
            }

            return ['court' => $court, 'slots' => $slots];
        })->values();
    }

    /**
     * Is the whole [start, start + hours) window free on this court?
     * Pass the id of a booking being edited so it doesn't clash with itself.
     */
    
    // Check whether the entire time window is free on this court.
    public function isAvailable(
        Court $court,
        CarbonInterface $start,
        int $hours,
        ?int $ignoreAppointmentId = null,
    ): bool {
        $end = $start->copy()->addHours($hours);

        $hasBooking = Appointment::query()
            ->blocking()
            ->where('court_id', $court->id)
            ->overlapping($start, $end)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
            ->exists();

        if ($hasBooking) {
            return false;
        }

        return ! OpenPlaySession::query()
            ->notCancelled()
            ->where('court_id', $court->id)
            ->overlapping($start, $end)
            ->exists();
    }

    /**
     * How many hours in a row can be booked starting at this slot (0 when the slot itself is taken).
     * Limited by the next booking, closing time and the maximum booking length.
     */
    public function maxHours(Court $court, CarbonInterface $start, ?int $ignoreAppointmentId = null): int
    {
        $max = 0;

        for ($hours = 1; $hours <= $this->business->maxDuration(); $hours++) {
            if ($start->hour + $hours > $this->business->closeHour()) {
                break;
            }

            if (! $this->isAvailable($court, $start, $hours, $ignoreAppointmentId)) {
                break;
            }

            $max = $hours;
        }

        return $max;
    }
}
