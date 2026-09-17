@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label text-muted">{{ $label }}</label>
    <input
        id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
        value="{{ $type === 'password' ? '' : old($name, $value) }}"
        @required($required)
        {{ $attributes->except('class')->merge(['class' => 'input']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @error($name)
        <p id="{{ $name }}-error" class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
