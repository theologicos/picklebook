<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Appointment;
use App\Models\Court;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->addDay()->setTime(10, 0);

        return [
            'reference_code' => Appointment::generateReferenceCode(),
            'court_id' => Court::factory(),
            'user_id' => User::factory(),
            'effective_datetime' => $start,
            'duration_hours' => 1,
            'ends_at' => $start->copy()->addHour(),
            'status' => AppointmentStatus::Confirmed,
            'total_amount' => 300,
            'payment_method' => PaymentMethod::Gcash,
            'payment_status' => PaymentStatus::Paid,
        ];
    }

    /** Place the booking at a given start time and length. */
    public function at(CarbonInterface $start, int $hours = 1): static
    {
        return $this->state(fn () => [
            'effective_datetime' => $start,
            'duration_hours' => $hours,
            'ends_at' => $start->copy()->addHours($hours),
        ]);
    }

    /** Unpaid GCash booking that is still holding its slot. */
    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Pending,
            'payment_method' => PaymentMethod::Gcash,
            'payment_status' => PaymentStatus::Unpaid,
        ]);
    }

    /** Customer sent a GCash reference; the owner still has to check it. */
    public function forVerification(): static
    {
        return $this->state(fn () => [
            'status' => AppointmentStatus::Pending,
            'payment_method' => PaymentMethod::Gcash,
            'payment_status' => PaymentStatus::ForVerification,
            'gcash_reference' => fake()->unique()->numerify('#############'),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => AppointmentStatus::Cancelled]);
    }
}
