<x-layouts.app :title="__('Create account')">
    <div class="mx-auto max-w-md">
        <h1 class="text-2xl font-bold tracking-tight text-stone-900">{{ __('Create your account') }}</h1>
        <p class="mt-1 text-sm text-stone-600">{{ __('One quick sign-up and you can book any court in under a minute.') }}</p>

        @if (str_contains((string) session('url.intended'), '/book/'))
            <p class="mt-4 rounded-xl bg-ball-300/30 px-4 py-3 text-sm text-brand-900 ring-1 ring-ball-500/40">
                {{ __('Log in or create an account to finish booking. Your chosen slot will be waiting.') }}
            </p>
        @endif

        <form method="POST" action="{{ route('register') }}" class="card mt-6 space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <x-field name="first_name" :label="__('First name')" autocomplete="given-name" required autofocus />
                <x-field name="last_name" :label="__('Last name')" autocomplete="family-name" required />
            </div>

            <x-field name="mobile_number" :label="__('Mobile number')" type="tel" inputmode="tel" autocomplete="tel"
                     placeholder="0917 123 4567"
                     :hint="__('We use this to log you in and to reach you about your booking.')" required />

            <x-password name="password" autocomplete="new-password" :hint="__('At least 8 characters.')" required />

            <button type="submit" class="btn btn-primary w-full">{{ __('Create account') }}</button>
        </form>

        <p class="mt-4 text-center text-sm text-stone-600">
            {{ __('Already have an account?') }}
            <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">{{ __('Log in') }}</a>
        </p>
    </div>
</x-layouts.app>
