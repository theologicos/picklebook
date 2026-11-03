<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        // New accounts are always players: "role" is not mass assignable and defaults to client.
        $user = User::create([
            'first_name' => $request->string('first_name')->trim()->toString(),
            'last_name' => $request->string('last_name')->trim()->toString(),
            'mobile_number' => $request->input('mobile_number'),
            'password_hash' => $request->input('password'),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('success', __('Welcome, :name! Your account is ready.', ['name' => $user->first_name]));
    }
}
