@php
    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'info' => session('info'),
    ]);
    $styles = [
        'success' => 'bg-green-50 text-green-800 ring-green-600/20',
        'error' => 'bg-red-50 text-red-800 ring-red-600/20',
        'info' => 'bg-blue-50 text-blue-800 ring-blue-600/20',
    ];
@endphp

@foreach ($messages as $type => $message)
    <div x-data="{ show: true }" x-show="show" role="{{ $type === 'error' ? 'alert' : 'status' }}"
         class="mb-4 flex items-start justify-between gap-3 rounded-xl px-4 py-3 text-sm ring-1 ring-inset {{ $styles[$type] }}">
        <p>{{ $message }}</p>
        <button type="button" class="-mr-1 shrink-0 rounded p-0.5 opacity-60 hover:opacity-100" @click="show = false" aria-label="{{ __('Dismiss') }}">&times;</button>
    </div>
@endforeach
