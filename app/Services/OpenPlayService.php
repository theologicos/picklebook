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

    // Register a player and reserve their spot.
    public function join(OpenPlaySession $session, User $user, PaymentMethod $method): OpenPlayEntry
    {
        if ($method === PaymentMethod::Cash && ! $this->business->allowPayAtCourt()) {
            throw new OpenPlayException(__('Paying at the court is not available. Please pay with GCash.'));
        }

        if ($method === PaymentMethod::Gcash && ! $this->business->hasGcash()) {
            throw new OpenPlayException(__('GCash payments are not set up yet. Please message us to join.'));
        }

        return DB::transaction(function () use ($session, $user, $method) {
            // Lock the session row so two players can't take the last spot together.
            $locked = OpenPlaySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if (! $locked->isJoinable()) {
                throw new OpenPlayException(__('This open play is no longer taking players.'));
            }

            $entry = OpenPlayEntry::query()->firstOrNew([
                'open_play_session_id' => $locked->getKey(),
                'user_id' => $user->getKey(),
            ]);

            if ($entry->exists && $entry->status !== OpenPlayEntryStatus::Left) {
                throw new OpenPlayException(__('You already joined this session.'));
            }

            if ($locked->entries()->active()->count() >= $locked->max_players) {
                throw new OpenPlayException(__('Sorry, this session is full.'));
            }

            // A player who left earlier simply re-uses their old row.
            $entry->fill([
                'status' => OpenPlayEntryStatus::Registered,
                'payment_method' => $method,
                'payment_status' => PaymentStatus::Unpaid,
                'gcash_reference' => null,
                'amount' => $locked->fee_per_player,
                'queued_at' => null,
            ])->save();

            return $entry;
        });
    }

    // Submit a GCash reference number for verification.
    public function submitGcashReference(OpenPlayEntry $entry, string $reference): OpenPlayEntry
    {
        $reference = preg_replace('/\D+/', '', $reference) ?? '';

        if ($entry->status === OpenPlayEntryStatus::Left
            || $entry->payment_method !== PaymentMethod::Gcash
            || $entry->payment_status === PaymentStatus::Paid) {
            throw new OpenPlayException(__('This sign-up is not waiting for a GCash payment.'));
        }

        if (strlen($reference) < 10 || strlen($reference) > 20) {
            throw new OpenPlayException(__('Enter the reference number from your GCash receipt (10 to 20 digits).'));
        }

        try {
            $entry->update([
                'gcash_reference' => $reference,
                'payment_status' => PaymentStatus::ForVerification,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new OpenPlayException(__('That GCash reference number was already used.'));
        }

        return $entry;
    }

    // Temporarily remove a player from the queue.
    public function stepOut(OpenPlayEntry $entry): OpenPlayEntry
    {
        if ($entry->status !== OpenPlayEntryStatus::Waiting) {
            throw new OpenPlayException(__('You are not in the queue right now.'));
        }

        $entry->update(['status' => OpenPlayEntryStatus::Registered, 'queued_at' => null]);

        return $entry;
    }

    // Return a paid player to the back of the queue.
    public function enterQueue(OpenPlayEntry $entry): OpenPlayEntry
    {
        if ($entry->session->status !== OpenPlaySessionStatus::Live) {
            throw new OpenPlayException(__('The queue opens when the session starts.'));
        }

        if ($entry->status !== OpenPlayEntryStatus::Registered) {
            throw new OpenPlayException(__('You are already in the queue or have left this session.'));
        }

        if (! $entry->isPaid()) {
            throw new OpenPlayException(__('Please check in with the staff first.'));
        }

        $entry->update(['status' => OpenPlayEntryStatus::Waiting, 'queued_at' => now()]);

        return $entry;
    }

    // Remove a player from the session completely.
    public function leave(OpenPlayEntry $entry): OpenPlayEntry
    {
        if ($entry->status === OpenPlayEntryStatus::Left) {
            throw new OpenPlayException(__('You already left this session.'));
        }

        if ($entry->status === OpenPlayEntryStatus::Playing) {
            throw new OpenPlayException(__('Please finish your game first.'));
        }

        $entry->update(['status' => OpenPlayEntryStatus::Left, 'queued_at' => null]);

        return $entry;
    }

    // Mark the player as paid and add them to the queue.
    public function checkIn(OpenPlayEntry $entry): OpenPlayEntry
    {
        if ($entry->session->status !== OpenPlaySessionStatus::Live) {
            throw new OpenPlayException(__('Start the session before checking players in.'));
        }

        if ($entry->status !== OpenPlayEntryStatus::Registered) {
            throw new OpenPlayException(__('This player is already checked in or has left.'));
        }

        $entry->update([
            'payment_status' => PaymentStatus::Paid,
            'status' => OpenPlayEntryStatus::Waiting,
            'queued_at' => now(),
        ]);

        return $entry;
    }

    // Retrieve waiting players in queue order.
    public function queue(OpenPlaySession $session): Collection
    {
        return $session->entries()->with('user')->waiting()->queueOrder()->get();
    }

    // Get the player's current position in the queue.
    public function position(OpenPlayEntry $entry): ?int
    {
        if ($entry->status !== OpenPlayEntryStatus::Waiting) {
            return null;
        }

        $ids = array_map('intval', $this->queue($entry->session)->modelKeys());
        $index = array_search((int) $entry->getKey(), $ids, true);

        return $index === false ? null : $index + 1;
    }

    // Retrieve the current game for a session.
    public function currentGame(OpenPlaySession $session): ?OpenPlayGame
    {
        return $session->games()->inProgress()->with('entries.user')->first();
    }

        // Start a game with the next four waiting players.
    public function startNextGame(OpenPlaySession $session): OpenPlayGame
    {
        return DB::transaction(function () use ($session) {
            $locked = OpenPlaySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== OpenPlaySessionStatus::Live) {
                throw new OpenPlayException(__('Start the session first.'));
            }

            if ($locked->games()->inProgress()->exists()) {
                throw new OpenPlayException(__('A game is already on the court. Finish it first.'));
            }

            $next = $locked->entries()->waiting()->queueOrder()->limit(self::PLAYERS_PER_GAME)->get();

            if ($next->count() < self::PLAYERS_PER_GAME) {
                throw new OpenPlayException(__('We need :needed players in the queue, but only :waiting are waiting.', [
                    'needed' => self::PLAYERS_PER_GAME,
                    'waiting' => $next->count(),
                ]));
            }

            $game = $locked->games()->create(['started_at' => now()]);
            $game->entries()->attach($next->modelKeys());

            OpenPlayEntry::query()
                ->whereIn('id', $next->modelKeys())
                ->update(['status' => OpenPlayEntryStatus::Playing->value, 'queued_at' => null]);

            return $game->load('entries.user');
        });
    }

    // Finish the game and return its players to the back of the queue.
    public function finishGame(OpenPlayGame $game): OpenPlayGame
    {
        if ($game->isFinished()) {
            throw new OpenPlayException(__('This game is already finished.'));
        }

        DB::transaction(function () use ($game) {
            $game->update(['ended_at' => now()]);

            OpenPlayEntry::query()
                ->whereIn('id', $game->entries()->pluck('open_play_entries.id')->all())
                ->where('status', OpenPlayEntryStatus::Playing->value)
                ->update([
                    'status' => OpenPlayEntryStatus::Waiting->value,
                    'queued_at' => now(),
                    'games_played' => DB::raw('games_played + 1'),
                ]);
        });

        return $game;
    }
}