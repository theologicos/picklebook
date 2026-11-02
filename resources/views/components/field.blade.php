@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])

<x-form-group :name="$name" :label="$label" :hint="$hint">
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
           {{ $attributes->merge(['class' => 'input'])->class(['ring-red-400' => $errors->has($name)]) }}>
</x-form-group>
