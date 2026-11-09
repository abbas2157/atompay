{{--
    One labelled input in the v2 design (site.css .field). Shows the field's own
    validation message beneath it and marks it aria-invalid, so the error is
    announced where it happened rather than only in a banner at the top.

    <x-v2.field name="login" label="Email or mobile number" autocomplete="username" required />
--}}
@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'value' => null,
    'full' => false,
])
@php
    $id = $attributes->get('id') ?? $name;
    $invalid = $errors->has($name);
    // Never re-fill a password field from old input.
    $old = str_contains($type, 'password') ? null : old($name, $value);
@endphp
<label class="field @if ($full) full @endif" for="{{ $id }}">
    {{ $label }}
    <input
        {{ $attributes->except(['class'])->merge([
            'id' => $id,
            'name' => $name,
            'type' => $type,
            'value' => $old,
        ]) }}
        @if ($invalid) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
    >
    @if ($invalid)
        <span class="hint" id="{{ $id }}-error" style="color:var(--rose);font-weight:600">{{ $errors->first($name) }}</span>
    @elseif ($hint)
        <span class="hint">{{ $hint }}</span>
    @endif
</label>
