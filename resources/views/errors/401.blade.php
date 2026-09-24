<x-layouts.error code="401" title="Please sign in to continue" eyebrow="Unauthorised">
    <p class="lede">
        This page is part of My AtomPay. Sign in with the same email or phone number
        and password you use on AtomShop.pk &mdash; it's one account for both sites.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('login') }}">Sign in</a>
        <a class="btn btn-ghost" href="{{ route('register') }}">Create an account</a>
    </div>
</x-layouts.error>
