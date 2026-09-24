<x-layouts.error code="404" title="We can't find that page" eyebrow="Not found">
    <p class="lede">
        The link may be out of date, or the address mistyped. Nothing is wrong with
        your account.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('home') }}">Go to the homepage</a>
        <a class="btn btn-ghost" href="{{ route('faq') }}">Read the FAQ</a>
    </div>
    <p class="hint">Looking for a product? Shopping happens on <a href="{{ config('atompay.shop_url') }}">AtomShop.pk</a>.</p>
</x-layouts.error>
