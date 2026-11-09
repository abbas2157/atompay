{{--
    The two store links for one app in the ecosystem, as compact pills. Uses
    site.css's own .ic-play and .ic-apple glyphs, so the marks match the shop's.

    An app with no link for a platform says so rather than linking nowhere -
    AtomBrand's Android build is not published yet.

    @include('v2.layout.partials.app-links', ['app' => [...], 'name' => 'Atomshop'])
--}}
@php
    $app ??= [];
    $name ??= 'the app';
@endphp
<div class="eco-apps">
    @if ($app['android'] ?? null)
        <a href="{{ $app['android'] }}" target="_blank" rel="noopener" aria-label="{{ $name }} on Google Play">
            <span class="ic-play" aria-hidden="true"></span>Android
        </a>
    @else
        <span class="soon">Android soon</span>
    @endif

    @if ($app['ios'] ?? null)
        <a href="{{ $app['ios'] }}" target="_blank" rel="noopener" aria-label="{{ $name }} on the App Store">
            <span class="ic-apple" aria-hidden="true"></span>iPhone
        </a>
    @else
        <span class="soon">iPhone soon</span>
    @endif
</div>
