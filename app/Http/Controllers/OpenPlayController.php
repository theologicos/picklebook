<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\OpenPlayException;
use App\Models\OpenPlayEntry;
use App\Models\OpenPlaySession;
use App\Rules\GcashReference;
use App\Services\OpenPlayService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OpenPlayController extends Controller
{
    public function __construct(protected OpenPlayService $openPlay)
    {
        //
    }

    // Upcoming and live sessions. Open to guests; joining needs an account.
    public function index(Request $request): View
    {
        $sessions = OpenPlaySession::query()
            ->with('court')
            ->withCount(['entries as joined_count' => fn ($q) => $q->active()])
            ->upcoming()
            ->orderBy('starts_at')
            ->get();

        // [session id => my entry], so the list can show "You're in" on the right cards.
        $mine = $request->user()
            ? OpenPlayEntry::query()
                ->where('user_id', $request->user()->id)
                ->active()
                ->whereIn('open_play_session_id', $sessions->modelKeys())
                ->get()
                ->keyBy('open_play_session_id')
            : collect();

        return view('open-play.index', compact('sessions', 'mine'));
    }

    // One session: details, the live queue, the game on the court and the player's own status.
    public function show(Request $request, OpenPlaySession $session): View
    {
        $session->load('court');

        $entry = $request->user()
            ? $session->entries()->active()->where('user_id', $request->user()->id)->first()
            : null;

        return view('open-play.show', [
            'session' => $session,
            'queue' => $this->openPlay->queue($session),
            'currentGame' => $this->openPlay->currentGame($session),
            'entry' => $entry,
            'position' => $entry ? $this->openPlay->position($entry) : null,
        ]);
    }

    public function join(Request $request, OpenPlaySession $session): RedirectResponse
    {
        $data = $request->validate(['payment_method' => ['required', Rule::enum(PaymentMethod::class)]]);

        try {
            $this->openPlay->join($session, $request->user(), PaymentMethod::from($data['payment_method']));
        } catch (OpenPlayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('open-play.show', $session)
            ->with('success', __('You are in! Check in with the staff when you arrive.'));
    }

    public function submitPayment(Request $request, OpenPlayEntry $entry): RedirectResponse
    {
        $this->ownEntry($request, $entry);

        $data = $request->validate(['gcash_reference' => ['required', 'string', new GcashReference]]);

        return $this->attempt(
            fn () => $this->openPlay->submitGcashReference($entry, $data['gcash_reference']),
            __('Thanks! The staff will confirm your payment at check-in.'),
        );
    }

    public function enterQueue(Request $request, OpenPlayEntry $entry): RedirectResponse
    {
        $this->ownEntry($request, $entry);

        return $this->attempt(fn () => $this->openPlay->enterQueue($entry), __('You are back in the queue.'));
    }

    public function stepOut(Request $request, OpenPlayEntry $entry): RedirectResponse
    {
        $this->ownEntry($request, $entry);

        return $this->attempt(fn () => $this->openPlay->stepOut($entry), __('You stepped out of the queue.'));
    }

    public function leave(Request $request, OpenPlayEntry $entry): RedirectResponse
    {
        $this->ownEntry($request, $entry);

        return $this->attempt(fn () => $this->openPlay->leave($entry), __('You left this session.'));
    }

    protected function attempt(Closure $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (OpenPlayException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }

    // A player may only change their own sign-up.
    protected function ownEntry(Request $request, OpenPlayEntry $entry): void
    {
        abort_unless((int) $entry->user_id === (int) $request->user()->id, 404);
    }
}