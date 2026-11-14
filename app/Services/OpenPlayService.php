<?php

namespace App\Services;

use App\Enums\OpenPlayEntryStatus;
use App\Enums\OpenPlaySessionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SkillLevel;
use App\Exceptions\OpenPlayException;
use App\Models\Court;
use App\Models\OpenPlayEntry;
use App\Models\OpenPlayGame;
use App\Models\OpenPlaySession;
use App\Models\User;
use App\Support\Business;
use App\Support\Format;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OpenPlayService
{
    public const PLAYERS_PER_GAME = 4;

    public function __construct(
        protected Business $business,
        protected AvailabilityService $availability,
    ) {
        //
    }

    // Schedule a new open play session.
    public function schedule(
        Court $court,
        CarbonInterface $start,
        int $hours,
        string $title,
        int $maxPlayers,
        int $fee,
        SkillLevel $skill = SkillLevel::All,
        ?string $notes = null,
    ): OpenPlaySession {
        $end = $start->copy()->addHours($hours);

        if (! $court->is_active) {
            throw new OpenPlayException(__('This court is not available.'));
        }

        if ($hours < 1) {
            throw new OpenPlayException(__('A session must last at least 1 hour.'));
        }

        if ($start->minute !== 0 || $start->second !== 0) {
            throw new OpenPlayException(__('Sessions must start on the hour.'));
        }

        if ($start->hour < $this->business->openHour() || $start->hour + $hours > $this->business->closeHour()) {
            throw new OpenPlayException(__('We are open from :open to :close.', [
                'open' => Format::hour($this->business->openHour()),
                'close' => Format::hour($this->business->closeHour()),
            ]));
        }

        if ($end->lte(now())) {
            throw new OpenPlayException(__('That time is already over.'));
        }

        if ($maxPlayers < self::PLAYERS_PER_GAME) {
            throw new OpenPlayException(__('An open play needs room for at least :count players.', ['count' => self::PLAYERS_PER_GAME]));
        }

        if ($fee < 0) {
            throw new OpenPlayException(__('The fee cannot be negative.'));
        }

        return DB::transaction(function () use ($court, $start, $hours, $end, $title, $maxPlayers, $fee, $skill, $notes) {
            // Lock the court row so two owners can't grab the same hour at once.
            Court::query()->whereKey($court->getKey())->lockForUpdate()->first();

            if (! $this->availability->isAvailable($court, $start, $hours)) {
                throw new OpenPlayException(__('That court already has a booking or another open play at that time.'));
            }

            return OpenPlaySession::create([
                'court_id' => $court->getKey(),
                'title' => $title,
                'starts_at' => $start,
                'ends_at' => $end,
                'max_players' => $maxPlayers,
                'fee_per_player' => $fee,
                'skill_level' => $skill,
                'status' => OpenPlaySessionStatus::Scheduled,
                'notes' => filled($notes) ? $notes : null,
            ]);
        });
    }

    // Start a scheduled session.
    public function start(OpenPlaySession $session): OpenPlaySession
    {
        if ($session->status !== OpenPlaySessionStatus::Scheduled) {
            throw new OpenPlayException(__('This session cannot be started.'));
        }

        if ($session->ends_at->isPast()) {
            throw new OpenPlayException(__('This session is already over.'));
        }

        $session->update(['status' => OpenPlaySessionStatus::Live]);

        return $session;
    }

    // Close the session and finish any ongoing game.
    public function close(OpenPlaySession $session): OpenPlaySession
    {
        if (! in_array($session->status, [OpenPlaySessionStatus::Scheduled, OpenPlaySessionStatus::Live], true)) {
            throw new OpenPlayException(__('This session is already closed.'));
        }

        DB::transaction(function () use ($session) {
            foreach ($session->games()->inProgress()->get() as $game) {
                $this->finishGame($game);
            }

            $session->update(['status' => OpenPlaySessionStatus::Closed]);
        });

        return $session;
    }

    // Cancel a session that has not started yet.
    public function cancelSession(OpenPlaySession $session): OpenPlaySession
    {
        if ($session->status !== OpenPlaySessionStatus::Scheduled) {
            throw new OpenPlayException(__('Only a session that has not started can be cancelled. Close it instead.'));
        }

        $session->update(['status' => OpenPlaySessionStatus::Cancelled]);

        return $session;
    }

    // Automatically close sessions whose scheduled time has ended.
    public function closeEndedSessions(): int
    {
        $sessions = OpenPlaySession::query()
            ->whereIn('status', [OpenPlaySessionStatus::Scheduled->value, OpenPlaySessionStatus::Live->value])
            ->where('ends_at', '<', now())
            ->get();

        foreach ($sessions as $session) {
            $this->close($session);
        }

        return $sessions->count();
    }
}