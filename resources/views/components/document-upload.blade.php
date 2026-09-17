{{--
    Document tile for the wizard: dashed drop area, live preview of the chosen
    image, and a link to what is already on file. Relies on the surrounding
    applicationWizard scope (files, pick()).
--}}
@props(['name', 'label', 'hint' => null, 'current' => null, 'required' => false, 'accept' => 'image/jpeg,image/png,image/webp'])
<div {{ $attributes->only('class') }}>
    <label for="{{ $name }}" class="group block cursor-pointer rounded-2xl border-2 border-dashed border-line bg-paper hover:border-violet/60 transition overflow-hidden
                                  @error($name) border-coral @enderror">
        <div class="aspect-[4/3] grid place-items-center relative">
            {{-- preview of the newly picked file --}}
            <template x-if="files['{{ $name }}']?.url">
                <img :src="files['{{ $name }}'].url" alt="" class="absolute inset-0 w-full h-full object-cover">
            </template>
            {{-- placeholder / currently-on-file state --}}
            <div class="text-center px-4" x-show="!files['{{ $name }}']?.url">
                @if ($current)
                    <img src="{{ $current }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-90">
                    <span class="relative inline-block font-mono text-[10.5px] tracking-[.08em] uppercase bg-nucleus/80 text-white rounded-full px-3 py-1">On file &middot; click to replace</span>
                @else
                    <svg viewBox="0 0 24 24" class="w-8 h-8 mx-auto mb-2 text-muted group-hover:text-violet transition" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/></svg>
                    <span class="block text-[13px] font-semibold">Tap to upload</span>
                    <span class="block text-[11.5px] text-muted mt-0.5">JPG, PNG or WebP</span>
                @endif
            </div>
        </div>
        <div class="px-3.5 py-3 bg-white border-t border-line">
            <b class="block text-[13.5px]">{{ $label }} @if ($required && ! $current)<span class="text-coral">*</span>@endif</b>
            <span class="block text-[12px] text-muted mt-0.5 truncate" x-text="files['{{ $name }}']?.name ?? @js($hint ?? ($current ? 'Already uploaded' : 'Not uploaded yet'))">{{ $hint ?? ($current ? 'Already uploaded' : 'Not uploaded yet') }}</span>
        </div>
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $accept }}" class="sr-only" @required($required) @change="pick($event)">
    @error($name)
        <p class="text-coral text-[13px] mt-1.5">{{ $message }}</p>
    @enderror
</div>
