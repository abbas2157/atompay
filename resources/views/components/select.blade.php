@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Select…', 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label text-muted">{{ $label }}</label>
    <select id="{{ $name }}" name="{{ $name }}" @required($required)
        {{ $attributes->except('class')->merge(['class' => 'input']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
        <option value="">{{ $placeholder }}</option>
        @foreach ($options as $optValue => $optLabel)
            <option value="{{ $optValue }}" @selected((string) old($name, $value) === (string) $optValue)>{{ $optLabel }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $name }}-error" class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
