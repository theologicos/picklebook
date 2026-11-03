@props(['title' => null])

@php
    // Sidebar entries. "icon" is the inner markup of a 24x24 outline icon.
    $nav = [
        [
            'label' => __('Dashboard'),
            'href' => route('admin.dashboard'),
            'active' => request()->routeIs('admin.dashboard'),
            'icon' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        ],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ? $title.' · ' : '' }}{{ __('Admin') }} · {{ $business->name() }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="{ menu: false }" class="min-h-screen bg-stone-100">
    {{-- Mobile top bar --}}
    <div class="sticky top-0 z-30 flex items-center justify-between bg-brand-950 px-4 py-3 text-white lg:hidden">
        <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 font-bold">
            <x-logo class="size-7" /> {{ $business->name() }}
        </a>
        <button type="button" class="rounded-lg p-2 hover:bg-white/10" @click="menu = true" aria-label="{{ __('Menu') }}">
            <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" /></svg>
        </button>
    </div>

    {{-- Backdrop for the mobile menu --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="menu = false"></div>

    {{-- Sidebar (always visible on large screens, slides over on phones) --}}
    <aside class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-brand-950 text-white transition-transform lg:translate-x-0"
           :class="{ 'translate-x-0': menu }">
        <div class="hidden items-center gap-2.5 px-5 py-5 text-lg font-bold lg:flex">
            <x-logo class="size-8" />
            <span class="truncate">{{ $business->name() }}</span>
        </div>
        <div class="flex items-center justify-between px-5 py-4 lg:hidden">
            <span class="font-bold">{{ __('Menu') }}</span>
            <button type="button" class="rounded-lg p-1 hover:bg-white/10" @click="menu = false" aria-label="{{ __('Close') }}">&times;</button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3">
            @foreach ($nav as $item)
                <a href="{{ $item['href'] }}"
                   @class([
                       'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium',
                       'bg-white/10 text-white' => $item['active'],
                       'text-brand-100 hover:bg-white/5 hover:text-white' => ! $item['active'],
                   ])>
                    <svg class="size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $item['icon'] !!}</svg>
                    <span class="flex-1">{{ $item['label'] }}</span>
                    @if (! empty($item['badge']))
                        <span class="rounded-full bg-ball-400 px-2 py-0.5 text-xs font-bold text-brand-950">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="space-y-1 border-t border-white/10 px-3 py-4 text-sm">
            <a href="{{ route('home') }}" target="_blank" class="block rounded-xl px-3 py-2 text-brand-100 hover:bg-white/5 hover:text-white">{{ __('View public site') }} &nearr;</a>
            <p class="px-3 pt-2 text-xs text-brand-300">{{ auth()->user()->full_name }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-xl px-3 py-2 text-left text-brand-100 hover:bg-white/5 hover:text-white">{{ __('Log out') }}</button>
            </form>
        </div>
    </aside>

    <div class="lg:pl-64">
        <main class="mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
            <header class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-2xl font-bold tracking-tight text-stone-900">{{ $title }}</h1>
                @isset($actions)
                    <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
                @endisset
            </header>

            <x-flash />

            {{ $slot }}
        </main>
    </div>
</body>
</html>
