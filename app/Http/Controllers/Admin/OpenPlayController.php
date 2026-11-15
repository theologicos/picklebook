<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SkillLevel;
use App\Exceptions\OpenPlayException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OpenPlaySessionRequest;
use App\Models\Court;
use App\Models\OpenPlayEntry;
use App\Models\OpenPlayGame;
use App\Models\OpenPlaySession;
use App\Services\OpenPlayService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OpenPlayController extends Controller
{
    public function __construct(protected OpenPlayService $openPlay) {}

    public function index(): View
    {
        $sessions = OpenPlaySession::query()
            ->with('court')
            ->withCount(['entries as joined_count' => fn ($q) => $q->active()])
            ->orderByDesc('starts_at')
            ->paginate(15);

        return view('admin.open-play.index', compact('sessions'));
    }

    public function create(): View
    {
        return view('admin.open-play.create', [
            'courts' => Court::query()->active()->orderBy('id')->get(),
            'skillLevels' => SkillLevel::cases(),
        ]);
    }

    public function store(OpenPlaySessionRequest $request): RedirectResponse
    {
        $court = Court::query()->findOrFail($request->integer('court_id'));

        try {
            $session = $this->openPlay->schedule(
                $court,
                $request->startsAt(),
                $request->integer('hours'),
                $request->string('title')->trim()->toString(),
                $request->integer('max_players'),
                $request->integer('fee_per_player'),
                SkillLevel::from($request->input('skill_level')),
                $request->input('notes'),
            );
        } catch (OpenPlayException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('admin.open-play.show', $session)->with('success', __('Open play scheduled.'));
    }

    // The control room: who signed up, the queue, and the game on the court.
    public function show(OpenPlaySession $session): View
    {
        $session->load('court');

        return view('admin.open-play.show', [
            'session' => $session,
            'entries' => $session->entries()->active()->with('user')->orderBy('created_at')->get(),
            'queue' => $this->openPlay->queue($session),
            'currentGame' => $this->openPlay->currentGame($session),
        ]);
    }

    public function start(OpenPlaySession $session): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->start($session), __('The session is live. Check players in to build the queue.'));
    }

    public function close(OpenPlaySession $session): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->close($session), __('The session is closed.'));
    }

    public function cancel(OpenPlaySession $session): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->cancelSession($session), __('The session was cancelled.'));
    }

    public function startGame(OpenPlaySession $session): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->startNextGame($session), __('Next game started.'));
    }

    public function finishGame(OpenPlayGame $game): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->finishGame($game), __('Game finished. Those players are back in the queue.'));
    }

    public function checkIn(OpenPlayEntry $entry): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->checkIn($entry), __('Player checked in and added to the queue.'));
    }

    public function remove(OpenPlayEntry $entry): RedirectResponse
    {
        return $this->attempt(fn () => $this->openPlay->leave($entry), __('Player removed from the session.'));
    }

    /** Run an action; on a broken rule go back with the friendly message, otherwise go back with a success note. */
    protected function attempt(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OpenPlayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}