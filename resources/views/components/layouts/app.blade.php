@props(['title' => null, 'description' => null, 'full' => false])

@php
    $pageTitle = $title ? $title.' · '.$business->name() : $business->name();
    $pageDescription = $description ?? __('Book a pickleball court online. Pay with GCash and get confirmed on Messenger.');

    // Links shown in the header, left to right.
    $links = [
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <meta name="theme-color" content="#0a251a">
    {{-- Link previews (Messenger, Facebook, Viber) --}}
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $business->name() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $pageDescription }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <header class="bg-brand-950 text-white" x-data="{ open: false }">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-y-2 px-4 py-3">
            <a href="{{ route('home') }}" class="flex items-center gap-2.5 text-lg font-bold tracking-tight">
                <x-logo class="size-8" />
                <span>{{ $business->name() }}</span>
            </a>

            <button type="button" class="rounded-lg p-2 hover:bg-white/10 md:hidden" @click="open = ! open"
                    :aria-expanded="open" aria-label="{{ __('Menu') }}">
                <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
            </button>

            <nav :class="open ? 'flex' : 'hidden md:flex'" class="w-full flex-col gap-1 pt-2 md:w-auto md:flex-row md:items-center md:pt-0">
                @foreach ($links as $link)
                    <a href="{{ $link['href'] }}"
                       @class([
                           'rounded-lg px-3 py-2 text-sm font-medium hover:bg-white/10',
                           'bg-white/10' => $link['active'],
                       ])>{{ $link['label'] }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="flex-1">
        @unless ($full)
            <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:py-8">
                <x-flash />
                {{ $slot }}
            </div>
        @else
            @if (session()->hasAny(['success', 'error', 'info']))
                <div class="mx-auto w-full max-w-6xl px-4 pt-4"><x-flash /></div>
            @endif
            {{ $slot }}
        @endunless
    </main>

    <footer class="bg-brand-950 text-sm text-brand-100">
        <div class="mx-auto flex max-w-6xl flex-col gap-3 px-4 py-8 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="font-semibold text-white">{{ $business->name() }}</p>
                @if ($business->address())
                    <p class="mt-1 text-brand-200">{{ $business->address() }}</p>
                @endif
            </div>
            <div class="space-y-1 sm:text-right">
                @if ($business->contactNumber())
                    <p><a href="tel:{{ \App\Support\PhoneNumber::international($business->contactNumber()) ?? $business->contactNumber() }}" class="hover:text-white">{{ $business->contactNumber() }}</a></p>
                @endif
                @if ($business->messengerUrl())
                    <p><a href="{{ $business->messengerUrl() }}" target="_blank" rel="noopener" class="hover:text-white">{{ __('Message us on Messenger') }}</a></p>
                @endif
            </div>
        </div>
    </footer>
</body>
</html>
