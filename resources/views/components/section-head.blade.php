@props(['eyebrow' => null, 'title', 'dark' => false])
<div {{ $attributes->merge(['class' => 'max-w-[620px] mb-11']) }}>
    @if ($eyebrow)
        <span class="eyebrow {{ $dark ? 'text-white/70' : '' }}">{{ $eyebrow }}</span>
    @endif
    <h2 class="text-[clamp(27px,3.6vw,40px)] mt-3 {{ $dark ? 'text-white' : '' }}">{{ $title }}</h2>
    @if (trim($slot))
        <p class="mt-3.5 text-[17px] {{ $dark ? 'text-white/75' : 'text-muted' }}">{{ $slot }}</p>
    @endif
</div>
