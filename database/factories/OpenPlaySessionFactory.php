<?php

namespace Database\Factories;

use App\Enums\OpenPlaySessionStatus;
use App\Enums\SkillLevel;
use App\Models\Court;
use App\Models\OpenPlaySession;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpenPlaySessionFactory extends Factory
{
    // return array<string, mixed>   
    public function definition(): array
    {
        $start = now()->addDay()->setTime(18, 0);

        return [
            'court_id' => Court::factory(),
            'title' => 'Open Play',
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(2),
            'max_players' => 16,
            'fee_per_player' => 100,
            'skill_level' => SkillLevel::All,
            'status' => OpenPlaySessionStatus::Scheduled,
            'notes' => null,
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['status' => OpenPlaySessionStatus::Live]);
    }
}