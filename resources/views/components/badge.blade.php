@props(['color' => 'gray'])

<span {{ $attributes->class(['badge', 'badge-'.$color]) }}>{{ $slot }}</span>
