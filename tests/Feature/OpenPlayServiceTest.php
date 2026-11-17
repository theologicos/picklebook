<?php

namespace Tests\Feature;

use App\Enums\OpenPlayEntryStatus;
use App\Enums\OpenPlaySessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\SkillLevel;
use App\Exceptions\OpenPlayException;
use App\Models\Appointment;
use App\Models\Court;
use App\Models\OpenPlayEntry;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\OpenPlayService;
use App\Support\Business;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenPlayServiceTest extends TestCase
{
    use RefreshDatabase;

    protected OpenPlayService $openPlay;

    protected Court $court;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:30:00'));

        app(Business::class)->save(['gcash_number' => '09171234567']);

        $this->openPlay = app(OpenPlayService::class);
        $this->court = Court::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // A session tomorrow 6pm to 8pm on our court, unless overridden.
    public function session(array $overrides = []): OpenPlaySession
    {
        return OpenPlaySession::factory()->create(array_merge(['court_id' => $this->court->id], $overrides));
    }

    // Five players already standing in the line, the first one joined longest ago.
    protected function fiveWaiting(OpenPlaySession $session): \Illuminate\Support\Collection
    {
        return collect(range(1, 5))->map(fn (int $i) => OpenPlayEntry::factory()->waiting()->create([
            'open_play_session_id' => $session->id,
            'queued_at' => now()->subMinutes(10 - $i),
        ]));
    }

    public function test_a_session_cannot_be_scheduled_over_a_booking(): void
    {
        Appointment::factory()->for($this->court)->at(Carbon::parse('2026-11-03 18:00'))->create();

        $this->expectException(OpenPlayException::class);

        $this->openPlay->schedule($this->court, Carbon::parse('2026-11-03 18:00'), 2, 'Friday Night', 16, 100, SkillLevel::All);
    }

    public function test_a_booking_cannot_be_made_over_a_session(): void
    {
        $this->session(); // 6pm to 8pm

        $availability = app(AvailabilityService::class);

        $this->assertFalse($availability->isAvailable($this->court, Carbon::parse('2026-11-03 19:00'), 1));
        $this->assertTrue($availability->isAvailable($this->court, Carbon::parse('2026-11-03 20:00'), 1));
    }

    public function test_the_day_grid_marks_open_play_hours(): void
    {
        $this->session();

        $grid = app(AvailabilityService::class)->dayGrid(Carbon::parse('2026-11-03'));
        $slots = collect($grid->first()['slots'])->keyBy('hour');

        $this->assertSame(AvailabilityService::OPEN_PLAY, $slots[18]['state']);
        $this->assertSame(AvailabilityService::OPEN_PLAY, $slots[19]['state']);
        $this->assertSame(AvailabilityService::AVAILABLE, $slots[20]['state']);
    }

    public function test_a_player_can_join_only_once(): void
    {
        $session = $this->session();
        $user = User::factory()->create();

        $entry = $this->openPlay->join($session, $user, PaymentMethod::Cash);

        $this->assertSame(OpenPlayEntryStatus::Registered, $entry->status);
        $this->assertSame(100, $entry->amount);

        $this->expectException(OpenPlayException::class);

        $this->openPlay->join($session, $user, PaymentMethod::Cash);
    }

    public function test_a_full_session_turns_players_away(): void
    {
        $session = $this->session(['max_players' => 4]);

        foreach (User::factory()->count(4)->create() as $user) {
            $this->openPlay->join($session, $user, PaymentMethod::Cash);
        }

        $this->expectException(OpenPlayException::class);

        $this->openPlay->join($session, User::factory()->create(), PaymentMethod::Cash);
    }

    public function test_check_in_needs_a_live_session(): void
    {
        $entry = $this->openPlay->join($this->session(), User::factory()->create(), PaymentMethod::Cash);

        $this->expectException(OpenPlayException::class);

        $this->openPlay->checkIn($entry);
    }

    public function test_checking_in_marks_paid_and_joins_the_queue(): void
    {
        $session = $this->session(['status' => OpenPlaySessionStatus::Live]);
        $entry = $this->openPlay->join($session, User::factory()->create(), PaymentMethod::Cash);

        $this->openPlay->checkIn($entry);

        $this->assertSame(OpenPlayEntryStatus::Waiting, $entry->fresh()->status);
        $this->assertTrue($entry->fresh()->isPaid());
        $this->assertSame(1, $this->openPlay->position($entry->fresh()));
    }

    public function test_the_next_game_takes_the_four_longest_waiting_players(): void
    {
        $session = $this->session(['status' => OpenPlaySessionStatus::Live]);
        $entries = $this->fiveWaiting($session);

        $game = $this->openPlay->startNextGame($session);

        $this->assertCount(4, $game->entries);
        $this->assertSame(OpenPlayEntryStatus::Playing, $entries[0]->fresh()->status);
        $this->assertSame(OpenPlayEntryStatus::Playing, $entries[3]->fresh()->status);
        $this->assertSame(OpenPlayEntryStatus::Waiting, $entries[4]->fresh()->status);
    }

    public function test_a_game_needs_four_players(): void
    {
        $session = $this->session(['status' => OpenPlaySessionStatus::Live]);
        OpenPlayEntry::factory()->waiting()->count(3)->create(['open_play_session_id' => $session->id]);

        $this->expectException(OpenPlayException::class);

        $this->openPlay->startNextGame($session);
    }

    public function test_finished_players_go_to_the_back_of_the_queue(): void
    {
        $session = $this->session(['status' => OpenPlaySessionStatus::Live]);
        $entries = $this->fiveWaiting($session);

        $game = $this->openPlay->startNextGame($session);

        Carbon::setTestNow(Carbon::parse('2026-11-02 10:50:00'));
        $this->openPlay->finishGame($game);

        $order = $this->openPlay->queue($session)->pluck('id')->all();

        // The fifth player waited the whole time, so they are first now.
        $this->assertEquals([
            $entries[4]->id, $entries[0]->id, $entries[1]->id, $entries[2]->id, $entries[3]->id,
        ], $order);
        $this->assertSame(1, $entries[0]->fresh()->games_played);
    }

    public function test_ended_sessions_are_closed_automatically(): void
    {
        $session = $this->session([
            'status' => OpenPlaySessionStatus::Live,
            'starts_at' => now()->subHours(3)->startOfHour(),
            'ends_at' => now()->subHour()->startOfHour(),
        ]);

        $this->assertSame(1, $this->openPlay->closeEndedSessions());
        $this->assertSame(OpenPlaySessionStatus::Closed, $session->fresh()->status);
    }
}