{{-- Drop-down panel under the header on small screens. $links: [[href, label], ...]. --}}
@props(['links', 'breakpoint' => 'lg'])
<nav id="mobile-menu" class="{{ $breakpoint }}:hidden border-t border-line2 bg-paper" x-show="open" x-cloak x-transition.opacity.duration.150ms aria-label="Mobile">
    <div class="wrap py-2">
        @foreach ($links as [$href, $label])
            <a href="{{ $href }}" @click="open = false" class="block py-3 text-[15px] no-underline border-b border-line2 last:border-b-0">{{ $label }}</a>
        @endforeach
        {{ $slot }}
    </div>
</nav>
