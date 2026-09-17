{{--
    Searchable select. Same props as before, so every existing <x-select>
    call is upgraded in place. Any x-model / x-bind passed in lands on the
    combobox root, where x-modelable exposes the chosen value.

    <x-select name="city_id" label="City" :options="$cities" :value="$id" placeholder="Select city" required x-model="form.city_id" />
--}}
@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Select…', 'required' => false])
@php
    $options  = collect($options)->all();
    $selected = old($name, $value);
    $config   = ['options' => $options, 'value' => $selected, 'placeholder' => $placeholder];
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label text-muted">{{ $label }}</label>
    <div
        class="relative"
        x-data="combobox(@js($config))"
        x-modelable="value"
        {{ $attributes->except(['class']) }}
        @click.outside="close()"
    >
        <input type="hidden" name="{{ $name }}" :value="value">
        <input
            id="{{ $name }}" type="text" autocomplete="off" role="combobox"
            x-ref="input" x-model="query"
            :placeholder="placeholder"
            @required($required)
            @focus="show()" @input="onInput()"
            @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)"
            @keydown.enter.prevent="pickActive()" @keydown.escape.prevent="close()"
            @keydown.tab="close()"
            :aria-expanded="open" aria-autocomplete="list" aria-controls="{{ $name }}-list"
            class="input pr-16"
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        >
        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
            <button type="button" class="w-7 h-7 rounded-full grid place-items-center text-muted hover:text-ink hover:bg-line2" x-show="value !== ''" x-cloak @click="clear()" aria-label="Clear" tabindex="-1">
                <svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
            <button type="button" class="w-7 h-7 rounded-full grid place-items-center text-muted" @click="open ? close() : ($refs.input.focus())" aria-label="Toggle options" tabindex="-1">
                <svg viewBox="0 0 24 24" class="w-4 h-4 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
            </button>
        </div>

        <ul
            id="{{ $name }}-list" role="listbox" x-ref="list"
            x-show="open" x-cloak x-transition.opacity.duration.100ms
            class="absolute z-40 left-0 right-0 mt-1.5 max-h-60 overflow-y-auto rounded-xl border border-line bg-white shadow-[0_12px_32px_rgba(20,21,26,.12)] py-1"
        >
            <template x-for="(option, i) in filtered" :key="option.value">
                <li role="option" :aria-selected="option.value === value"
                    class="px-3.5 py-2.5 text-[14.5px] cursor-pointer flex items-center justify-between gap-3"
                    :class="{ 'bg-paper': i === active, 'font-semibold': option.value === value }"
                    @mouseenter="active = i" @mousedown.prevent="choose(option)">
                    <span x-text="option.label"></span>
                    <span x-show="option.value === value" class="text-ok" aria-hidden="true">&#10003;</span>
                </li>
            </template>
            <li x-show="filtered.length === 0" class="px-3.5 py-2.5 text-[13.5px] text-muted">No matches</li>
        </ul>
    </div>
    @error($name)
        <p id="{{ $name }}-error" class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
