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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,600;12..96,700;12..96,800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
