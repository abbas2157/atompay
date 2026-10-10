@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
@php $isPassword = $type === 'password'; @endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label text-muted">{{ $label }}</label>
    {{-- Password fields get a show/hide toggle; without JS they stay a plain password field. --}}
    <div @if ($isPassword) class="relative" x-data="{ show: false }" @endif>
        <input
            id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
            value="{{ $isPassword ? '' : old($name, $value) }}"
            @if ($isPassword) :type="show ? 'text' : 'password'" @endif
            @required($required)
            {{ $attributes->except('class')->merge(['class' => $isPassword ? 'input pr-12' : 'input']) }}
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >
        @if ($isPassword)
            <button type="button" x-cloak @click="show = !show; $nextTick(() => $el.previousElementSibling.focus())"
                    class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full grid place-items-center text-muted hover:text-ink hover:bg-line2"
                    :aria-label="show ? 'Hide password' : 'Show password'" :aria-pressed="show.toString()" aria-controls="{{ $name }}">
                <svg x-show="!show" viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                <svg x-show="show" viewBox="0 0 24 24" class="w-[18px] h-[18px]" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 3l18 18"/><path d="M10.6 5.1A9.8 9.8 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-3.2 4.2M6.6 6.6C3.9 8.3 2 12 2 12s3.5 7 10 7a9.6 9.6 0 0 0 5.4-1.6"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/></svg>
            </button>
        @endif
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
