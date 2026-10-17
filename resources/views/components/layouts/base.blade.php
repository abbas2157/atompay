{{--
    The document shell every page shares: head, fonts, assets.
    layouts.app (marketing) and layouts.account (signed-in) wrap it with
    their own chrome. Pages pass SEO data through the `seo` slot.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#FBFAF7">

    {{ $seo }}

    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    {{-- Fonts are self-hosted and bundled by Vite; preloading them lets text paint on the first frame. --}}
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/inter-latin.woff2') }}">
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('resources/fonts/bricolage-grotesque-latin.woff2') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-analytics />
    @stack('head')
</head>
<body class="min-h-full flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-[100] btn btn-primary btn-sm">Skip to content</a>

    {{ $header ?? '' }}

    {{-- tabindex lets the skip link and back-to-top move keyboard focus here. --}}
    <main id="main" class="flex-1 focus:outline-none" tabindex="-1">
        {{ $slot }}
    </main>

    {{ $footer ?? '' }}

    @unless (request()->routeIs('staff.*'))
        <div class="fixed right-4 bottom-4 sm:right-6 sm:bottom-6 z-40 flex flex-col items-center gap-3">
            <button type="button" data-back-to-top class="fab w-11 h-11 bg-white border border-line text-ink hover:border-ink/40 hover:-translate-y-0.5" aria-label="Back to top">
                <svg viewBox="0 0 24 24" class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
            </button>
            <x-whatsapp-button />
        </div>

        {{-- Only when Google Analytics is on: without it the site sets no optional cookies. --}}
        @if (filled(config('services.google_analytics.measurement_id')))
            <div x-data="cookieBanner" x-show="open" x-cloak x-transition.opacity
                 class="fixed z-50 inset-x-3 bottom-3 sm:inset-x-auto sm:left-6 sm:bottom-6 sm:max-w-[400px] card shadow-xl p-4 sm:p-5"
                 role="region" aria-label="Cookie preferences">
                <p class="text-[13.5px] sm:text-[14px] text-muted leading-snug">
                    We use Google Analytics cookies to see which pages help people, only if you allow it.
                    Sign-in cookies are always on. <a href="{{ route('privacy') }}" class="underline text-ink">Privacy policy</a>
                </p>
                <div class="flex gap-2 mt-3 sm:mt-4">
                    <button type="button" class="btn btn-primary btn-sm" @click="choose(true)">Accept</button>
                    <button type="button" class="btn btn-ghost btn-sm" @click="choose(false)">Decline</button>
                </div>
            </div>
        @endif
    @endunless

    @stack('scripts')
</body>
</html>
