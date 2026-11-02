<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Court;
use App\Support\Business;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public const AVAILABLE = 'available';
    public const BOOKED = 'booked';
    public const PAST = 'past';

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
     * @return Collection<int, array{court: Court, slots: list<array{hour: int, start: CarbonInterface, state: string, appointment: ?Appointment}>}>
     */
    public function dayGrid(CarbonInterface $date): Collection
    {
        $courts = Court::query()->active()->orderBy('id')->get();

        $appointments = Appointment::query()
            ->with('user')
            ->blocking()
            ->onDate($date)
            ->get();

        // [court id][hour] => appointment, so each slot is a cheap lookup.
        $occupied = [];
        foreach ($appointments as $appointment) {
            for ($i = 0; $i < $appointment->duration_hours; $i++) {
                $hour = $appointment->effective_datetime->copy()->addHours($i)->hour;
                $occupied[$appointment->court_id][$hour] = $appointment;
            }
        }

        return $courts->map(function (Court $court) use ($date, $occupied) {
            $slots = [];

            foreach ($this->business->bookableHours() as $hour) {
                $start = $date->copy()->setTime($hour, 0);
                $appointment = $occupied[$court->id][$hour] ?? null;

                $slots[] = [
                    'hour' => $hour,
                    'start' => $start,
                    'state' => match (true) {
                        $appointment !== null => self::BOOKED,
                        $start->lte(now()) => self::PAST,
                        default => self::AVAILABLE,
                    },
                    'appointment' => $appointment,
                ];
            }

            return ['court' => $court, 'slots' => $slots];
        })->values();
    }

    /**
     * Is the whole [start, start + hours) window free on this court?
     * Pass the id of a booking being edited so it doesn't clash with itself.
     */
    public function isAvailable(Court $court, CarbonInterface $start, int $hours, ?int $ignoreAppointmentId = null): bool
    {
        $end = $start->copy()->addHours($hours);

        return ! Appointment::query()
            ->blocking()
            ->where('court_id', $court->id)
            ->overlapping($start, $end)
            ->when($ignoreAppointmentId, fn ($query) => $query->whereKeyNot($ignoreAppointmentId))
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
