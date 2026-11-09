{{-- The header's links below 1100px. Hidden until site.js opens it. --}}
<div class="nav-drawer" data-drawer hidden>
    <div class="nav-drawer-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:4px 4px 10px">
            <span class="brand">
                <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="36" height="36">
                <span><strong style="font-size:17px">ATOMPAY</strong></span>
            </span>
            <button class="nav-burger is-close" type="button" data-drawer-close aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </div>

        {{-- The same four as the bar above 1100px; keep them in step. --}}
        <span class="d-h">AtomPay</span>
        <a class="d-l" href="{{ route('home') }}#how">How it works</a>
        <a class="d-l" href="{{ route('home') }}#plan">The plan</a>
        <a class="d-l" href="{{ route('products') }}">Eligible products</a>
        <a class="d-l" href="{{ route('faq') }}">Help</a>

        @auth
            <span class="d-h">My AtomPay</span>
            @if (auth()->user()->isCustomer())
                <a class="d-l" href="{{ route('account.dashboard') }}">Overview</a>
                <a class="d-l" href="{{ route('account.applications') }}">My applications</a>
                <a class="d-l" href="{{ route('account.verification') }}">KYC &amp; verification</a>
                <a class="d-l" href="{{ route('account.orders') }}">My orders</a>
            @endif
            @if (auth()->user()->isStaff())
                <a class="d-l" href="{{ route('staff.assessments.index') }}">Review queue</a>
            @endif
        @else
            <span class="d-h">Account</span>
            <a class="d-l" href="{{ route('login') }}">Sign in</a>
            <a class="d-l" href="{{ route('register') }}">Get a limit</a>
        @endauth

        <span class="d-h">Atomshop.pk</span>
        <a class="d-l" href="{{ $shopLink }}" target="_blank" rel="noopener">Shop on Atomshop.pk</a>
    </div>
</div>
