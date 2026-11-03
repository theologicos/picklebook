@props(['name' => 'password', 'label' => null, 'hint' => null, 'autocomplete' => 'current-password'])

<x-form-group :name="$name" :label="$label ?? __('Password')" :hint="$hint">
    <div x-data="{ show: false }" class="relative">
        <input id="{{ $name }}" name="{{ $name }}" :type="show ? 'text' : 'password'" type="password"
               autocomplete="{{ $autocomplete }}"
               {{ $attributes->merge(['class' => 'input pr-16'])->class(['ring-red-400' => $errors->has($name)]) }}>
        <button type="button" @click="show = ! show"
                class="absolute inset-y-0 right-0 px-3.5 text-xs font-semibold text-stone-500 hover:text-stone-700"
                x-text="show ? @js(__('Hide')) : @js(__('Show'))">{{ __('Show') }}</button>
    </div>
</x-form-group>
