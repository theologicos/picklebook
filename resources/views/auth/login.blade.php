<x-layouts.app :title="__('Log in')">
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-bold tracking-tight text-stone-900">{{ __('Welcome back') }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ __('Log in with your mobile number.') }}</p>

        @if (str_contains((string) session('url.intended'), '/book/'))
            <p class="mt-4 rounded-xl bg-ball-300/30 px-4 py-3 text-sm text-brand-900 ring-1 ring-ball-500/40">
                {{ __('Log in or create an account to finish booking. Your chosen slot will be waiting.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('login') }}" class="card mt-6 space-y-4">
            @csrf

            <x-field name="mobile_number" :label="__('Mobile number')" type="tel" inputmode="tel"
                     autocomplete="tel" placeholder="0917 123 4567" required autofocus />

            <x-password name="password" required />

            <label class="flex items-center gap-2 text-sm text-stone-600">
                <input type="checkbox" name="remember" value="1" class="size-4 rounded border-stone-300 text-brand-700 focus:ring-brand-600">
                {{ __('Keep me logged in') }}
            </label>

            <button type="submit" class="btn btn-primary w-full">{{ __('Log in') }}</button>
        </form>
    </div>
</x-layouts.app>
