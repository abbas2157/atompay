{{-- Upload with a "currently on file" link when a document already exists. --}}
@props(['name', 'label', 'current' => null, 'required' => false, 'accept' => 'image/jpeg,image/png,image/webp'])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="field-label text-muted">{{ $label }}</label>
    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $accept }}" @required($required)
        class="input file:mr-3 file:rounded-full file:border-0 file:bg-nucleus file:text-white file:font-mono file:text-[11px] file:px-3 file:py-1.5"
        @error($name) aria-invalid="true" @enderror>
    @if ($current)
        <p class="text-[12.5px] text-muted mt-1.5">On file &mdash; <a href="{{ $current }}" target="_blank" rel="noopener" class="underline">view</a>. Upload again only to replace it.</p>
    @endif
    @error($name)
        <p class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
