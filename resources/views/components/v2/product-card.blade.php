{{--
    One AtomPay-eligible product, as a v2 .p-card: white, 22px radius, square
    image, brand over title, the figure that matters, then the cash price.

    The monthly figure is the plan hung on the product by CatalogueService, so
    no view does arithmetic and a card can never quote a figure that differs
    from checkout. The link goes to Atomshop.pk, because the order is placed
    there, not here.

    <x-v2.product-card :product="$product" />
--}}
@props(['product'])
@php
    $plan = $product->atompay_plan;
    $inStock = $product->isInStock();
@endphp
<article class="p-card">
    <a href="{{ $product->shop_url }}" target="_blank" rel="noopener" tabindex="-1" aria-hidden="true" style="display:block">
        <div class="p-img">
            <span class="badge b-rose p-flag">AtomPay eligible</span>
            @unless ($inStock)
                <span class="badge b-grey" style="position:absolute;right:12px;top:12px">Out of stock</span>
            @endunless

            {{-- The placeholder is rendered for a product with no picture, and
                 hidden for one that has it - site.js reveals it if the <img>
                 then fails to load (data-fallback).

                 It used to be rendered visible underneath every picture, on the
                 assumption that a loaded one covered it. It does not: the image
                 box is 72% of the tile with object-fit:contain, so the 76px
                 glyph showed around its edges on every card. --}}
            <svg class="ph-glyph" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.1"
                 aria-hidden="true" @if ($product->picture_url) hidden @endif>
                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                <path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
            </svg>

            @if ($product->picture_url)
                <img src="{{ $product->picture_url }}" alt="" loading="lazy" decoding="async" data-fallback>
            @endif
        </div>
    </a>

    <div class="p-body">
        @if ($product->brand?->title)
            <p class="p-brand">{{ $product->brand->title }}</p>
        @endif

        <p class="p-title">
            <a href="{{ $product->shop_url }}" target="_blank" rel="noopener">{{ $product->title }}</a>
        </p>

        <p class="p-monthly">@rs($plan['monthly'])</p>
        <p class="p-term">per month &times; {{ $plan['months'] }} &middot; no advance</p>
        <p class="p-cash">Cash price @rs($plan['price'])</p>
    </div>
</article>
