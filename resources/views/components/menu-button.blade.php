{{-- Hamburger / close toggle. Expects an `open` boolean in the enclosing Alpine scope. --}}
<button type="button" {{ $attributes->merge(['class' => 'w-10 h-10 rounded-full border border-line grid place-items-center bg-white']) }}
        @click="open = !open" :aria-expanded="open" aria-controls="mobile-menu" aria-label="Menu">
    <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
        <path x-show="!open" d="M4 7h16M4 12h16M4 17h16"/>
        <path x-show="open" x-cloak d="M6 6l12 12M18 6L6 18"/>
    </svg>
</button>
