<x-layouts.error code="403" title="You don't have access to this" eyebrow="Forbidden">
    <p class="lede">
        This page is restricted. If you reached it from My AtomPay, your account may
        not have the role it needs &mdash; staff review pages, for instance, are not
        open to customers.
    </p>
    <div class="actions">
        <a class="btn btn-primary" href="{{ route('account.dashboard') }}">My AtomPay</a>
        <a class="btn btn-ghost" href="{{ route('home') }}">Homepage</a>
    </div>
    <p class="hint">Think this is a mistake? Contact AtomShop support with the address you tried.</p>
</x-layouts.error>
