{{--
    Everything a crawler reads from <head>. One component so every page
    gets the same, complete set of tags.

    <x-seo title="..." description="..." :noindex="true" :canonical="url" image="..." :schema="[...]" />
--}}
@props([
    'title',
    'description' => '',
    'canonical'   => null,
    'image'       => null,
    'type'        => 'website',
    'noindex'     => false,
    'schema'      => [],
])
@php
    $siteName  = config('app.name');
    $fullTitle = $title === $siteName ? $title : "{$title} — {$siteName}";
    $canonical ??= url()->current();
    $image     ??= asset('images/og-default.png');
@endphp
<title>{{ $fullTitle }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="{{ $noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' }}">
<meta name="geo.region" content="PK">

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $fullTitle }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $image }}">
<meta property="og:locale" content="en_PK">

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $fullTitle }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $image }}">

@foreach ($schema as $node)
<script type="application/ld+json">{!! json_encode($node, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endforeach
