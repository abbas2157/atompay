{{--
    Google Play and App Store badges, built from the design system the artifact
    uses (.store-badges in atompay.css) rather than the shop's own home-page
    buttons. Both links come from config('atompay.api.store_url'), so a store
    listing is never hardcoded in a view, and a store with no link is left out.

    @include('v2.layout.partials.store-badges', ['dark' => true])
      dark  true on a navy background, false on paper.
--}}
@php
    $stores = array_filter([
        'android' => config('atompay.api.store_url.android'),
        'ios' => config('atompay.api.store_url.ios'),
    ]);
    $dark ??= false;
@endphp
@if ($stores)
    <div class="store-badges @if ($dark) on-dark @endif">
        @if ($stores['android'] ?? null)
            <a class="store" href="{{ $stores['android'] }}" target="_blank" rel="noopener">
                <span class="ic-play" aria-hidden="true"></span>
                <span><small>Get it on</small><b>Google Play</b></span>
            </a>
        @endif

        @if ($stores['ios'] ?? null)
            <a class="store" href="{{ $stores['ios'] }}" target="_blank" rel="noopener">
                <span class="ic-apple" aria-hidden="true"></span>
                <span><small>Download on the</small><b>App Store</b></span>
            </a>
        @endif
    </div>
@endif
