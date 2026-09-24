{{--
    Two jobs: a genuine 503, and the maintenance screen rendered ahead of time by
    `php artisan down --render="errors::503"`. That pre-render happens while the
    app is about to go offline, so this page must not call route() - the route
    table is not guaranteed at that moment. Plain paths only.
--}}
<x-layouts.error code="503" title="Back in a few minutes" eyebrow="Maintenance">
    <p class="lede">
        AtomPay is briefly offline while we deploy an update. Nothing is lost:
        your application, documents and approved limit are exactly as you left them.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="javascript:location.reload()">Check again</a>
        <a class="btn btn-ghost" href="{{ config('atompay.shop_url') }}">Go to AtomShop.pk</a>
    </div>
    <p class="hint">Shopping on AtomShop.pk is unaffected while this site is down.</p>
</x-layouts.error>
