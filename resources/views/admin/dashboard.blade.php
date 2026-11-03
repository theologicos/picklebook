<x-layouts.admin :title="__('Dashboard')">
    <p class="-mt-4 mb-6 text-sm text-stone-600">{{ \App\Support\Format::dateLong(today()) }}</p>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="card">
            <p class="text-sm text-stone-500">{{ __('Bookings today') }}</p>
            <p class="mt-1 text-3xl font-bold text-stone-900">{{ $stats['bookings_today'] }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-stone-500">{{ __('Court usage today') }}</p>
            <p class="mt-1 text-3xl font-bold text-stone-900">{{ $stats['utilization'] }}%</p>
            <p class="mt-1 text-xs text-stone-500">{{ trans_choice(':count hour booked|:count hours booked', $stats['hours_today']) }}</p>
        </div>
        <div class="card">
            <p class="text-sm text-stone-500">{{ __('Collected today') }}</p>
            <p class="mt-1 text-3xl font-bold text-stone-900">@peso($stats['revenue_today'])</p>
        </div>
        <div class="card">
            <p class="text-sm text-stone-500">{{ __('Collected this month') }}</p>
            <p class="mt-1 text-3xl font-bold text-stone-900">@peso($stats['revenue_month'])</p>
        </div>
    </div>
</x-layouts.admin>
