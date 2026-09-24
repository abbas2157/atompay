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

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    {{ $footer ?? '' }}

    @stack('scripts')
</body>
</html>
