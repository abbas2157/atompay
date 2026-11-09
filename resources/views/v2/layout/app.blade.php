{{--
    AtomPay storefront v2 layout, built the way Atomshop.pk's
    website/v2/layout/app.blade.php is. A page:

      @extends('v2.layout.app')
      @section('nav', 'plan')                         highlights a header item
      @section('seo') <x-seo … /> @endsection         title, meta, JSON-LD
      @push('head') <style>…</style> @endpush         page-local CSS (rare)
      @section('content') … @endsection                inside <main>
      @push('scripts') … @endpush                      after site.js

    v2 assets are static files under public/web/v2 - no Vite. The v1 design
    keeps its Vite bundle; the two never load together.
--}}
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#14213D">

    @yield('seo')

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="{{ config('design.assets.font_css') }}">
    @foreach (config('design.assets.css') as $stylesheet)
        <link rel="stylesheet" href="{{ design_asset($stylesheet) }}">
    @endforeach

    <x-analytics />
    @stack('head')
</head>
<body @hasSection('nav') data-nav="@yield('nav')" @endif>
    <a class="skip-link" href="#main">Skip to content</a>
    <div class="scroll-progress" aria-hidden="true"><span></span></div>

    @include('v2.layout.partials.topbar')
    @include('v2.layout.partials.header')
    @include('v2.layout.partials.drawer')

    <main id="main" tabindex="-1">
        @yield('content')
    </main>

    @yield('trust-band')
    @include('v2.layout.partials.footer')

    {{-- Ask Atom, everywhere but the internal staff screens. --}}
    @unless (request()->routeIs('staff.*'))
        @include('v2.layout.partials.ask-atom')
    @endunless

    <button class="to-top" type="button" aria-label="Back to top" hidden>
        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
    </button>

    @php($flash = session('status') ?? session('success') ?? session('error') ?? $errors->first())
    @if ($flash)
        <div data-flash="{{ $flash }}" hidden></div>
    @endif

    {{-- Only when Google Analytics is on: without it the site sets no optional cookies. --}}
    @if (filled(config('services.google_analytics.measurement_id')))
        <div class="card" data-cookie-notice hidden role="region" aria-label="Cookie preferences"
             style="position:fixed;z-index:50;left:16px;right:16px;bottom:16px;max-width:400px;box-shadow:var(--shadow)">
            <p class="body" style="margin:0">
                We use Google Analytics cookies to see which pages help people, only if you allow it.
                Sign-in cookies are always on. <a class="link" href="{{ route('privacy') }}">Privacy policy</a>
            </p>
            <div style="display:flex;gap:8px;margin-top:14px">
                <button class="btn btn-p btn-sm" type="button" data-cookie-choice="accept">Accept</button>
                <button class="btn btn-g btn-sm" type="button" data-cookie-choice="decline">Decline</button>
            </div>
        </div>
    @endif

    <script src="{{ design_asset(config('design.assets.js.0')) }}"></script>
    @stack('scripts')
</body>
</html>
