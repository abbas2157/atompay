{{-- The atom logo. `id` must be unique per instance because SVG gradient ids are document-global. --}}
@props(['size' => 34, 'id' => 'nav', 'tagline' => true])
<a {{ $attributes->merge(['class' => 'flex items-center gap-2.5 no-underline', 'href' => route('home')]) }} aria-label="{{ config('app.name') }} home">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 44 44" aria-hidden="true">
        <circle cx="22" cy="22" r="20" fill="#050708"/>
        <ellipse cx="22" cy="22" rx="17" ry="7" fill="none" stroke="url(#g{{ $id }}1)" stroke-width="1.6" transform="rotate(28 22 22)"/>
        <ellipse cx="22" cy="22" rx="17" ry="7" fill="none" stroke="url(#g{{ $id }}2)" stroke-width="1.6" transform="rotate(-28 22 22)"/>
        <text x="22" y="27" font-family="Bricolage Grotesque, sans-serif" font-weight="800" font-size="17" fill="#fff" text-anchor="middle">A</text>
        <defs>
            <linearGradient id="g{{ $id }}1" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#FAA53A"/><stop offset="1" stop-color="#62459B"/></linearGradient>
            <linearGradient id="g{{ $id }}2" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#F05465"/><stop offset="1" stop-color="#3D5DAB"/></linearGradient>
        </defs>
    </svg>
    <span>
        <b class="font-disp text-[17px] tracking-tight block leading-none">{{ config('app.name') }}</b>
        @if ($tagline)
            <small class="hidden sm:block font-mono text-[9.5px] tracking-[.1em] text-muted mt-1">Instalment partner of AtomShop.pk</small>
        @endif
    </span>
</a>
