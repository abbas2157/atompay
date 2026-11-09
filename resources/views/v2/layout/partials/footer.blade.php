{{-- Navy footer with amber hovers, matching Atomshop.pk v2 (.f-l, .soc). --}}
@php
    $support = config('atompay.api.support');
    $whatsapp = $support['whatsapp'] ?? $support['phone'] ?? null;
    $whatsappUrl = \App\Support\Pakistan::whatsappUrl($whatsapp, 'Hi AtomPay, I have a question.');
    // $shopLink carries the UTM tags, so AtomShop's analytics credits AtomPay
    // for the visit. $shopUrl is the bare address, for structured data only.
    $shop = $shopLink;
@endphp
<footer style="background:var(--ink);color:#B9C2D6;padding:48px 24px 28px;font-size:14px">
    <div class="wrap">
        <div style="display:grid;gap:28px;padding-bottom:28px;border-bottom:1px solid #2A3A60;grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr))">
            <div>
                <span class="brand" style="margin-bottom:14px">
                    <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="44" height="44" style="background:#fff;border-radius:11px;padding:4px;box-sizing:border-box">
                    <span><strong style="color:#fff">ATOMPAY</strong><small style="color:#8F9AB4">PAY IN STEPS</small></span>
                </span>
                <p style="max-width:40ch;color:#8F9AB4;line-height:1.6;margin:0">
                    The smart limit for Atomshop.pk. Three months, no advance, 0% markup, on selected listed products. Lahore, Punjab, Pakistan.
                </p>
            </div>

            <div>
                <h4 style="font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#fff;margin:0 0 12px">AtomPay</h4>
                <a class="f-l" href="{{ route('home') }}#how" style="display:block;padding:5px 0">How it works</a>
                <a class="f-l" href="{{ route('home') }}#plan" style="display:block;padding:5px 0">The plan</a>
                <a class="f-l" href="{{ route('products') }}" style="display:block;padding:5px 0">Eligible products</a>
                <a class="f-l" href="{{ route('home') }}#limit" style="display:block;padding:5px 0">Your limit</a>
                <a class="f-l" href="{{ route('home') }}#ecosystem" style="display:block;padding:5px 0">Ecosystem</a>
            </div>

            <div>
                <h4 style="font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#fff;margin:0 0 12px">Your account</h4>
                @auth
                    <a class="f-l" href="{{ route('account.dashboard') }}" style="display:block;padding:5px 0">Overview</a>
                    <a class="f-l" href="{{ route('account.applications') }}" style="display:block;padding:5px 0">My applications</a>
                    <a class="f-l" href="{{ route('account.verification') }}" style="display:block;padding:5px 0">KYC &amp; verification</a>
                    <a class="f-l" href="{{ route('account.orders') }}" style="display:block;padding:5px 0">My orders</a>
                @else
                    <a class="f-l" href="{{ route('login') }}" style="display:block;padding:5px 0">Sign in</a>
                    <a class="f-l" href="{{ route('register') }}" style="display:block;padding:5px 0">Get a limit</a>
                @endauth
                <a class="f-l" href="{{ $shop }}" target="_blank" rel="noopener" style="display:block;padding:5px 0">Shop on Atomshop.pk</a>
            </div>

            <div>
                <h4 style="font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#fff;margin:0 0 12px">Help</h4>
                <a class="f-l" href="{{ route('faq') }}" style="display:block;padding:5px 0">FAQs</a>
                @if ($whatsappUrl)
                    <a class="f-l" href="{{ $whatsappUrl }}" target="_blank" rel="noopener" style="display:block;padding:5px 0"><span class="wa-dot wa-inline"></span>{{ $whatsapp }}</a>
                @endif
                <a class="f-l" href="{{ route('privacy') }}" style="display:block;padding:5px 0">Privacy policy</a>
                <a class="f-l" href="{{ route('account.delete.info') }}" style="display:block;padding:5px 0">Delete my account</a>
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:18px 24px;justify-content:space-between;align-items:center;padding-top:22px">
            <div>
                <h4 style="font-size:12px;font-weight:800;letter-spacing:1.4px;text-transform:uppercase;color:#fff;margin:0 0 10px">Get the app</h4>
                @include('v2.layout.partials.store-badges', ['dark' => true])
            </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:12px 24px;justify-content:space-between;padding-top:22px;font-size:13px;color:#8F9AB4">
            <span>&copy; {{ date('Y') }} {{ config('app.name') }} &mdash; the installment limit for Atomshop.pk purchases.</span>
            <span>Figures shown as examples are not an offer of credit.</span>
        </div>
    </div>
</footer>
