<?php

namespace Database\Factories;

use App\Enums\OpenPlayEntryStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\OpenPlayEntry;
use App\Models\OpenPlaySession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpenPlayEntryFactory extends Factory
{
    // return array<string, mixed>
    
    public function definition(): array
    {
        return [
            'open_play_session_id' => OpenPlaySession::factory(),
            'user_id' => User::factory(),
            'status' => OpenPlayEntryStatus::Registered,
            'payment_method' => PaymentMethod::Cash,
            'payment_status' => PaymentStatus::Unpaid,
            'amount' => 100,
            'queued_at' => null,
            'games_played' => 0,
        ];
    }

    // Checked in, paid and standing in the line.
    public function waiting(): static
    {
        return $this->state(fn () => [
            'status' => OpenPlayEntryStatus::Waiting,
            'payment_status' => PaymentStatus::Paid,
            'queued_at' => now(),
        ]);
    }
}