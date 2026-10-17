{{--
    Google Analytics 4 (gtag.js). Off unless GOOGLE_ANALYTICS_ID is set.

    - Never on the staff area: its URLs carry customer assessment ids and it
      is internal traffic that would skew the numbers.
    - Both tags carry the per-request CSP nonce; SecurityHeaders adds Google's
      hosts to the policy only when an id is configured.
    - Google signals / ad personalisation are off: this is a finance site
      holding CNICs, and nothing here needs cross-device ad profiling.
    - Consent mode: analytics cookies stay off until the cookie banner is
      accepted; until then Google receives cookieless pings only.
    - Outside production every hit is flagged debug_mode, so local testing
      shows up in GA's DebugView and can be told apart from real visitors.
--}}
@php
    $measurementId = config('services.google_analytics.measurement_id');
    $nonce = \Illuminate\Support\Facades\Vite::cspNonce();
    $options = [
        'allow_google_signals' => false,
        'allow_ad_personalization_signals' => false,
        ...(app()->isProduction() ? [] : ['debug_mode' => true]),
    ];
@endphp
@if (filled($measurementId) && ! request()->routeIs('staff.*'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($measurementId) }}" @if ($nonce) nonce="{{ $nonce }}" @endif></script>
    <script @if ($nonce) nonce="{{ $nonce }}" @endif>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        {{-- No analytics cookies until the visitor accepts the banner (layouts.base). --}}
        gtag('consent', 'default', {
            analytics_storage: (function () { try { return localStorage.getItem('atompay.analytics-consent'); } catch (e) {} })() === 'granted' ? 'granted' : 'denied',
            ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied'
        });
        gtag('js', new Date());
        gtag('config', @json($measurementId), @json($options));
    </script>
@endif
