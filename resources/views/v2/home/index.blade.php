{{--
    AtomPay landing page (v2).

    Built from the approved artifact (claude.ai/artifact/5K8rN53VxBz3fY8U2EYXE6),
    section for section and component for component: hero · stats · how · plan ·
    limit · eligible · ecosystem · apply · account · help · trust band. Where the
    artifact and atomshop's own home page disagree, the artifact wins - it is the
    design that was signed off.

    Four things were added after it, on request, in its own components:
      - the four-up product grid, from the real catalogue (CatalogueService)
      - the plan and income estimators, live (site.js)
      - the apps section, after Help
      - Ask Atom triggers

    The plan quoted is AtomPay's real one - eligible listed products only,
    always 3 months, no advance, 0% markup, plus the processing fee as a
    disclaimer. See docs/atompay-plan-rule.md.
--}}
@extends('v2.layout.app')

@section('nav', 'how')

@section('seo')
    @php
        $description = 'AtomPay is the installment payment option at Atomshop.pk checkout. Get an approved spending limit, then pay for eligible products over three months with no advance and 0% markup.';

        $schema = [
            [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => route('home'),
                'logo' => asset('web/v2/img/atompay-logo.png'),
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Lahore', 'addressCountry' => 'PK'],
                'parentOrganization' => ['@type' => 'Organization', 'name' => 'Atomshop', 'url' => $shopUrl],
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'HowTo',
                'name' => 'How to buy on Atomshop.pk with an AtomPay limit',
                'step' => collect($steps)->map(fn ($step, $i) => [
                    '@type' => 'HowToStep', 'position' => $i + 1, 'name' => $step['title'], 'text' => $step['text'],
                ])->values()->all(),
            ],
            [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => collect($faqs)->map(fn ($faq) => [
                    '@type' => 'Question', 'name' => $faq['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
                ])->values()->all(),
            ],
        ];
    @endphp
    <x-seo
        title="Three months, no advance, no markup — AtomPay for Atomshop.pk"
        :description="$description"
        :schema="$schema"
        :canonical="route('home')"
    />
@endsection

@section('content')
    @php
        $plan = $atompayPlan;              // InstalmentQuoteService::atompayPlan()
        $feePercent = $plan['fee_percent'];
        $limitPct = (int) (config('atompay.credit.limit_ratio') * 100);
        $capPct = (int) (config('atompay.credit.instalment_ratio') * 100);
    @endphp

    {{-- ---------------------------------------------------------- hero --}}
    <section class="section" id="top">
        <div class="wrap split">
            <div>
                <span class="eyebrow ap">Smart limit</span>
                <h1 class="h1">Three months. No advance. No markup.</h1>
                <p class="lead">
                    AtomPay is Atomshop.pk's own installment plan. Verify once and get a spending limit, then pay for
                    any AtomPay-eligible product in three equal monthly steps &mdash; the cash price split three ways,
                    with nothing added for financing.
                </p>
                <div class="cta-row" style="margin-top:26px">
                    <a class="btn btn-p btn-lg" href="{{ auth()->check() ? route('account.application') : route('register') }}">Get my limit</a>
                    <a class="btn btn-g btn-lg" href="{{ route('products') }}">See eligible products</a>
                </div>
                <ul class="ticks" style="margin-top:28px">
                    <li>No advance payment, on any eligible product</li>
                    <li>0% markup &mdash; you pay the cash price, in three parts</li>
                    <li>Your Atomshop.pk account, no second sign-up</li>
                </ul>
            </div>

            <div>
                <div class="apay">
                    <div class="apay-top">
                        <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="48" height="48">
                        <span><b>AtomPay plan</b><small>Example: a @rs($plan['price']) product</small></span>
                    </div>

                    <div class="apay-amt">@rs($plan['monthly']) <span>/month &times; {{ $plan['months'] }}</span></div>

                    <ul class="apay-terms">
                        <li>{{ $plan['months'] }} months</li><li>No advance</li><li>0% markup</li><li>Eligible products</li>
                    </ul>

                    <div style="margin-top:16px">
                        <div class="ln"><span>Cash price</span><b>@rs($plan['price'])</b></div>
                        <div class="ln"><span>Advance at checkout</span><b>@rs(0)</b></div>
                        <div class="ln"><span>Financing markup</span><b>@rs(0)</b></div>
                        <div class="ln"><span>Total you repay</span><b>@rs($plan['total'])</b></div>
                    </div>

                    <div class="apay-extra">
                        <div><span><span class="tag">Charged on top</span>Processing fee, {{ $feePercent }}% of price</span><b>@rs($plan['processing_fee'])</b></div>
                        <div><span><span class="tag">Charged on top</span>Delivery</span><b>Charges apply</b></div>
                    </div>
                </div>
                <p class="body muted" style="margin-top:12px;font-size:13.5px">
                    AtomPay needs an approved limit that covers the full price, because there is no advance to reduce it.
                </p>
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- stats --}}
    <section class="section tight alt">
        <div class="wrap grid g-4">
            <div><div class="stat-n ap">{{ $plan['months'] }}</div><div class="stat-l">Months, always &mdash; the term is fixed</div></div>
            <div><div class="stat-n ap">0%</div><div class="stat-l">Markup on the financed amount</div></div>
            <div><div class="stat-n ap">@rs(0)</div><div class="stat-l">Advance payment at checkout</div></div>
            <div><div class="stat-n ap">{{ $limitPct }}%</div><div class="stat-l">Of your monthly income becomes your limit</div></div>
        </div>
    </section>

    {{-- ----------------------------------------------------- how it works --}}
    <section class="section" id="how">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">How it works</span>
                    <h2 class="h2">Get the limit once. Use it whenever you shop.</h2>
                </div>
                <p>The purchase happens on Atomshop.pk. This site is where your limit is applied for, approved and managed.</p>
            </div>

            @php
                $tiles = ['t-rose', 't-blue', 't-amber', 't-green'];
                $icons = [
                    '<path d="M16 20v-2a4 4 0 0 0-8 0v2"/><circle cx="12" cy="8" r="3.5"/>',
                    '<path d="M3 10.5 12 4l9 6.5"/><path d="M5 10v9h14v-9"/><path d="M9.5 19v-5h5v5"/>',
                    '<path d="M4 7.5h16v12H4z"/><path d="M4 11.5h16"/><path d="M9 4v3.5M15 4v3.5"/>',
                    '<path d="M3.5 12a8.5 8.5 0 1 0 2.6-6.1"/><path d="M3.5 4.5V9H8"/><path d="M12 8v4.3l3 1.8"/>',
                ];
            @endphp
            <ol class="grid g-4">
                @foreach ($steps as $step)
                    <li class="card lift">
                        <span class="icon-tile {{ $tiles[$loop->index] ?? 't-blue' }}" aria-hidden="true" style="margin-bottom:16px">
                            <svg viewBox="0 0 24 24">{!! $icons[$loop->index] ?? '' !!}</svg>
                        </span>
                        <h3 class="h3">{{ $step['title'] }}</h3>
                        <p class="body" style="margin-top:8px">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- ---------------------------------------------------------- plan --}}
    <section class="section alt" id="plan">
        {{-- v1 called this #calculator; keep the old anchor working. --}}
        <span class="anchor" id="calculator" aria-hidden="true"></span>
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow ap">Estimate a plan</span>
                    <h2 class="h2">See roughly what a monthly payment looks like.</h2>
                </div>
                <p>
                    A close estimate to plan your budget. Your exact plan is confirmed at Atomshop checkout once you
                    pick a real product.
                </p>
            </div>

            <div class="split" style="gap:32px"
                 data-plan-calc
                 data-months="{{ $plan['months'] }}"
                 data-fee="{{ $feePercent }}">
                <div class="card">
                    <label class="field" for="plan-price">
                        Product price
                        <input id="plan-price" type="number" inputmode="numeric" data-plan-price
                               min="{{ (int) config('atompay.plan.price_min') }}"
                               max="{{ (int) config('atompay.plan.price_max') }}"
                               step="1000" value="{{ $plan['price'] }}">
                        <span class="hint">Cash price as listed on Atomshop.pk. Your limit must cover it in full.</span>
                    </label>

                    <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:10px;margin-top:18px">
                        @foreach ([22000, 45000, 100000] as $quick)
                            <button class="btn btn-g btn-sm" type="button" data-plan-set="{{ $quick }}">@rs($quick)</button>
                        @endforeach
                    </div>

                    <ul class="ticks" style="margin-top:22px">
                        <li>The months add up to the price exactly &mdash; the last month takes the rounding</li>
                        <li>Seller financing is the alternative: 3&ndash;12 months, 20% advance, markup per month</li>
                    </ul>
                </div>

                <div>
                    <div class="table-box">
                        <table class="t tight">
                            <caption class="sr-only">AtomPay repayment schedule</caption>
                            <thead><tr><th scope="col">Due</th><th scope="col">Payment</th><th scope="col" style="text-align:right">Amount</th></tr></thead>
                            <tbody data-plan-schedule>
                                @foreach ($plan['schedule'] as $row)
                                    <tr>
                                        <td>{{ $row['due']->format('d M Y') }}</td>
                                        <td>Payment {{ $row['number'] }} of {{ $plan['months'] }}@if ($row['rounding'])<span class="sub">Takes the rounding</span>@endif</td>
                                        <td class="money" style="text-align:right">@rs($row['amount'])</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="card soft" style="margin-top:14px;padding:18px">
                        <div class="apay-extra" style="margin:0;padding:0;border:0">
                            <div><span>Total repaid over {{ $plan['months'] }} months</span><b data-plan-out="total">@rs($plan['total'])</b></div>
                            <div><span><span class="tag">On top</span>Processing fee, {{ $feePercent }}%</span><b data-plan-out="fee">@rs($plan['processing_fee'])</b></div>
                            <div><span><span class="tag">On top</span>Delivery</span><b>Charges apply</b></div>
                        </div>
                        <p class="body muted" style="font-size:13px;margin-top:10px">
                            The processing fee is shown with every AtomPay plan as a disclaimer. It is not added to the
                            order total.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- --------------------------------------------------------- limit --}}
    <section class="section" id="limit">
        {{-- v1 called this #assess, and AssessmentController still redirects
             there after an estimate; keep the anchor working. --}}
        <span class="anchor" id="assess" aria-hidden="true"></span>
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Get approved</span>
                    <h2 class="h2">Find out your limit before you shop.</h2>
                </div>
                <p>
                    Your limit is {{ $limitPct }}% of your monthly income, and any single month is capped at
                    {{ $capPct }}% of it. The estimate is instant; your final limit is confirmed after verification.
                </p>
            </div>

            @if ($estimate)
                <div class="note" style="margin-bottom:22px">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span class="nbody">
                        <b>We kept your estimate of @rs($estimate['approved_limit'])</b>
                        <a class="link" href="{{ route('login') }}">Sign in</a> or
                        <a class="link" href="{{ route('register') }}">create an account</a> to have it verified.
                    </span>
                </div>
            @endif

            <div class="split" style="gap:32px"
                 data-limit-calc
                 data-limit-ratio="{{ config('atompay.credit.limit_ratio') }}"
                 data-cap-ratio="{{ config('atompay.credit.instalment_ratio') }}"
                 data-fee-percent="{{ $feePercent }}">
                <div class="card" style="padding:20px">
                    <div class="spend">
                        <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <span class="lbl">Available to spend</span>
                            <span class="badge" style="background:rgba(255,255,255,.72);color:var(--ink)" data-limit-badge>Example</span>
                        </div>
                        <div class="amt" data-limit-out="limit">@rs($exampleLimit)</div>
                        <div class="row">
                            <span>Limit @rs($exampleLimit)</span>
                            <span>{{ $plan['months'] }} months &middot; 0% markup</span>
                        </div>
                    </div>

                    <div class="apay-extra" style="border-top:0;margin-top:16px;padding-top:0">
                        <div><span>Declared monthly income</span><b data-limit-out="income">@rs($exampleIncome)</b></div>
                        <div><span>Purchase limit &mdash; {{ $limitPct }}% of income</span><b data-limit-out="limit">@rs($exampleLimit)</b></div>
                        <div><span>Monthly cap &mdash; {{ $capPct }}% of income</span><b data-limit-out="cap">@rs($exampleCap)</b></div>
                    </div>

                    {{-- The same two "on top" lines the plan carries, so the fee
                         and delivery are stated wherever a figure is quoted. --}}
                    <div class="apay-extra">
                        <div>
                            <span><span class="tag">On top</span>Processing fee, {{ $feePercent }}%</span>
                            <b data-limit-out="fee">@rs(\App\Support\Money::share($exampleLimit, $feePercent / 100))</b>
                        </div>
                        <div><span><span class="tag">On top</span>Delivery</span><b>Charges apply</b></div>
                    </div>

                    <p class="body muted" style="font-size:13px;margin-top:12px" data-limit-note>
                        Example figures. The processing fee is shown with every AtomPay plan as a disclaimer and is not
                        added to the order total; the figure above is {{ $feePercent }}% of a purchase using the whole
                        limit. Your limit is decided by AtomPay staff after identity, address and income checks.
                    </p>
                </div>

                <div>
                    <h3 class="h3">Two numbers decide everything</h3>
                    <p class="body" style="margin-top:10px">
                        Your purchase limit is {{ $limitPct }}% of the monthly income you declare. The most any single
                        month can cost you is {{ $capPct }}% of that income, or your disposable income after
                        expenses &mdash; whichever is lower.
                    </p>

                    <div class="grid g-2" style="margin-top:18px">
                        <div class="card soft">
                            <span class="badge b-rose">Limit</span>
                            <p class="h3" style="margin-top:10px">{{ $limitPct }}% of income</p>
                            <p class="body" style="margin-top:6px">@rs($exampleIncome) a month earns a @rs($exampleLimit) limit.</p>
                        </div>
                        <div class="card soft">
                            <span class="badge b-rose">Monthly cap</span>
                            <p class="h3" style="margin-top:10px">{{ $capPct }}% of income</p>
                            <p class="body" style="margin-top:6px">No month may cost more than @rs($exampleCap) on that income.</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('assess') }}" data-busy style="margin-top:20px">
                        @csrf
                        <div class="form">
                            <x-v2.field
                                name="monthly_income"
                                label="Your monthly income (Rs.)"
                                type="number"
                                inputmode="numeric"
                                min="1000"
                                step="1000"
                                placeholder="e.g. {{ $exampleIncome }}"
                                :value="$estimate['monthly_income'] ?? null"
                                data-limit-income
                                :full="true"
                                required
                                hint="Before tax, as it reaches your account. Nothing is recorded until you submit." />

                            <div class="field full cta-row">
                                <button class="btn btn-p" type="submit">
                                    {{ auth()->check() ? 'Continue to my application' : 'Calculate my limit' }}
                                </button>
                                <button class="btn btn-g" type="button" data-ask="How do I get a limit?">Ask Atom</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------- eligible --}}
    <section class="section alt" id="eligible">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Eligible products</span>
                    <h2 class="h2">Look for the badge on the listing.</h2>
                </div>
                <p>
                    AtomPay is offered on selected listed products only &mdash; never on custom orders or offers.
                    Atomshop marks them per product, and some brands mark their whole catalogue.
                </p>
            </div>

            @if ($products->isNotEmpty())
                <div class="grid cols-4">
                    @foreach ($products as $product)
                        <x-v2.product-card :product="$product" />
                    @endforeach
                </div>
                <p class="body muted" style="margin-top:16px;font-size:13.5px">
                    Monthly figures are the cash price divided by {{ $plan['months'] }}; the last month absorbs the rounding.
                </p>
            @else
                <div class="card" style="text-align:center">
                    <p class="body">
                        No products are marked AtomPay-eligible yet. Atomshop sets this per product, and per brand for a
                        whole catalogue.
                    </p>
                </div>
            @endif

            <div class="cta-row" style="margin-top:20px">
                <a class="btn btn-p" href="{{ route('products') }}">
                    See all {{ number_format($productCount) }} eligible {{ Str::plural('product', $productCount) }}
                    <span aria-hidden="true">&rarr;</span>
                </a>
                <a class="btn btn-g" href="{{ auth()->check() ? route('account.applications') : route('register') }}">Check my limit first</a>
            </div>
        </div>
    </section>

    {{-- ------------------------------------------------------ ecosystem --}}
    <section class="section" id="ecosystem">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">The ecosystem</span>
                    <h2 class="h2">One ecosystem, built in Lahore.</h2>
                </div>
                <p>Four products, one marketplace and one account. AtomPay is the financing part &mdash; it never sells you anything itself.</p>
            </div>

            @php
                $appLinks = [
                    'atomshop' => config('atompay.apps.atomshop'),
                    'atompay' => config('atompay.api.store_url'),
                    'atomseller' => config('atompay.apps.atomseller'),
                    'atombrand' => config('atompay.apps.atombrand'),
                ];
            @endphp
            <div class="eco">
                <div class="eco-card lift">
                    <span class="eco-mark"><img src="{{ asset('web/v2/img/atomshop-mark.png') }}" alt="Atomshop.pk" width="52" height="52" loading="lazy" decoding="async"></span>
                    <span class="role">For shoppers</span>
                    <h3>Atomshop.pk</h3>
                    <p>The marketplace. Browse and buy on installments &mdash; the order, the delivery and the warranty all sit here.</p>
                    @include('v2.layout.partials.app-links', ['app' => $appLinks['atomshop'], 'name' => 'Atomshop'])
                    <a class="foot" href="{{ $shopLink }}" target="_blank" rel="noopener">atomshop.pk &rarr;</a>
                </div>

                <div class="eco-card here">
                    <span class="eco-mark"><img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="AtomPay" width="52" height="52" loading="lazy" decoding="async"></span>
                    <span class="role">For financing</span>
                    <h3>AtomPay</h3>
                    <p>A verified spending limit, used at Atomshop.pk checkout on eligible products. Three months, no advance, 0% markup.</p>
                    @include('v2.layout.partials.app-links', ['app' => $appLinks['atompay'], 'name' => 'AtomPay'])
                    <span class="foot">You are here</span>
                </div>

                <div class="eco-card lift">
                    <span class="eco-mark"><img src="{{ asset('web/v2/img/atomseller-mark.png') }}" alt="Atom Seller" width="52" height="52" loading="lazy" decoding="async"></span>
                    <span class="role">For local sellers</span>
                    <h3>Atom Seller</h3>
                    <p>Leads, deals, deliveries and collections, run by a verified seller in your own area of the city.</p>
                    @include('v2.layout.partials.app-links', ['app' => $appLinks['atomseller'], 'name' => 'Atom Seller'])
                    <a class="foot" href="{{ rtrim($shopUrl, '/') }}/smart-seller" target="_blank" rel="noopener">Smart Seller &rarr;</a>
                </div>

                <div class="eco-card lift">
                    <span class="eco-mark"><img src="{{ asset('web/v2/img/atombrand-mark.png') }}" alt="AtomBrand" width="52" height="52" loading="lazy" decoding="async"></span>
                    <span class="role">For brand partners</span>
                    <h3>AtomBrand</h3>
                    <p>A brand's own storefront, catalogue and stock on Atomshop, with each order handed over to a seller.</p>
                    @include('v2.layout.partials.app-links', ['app' => $appLinks['atombrand'], 'name' => 'AtomBrand'])
                    <a class="foot" href="{{ rtrim($shopUrl, '/') }}/brand-partners" target="_blank" rel="noopener">Brand Partners &rarr;</a>
                </div>
            </div>

            <h3 class="h3" style="margin-top:40px">What happens to one order</h3>
            <p class="body" style="margin-top:8px;max-width:62ch">
                Every app feeds the same marketplace. Here is the path of a single AtomPay purchase, and who is holding
                it at each point.
            </p>

            <div class="flow">
                <div class="flow-step">
                    <span class="flow-who"><img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="20" height="20" loading="lazy" decoding="async"> You, with AtomPay</span>
                    <h4>Choose AtomPay at checkout</h4>
                    <p>On an eligible listing, pick AtomPay instead of seller financing. Nothing is paid at checkout.</p>
                </div>
                <div class="flow-step">
                    <span class="flow-who"><img src="{{ asset('web/v2/img/atomshop-mark.png') }}" alt="" width="20" height="20" loading="lazy" decoding="async"> Atomshop</span>
                    <h4>Atomshop works the order</h4>
                    <p>AtomPay orders are handled by Atomshop's own agents, not by sellers, and the price is never recalculated.</p>
                </div>
                <div class="flow-step">
                    <span class="flow-who"><img src="{{ asset('web/v2/img/atomseller-mark.png') }}" alt="" width="20" height="20" loading="lazy" decoding="async"> Atom Seller</span>
                    <h4>A local seller delivers</h4>
                    <p>A seller in your area hands over the unit and records your payments in the Atom Seller app.</p>
                </div>
                <div class="flow-step">
                    <span class="flow-who"><img src="{{ asset('web/v2/img/atombrand-mark.png') }}" alt="" width="20" height="20" loading="lazy" decoding="async"> AtomBrand</span>
                    <h4>Or the brand hands it over</h4>
                    <p>If the product came from a brand partner's catalogue, the brand releases the unit and keeps its stock up to date.</p>
                </div>
            </div>

            <div class="card soft" style="margin-top:16px;display:flex;flex-wrap:wrap;gap:18px;align-items:center;justify-content:space-between">
                <div style="min-width:0">
                    <p style="font-weight:700">One account across all of it</p>
                    <p class="body" style="margin-top:4px">
                        Shoppers sign up once on Atomshop.pk or AtomPay, because the two share one account. Sellers and
                        brand partners receive theirs from Atomshop after approval.
                    </p>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <span class="chip">AtomPay &middot; Android &amp; iPhone</span>
                    <span class="chip">Atom Seller &middot; Android &amp; iPhone</span>
                    <span class="chip">AtomBrand &middot; iPhone</span>
                </div>
            </div>
        </div>
    </section>

    {{-- ---------------------------------------------------------- apply --}}
    <section class="section alt" id="apply">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow">Getting approved</span>
                    <h2 class="h2">Five checks, then a limit that stays.</h2>
                </div>
                <p>You fill in two of these. AtomPay staff do the rest, including one visit to the address you give us.</p>
            </div>

            <div class="grid g-3">
                <div class="card"><span class="badge b-rose">You</span><h3 class="h3" style="margin-top:12px">Your details &amp; CNIC</h3><p class="body" style="margin-top:8px">Name, CNIC, mobile, date of birth and address, with a photo of your CNIC and a selfie.</p></div>
                <div class="card"><span class="badge b-blue">AtomPay staff</span><h3 class="h3" style="margin-top:12px">Address check</h3><p class="body" style="margin-top:8px">A one-time visit to confirm you live there, and a signed verification form.</p></div>
                <div class="card"><span class="badge b-rose">You</span><h3 class="h3" style="margin-top:12px">Income &amp; expenses</h3><p class="body" style="margin-top:8px">Employment, employer, monthly income, installments you already pay, and household expenses.</p></div>
                <div class="card"><span class="badge b-blue">AtomPay</span><h3 class="h3" style="margin-top:12px">Risk review</h3><p class="body" style="margin-top:8px">Your payment history on Atomshop and your obligations set a score; staff confirm it.</p></div>
                <div class="card"><span class="badge b-blue">AtomPay staff</span><h3 class="h3" style="margin-top:12px">Limit decision</h3><p class="body" style="margin-top:8px">Your purchase limit and monthly cap &mdash; then you shop.</p></div>

                <div class="card" style="display:flex;flex-direction:column;gap:12px;border-color:var(--apay-line);background:linear-gradient(160deg,var(--apay-wash),#fff 62%)">
                    <h3 class="h3">What to have ready</h3>
                    <ul class="ticks" style="font-size:14px">
                        <li>Your CNIC, front and back</li>
                        <li>A selfie on the same phone</li>
                        <li>Your monthly income figure</li>
                        <li>An address someone can visit</li>
                    </ul>
                    <a class="btn btn-p" style="margin-top:auto" href="{{ auth()->check() ? route('account.application') : route('register') }}">Start my application</a>
                </div>
            </div>
        </div>
    </section>

    {{-- -------------------------------------------------------- account --}}
    <section class="section" id="account">
        <div class="wrap split">
            <div>
                <span class="eyebrow">Your account</span>
                <h2 class="h2">Every payment, with its date.</h2>
                <p class="body" style="margin-top:12px;max-width:46ch">
                    Sign in with your Atomshop.pk account to see what is left of your limit, every order against it, and
                    each payment with the date it falls due. The AtomPay app shows the same thing and pushes a reminder
                    the day a payment is due.
                </p>
                <div class="cta-row" style="margin-top:22px">
                    <a class="btn btn-p" href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">Sign in</a>
                    <a class="btn btn-g" href="#apps">Get the app</a>
                </div>
                <p class="body muted" style="margin-top:14px;font-size:13.5px">
                    AtomPay also works in your phone browser at atompay.shop.
                </p>
            </div>

            <div class="table-box tight">
                <table class="t tight">
                    <caption class="sr-only">Example payment schedule</caption>
                    <thead><tr><th scope="col">Due</th><th scope="col">Order</th><th scope="col" style="text-align:right">Amount</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @foreach ($plan['schedule'] as $row)
                            <tr>
                                <td>{{ $row['due']->format('d M Y') }}</td>
                                <td>Inverter AC</td>
                                <td style="text-align:right">@rs($row['amount'])</td>
                                <td>
                                    @if ($loop->first)
                                        <span class="badge b-amber">Due next</span>
                                    @else
                                        <span class="badge b-grey">Upcoming</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------------------- help --}}
    <section class="section alt" id="help">
        <div class="wrap">
            <div class="sec-head">
                <div><span class="eyebrow">Help</span><h2 class="h2">Questions customers ask.</h2></div>
                <p>Still stuck? Message us on WhatsApp. Delivery is Lahore only.</p>
            </div>

            @foreach ($faqs as $faq)
                <div @class(['acc', 'open' => $loop->first])>
                    <button type="button">{{ $faq['q'] }}</button>
                    <div class="acc-body">{{ $faq['a'] }}</div>
                </div>
            @endforeach

            <div class="cta-row" style="margin-top:24px">
                <a class="btn btn-p" href="{{ auth()->check() ? route('account.dashboard') : route('register') }}">Get my limit</a>
                <a class="btn btn-g" href="{{ route('faq') }}">All FAQs</a>
                <button class="btn btn-g" type="button" data-ask="">Ask Atom</button>
            </div>
        </div>
    </section>

    {{-- ----------------------------------------------------------- apps --}}
    <section class="section" id="apps">
        <div class="wrap">
            <div class="sec-head">
                <div>
                    <span class="eyebrow ap">AtomPay app</span>
                    <h2 class="h2">Your limit and your next payment, on your phone.</h2>
                </div>
                <p>
                    Same account as this site and Atomshop.pk. AtomPay works in your phone browser too, so the app is a
                    convenience rather than a requirement.
                </p>
            </div>

            <div class="split" style="gap:32px">
                <div>
                    <ul class="ticks">
                        <li>What is left of your limit, on the home screen</li>
                        <li>Every installment with the date it falls due</li>
                        <li>A reminder before each payment, not after</li>
                    </ul>

                    <div style="margin-top:24px">
                        @include('v2.layout.partials.store-badges')
                    </div>

                    <p class="body muted" style="margin-top:16px;font-size:13.5px">
                        On Google Play and the App Store. Atom Seller and AtomBrand have their own apps for sellers and
                        brand partners.
                    </p>
                </div>

                <div class="app-shot" aria-label="The AtomPay app home screen">
                    <div class="app-shot-top">
                        <span class="lbl">Available to spend</span>
                        <b>@rs($exampleLimit)</b>
                        <span>limit @rs($exampleLimit) &middot; {{ $plan['months'] }} months &middot; 0% markup</span>
                    </div>
                    <div class="app-shot-body">
                        <div class="app-shot-row"><span>Next payment</span><b>@rs($plan['monthly'])</b></div>
                        <div class="app-shot-row"><span class="app-shot-chip due">Due soon</span><span>in 4 days</span></div>
                        <div class="app-shot-row"><span>Payment 1 of {{ $plan['months'] }}</span><b>@rs($plan['monthly'])</b></div>
                        <div class="app-shot-row"><span class="app-shot-chip">Paid</span><span>by bank transfer</span></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

{{-- ----------------------------------------------------------- trust band --}}
@section('trust-band')
    <section class="trust-band">
        <div class="wrap">
            <div class="sec-head" style="margin-bottom:16px">
                <div><span class="eyebrow">Explore more</span><h2 class="h3" style="margin-top:8px">Where to go next</h2></div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <a class="chip" href="{{ route('products') }}">AtomPay eligible products</a>
                <a class="chip" href="#calculator">Installment calculator</a>
                <a class="chip" href="{{ $shopLink }}" target="_blank" rel="noopener">Shop on Atomshop.pk</a>
                <a class="chip" href="#apply">Apply for a limit</a>
                <a class="chip" href="#ecosystem">Atomshop ecosystem</a>
                <a class="chip" href="{{ route('faq') }}">FAQs</a>
            </div>
        </div>
    </section>
@endsection
