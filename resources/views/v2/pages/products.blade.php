{{--
    Eligible products (v2). Everything admin has marked buyable with AtomPay on
    Atomshop, priced at the AtomPay plan. The purchase happens on Atomshop.pk,
    so every card links out.
--}}
@extends('v2.layout.app')

@section('nav', 'products')

@section('seo')
    <x-seo
        title="Products you can buy with AtomPay"
        description="Every Atomshop.pk product you can buy with an AtomPay limit — three months, no advance, 0% markup. See the monthly figure before you shop."
        :canonical="route('products')"
    />
@endsection

@section('content')
    <div class="page-hero">
        <div class="wrap">
            <p class="crumbs"><a href="{{ route('home') }}">Home</a> / <span>Eligible products</span></p>
            <span class="eyebrow ap">Eligible products</span>
            <h1 class="h1">What you can buy with AtomPay.</h1>
            <p class="lead" style="margin-top:12px">
                {{ number_format($total) }} {{ Str::plural('product', $total) }} on Atomshop.pk can be paid for with an
                AtomPay limit &mdash; three months, no advance, 0% markup. The monthly figure on each card is the one
                you will be quoted at checkout.
            </p>
        </div>
    </div>

    <section class="section">
        <div class="wrap">
            {{-- Filters. A GET form, so a filtered listing is a shareable URL
                 and works with no JavaScript at all. --}}
            <form method="GET" action="{{ route('products') }}" class="card" style="margin-bottom:24px;padding:18px">
                <div class="form" style="align-items:end">
                    <label class="field" for="category">
                        Category
                        <select id="category" name="category" data-search>
                            <option value="">All categories</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}" @selected($filters['category'] === $category->id)>{{ $category->title }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="field" for="brand">
                        Brand
                        <select id="brand" name="brand" data-search>
                            <option value="">All brands</option>
                            @foreach ($brands as $brand)
                                <option value="{{ $brand->id }}" @selected($filters['brand'] === $brand->id)>{{ $brand->title }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="field" style="flex-direction:row;gap:10px;flex-wrap:wrap">
                        <button class="btn btn-p" type="submit">Show products</button>
                        @if (array_filter($filters))
                            <a class="btn btn-g" href="{{ route('products') }}">Clear</a>
                        @endif
                    </div>
                </div>
            </form>

            @if ($products->isEmpty())
                <div class="card" style="text-align:center">
                    <h2 class="h3">Nothing matches that</h2>
                    <p class="body" style="margin-top:8px">
                        @if (array_filter($filters))
                            No eligible product fits those filters. Try clearing them.
                        @else
                            No products are marked AtomPay-eligible yet. Atomshop sets this per product, and per brand
                            for a whole catalogue.
                        @endif
                    </p>
                    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:18px">
                        @if (array_filter($filters))
                            <a class="btn btn-p" href="{{ route('products') }}">Show all eligible products</a>
                        @endif
                        <a class="btn btn-g" href="{{ $shopLink }}" target="_blank" rel="noopener">Browse Atomshop.pk</a>
                    </div>
                </div>
            @else
                <p class="body" style="margin-bottom:16px">
                    Showing {{ $products->firstItem() }}&ndash;{{ $products->lastItem() }} of {{ number_format($products->total()) }}.
                </p>

                <div class="grid cols-4">
                    @foreach ($products as $product)
                        <x-v2.product-card :product="$product" />
                    @endforeach
                </div>

                @if ($products->hasPages())
                    <div style="margin-top:28px">
                        {{ $products->onEachSide(1)->links('v2.pages.partials.pagination') }}
                    </div>
                @endif
            @endif

            <div class="note blue" style="margin-top:28px">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                <span class="nbody">
                    <b>You order on Atomshop.pk, not here.</b>
                    Open a product, choose AtomPay at checkout, and your limit covers it. Your limit must cover the whole
                    price, because AtomPay takes no advance.
                </span>
            </div>
        </div>
    </section>
@endsection
