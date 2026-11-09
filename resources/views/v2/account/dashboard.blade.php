{{--
    Overview (v2). The v1 dashboard carried everything; in v2 the detail lives
    on /my/applications, /my/verification and /my/orders, so this page answers
    only the three questions a customer opens it for - what can I spend, what is
    due next, and what should I do now - and links on.
--}}
@extends('v2.layout.account')

@section('acct-nav', 'overview')
@section('acct-eyebrow', 'My AtomPay')
@section('acct-title', 'Overview')
@section('acct-lead', 'What you can spend, what is due next, and anything waiting on you.')

@section('acct')
    @php
        $banner = app(\App\Services\StatusBanner::class)->for($profile, $credit['latest']);
        $noteTone = ['done' => 'green', 'blocked' => 'rose'][$banner['tone']] ?? '';
        $bannerHref = $banner['action'] === 'apply' ? route('account.application')
            : ($banner['action'] === 'profile' ? route('account.verification') : route('account.applications'));

        $active = $plans->where('is_active', true);
        $late = $plans->where('has_late', true);
    @endphp

    {{-- What happens next, in one line, with the one action that moves it on. --}}
    <div class="note {{ $noteTone }}">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            @if ($banner['tone'] === 'done')
                <path d="M4 12l5 5L20 6"/>
            @elseif ($banner['tone'] === 'blocked')
                <circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
            @else
                <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @endif
        </svg>
        <span class="nbody"><b>{{ $banner['title'] }}</b>{{ $banner['text'] }}</span>
    </div>
    <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:-6px">
        <a class="btn btn-p" href="{{ $bannerHref }}">{{ $banner['cta'] }}</a>
        @if ($credit['limit'] > 0)
            <a class="btn btn-g" href="{{ config('atompay.shop_url') }}" target="_blank" rel="noopener">Shop on Atomshop.pk</a>
        @endif
    </div>

    {{-- The limit. --}}
    <div class="card" style="padding:20px">
        @if ($credit['limit'] > 0)
            <div class="spend">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <span class="lbl">Available to spend</span>
                    <span class="badge" style="background:rgba(255,255,255,.75);color:var(--ink)">{{ $credit['active']?->status->label() }}</span>
                </div>
                <div class="amt">@rs($credit['available'])</div>
                <div class="bar" role="progressbar" aria-valuenow="{{ $credit['used_percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Limit used">
                    <span style="width: {{ $credit['used_percent'] }}%"></span>
                </div>
                <div class="row">
                    <span>@rs($credit['used']) in use &middot; limit @rs($credit['limit'])</span>
                    <span>3 months &middot; 0% markup</span>
                </div>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:12px;margin-top:18px">
                <a class="btn btn-g btn-sm" href="{{ route('account.applications') }}">My applications</a>
                <a class="btn btn-g btn-sm" href="{{ route('account.orders') }}">My orders</a>
                <a class="btn btn-g btn-sm" href="{{ route('account.verification') }}">KYC &amp; verification</a>
            </div>
        @else
            <div class="apay-rule">
                <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="34" height="34">
                <div>
                    <b>No limit yet</b>
                    <p>Apply once with your identity and income details. Staff verify who you are and where you live, then set a limit you can spend across Atomshop.pk &mdash; three months, no advance, 0% markup.</p>
                </div>
            </div>
        @endif
    </div>

    {{-- Three figures. --}}
    <div class="grid g-3">
        <div class="card stat-card">
            <span class="icon-tile t-amber" aria-hidden="true"><svg viewBox="0 0 24 24"><rect x="3" y="4.5" width="18" height="17" rx="2"/><path d="M16 2.5v4M8 2.5v4M3 10h18"/></svg></span>
            <div>
                <div class="stat-n">{{ $nextDue?->installment_date?->format('d M Y') ?? '—' }}</div>
                <div class="stat-l">{{ $nextDue ? 'Next payment · '.\App\Support\Money::format($nextDue->installment_price, 'Rs. ') : 'Nothing due' }}</div>
            </div>
        </div>
        <div class="card stat-card">
            <span class="icon-tile t-blue" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg></span>
            <div><div class="stat-n">{{ $active->count() }}</div><div class="stat-l">{{ $active->count() === 1 ? 'Order being repaid' : 'Orders being repaid' }}</div></div>
        </div>
        <div class="card stat-card">
            <span class="icon-tile {{ $late->count() ? 't-rose' : 't-green' }}" aria-hidden="true">
                <svg viewBox="0 0 24 24">@if ($late->count())<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>@else<path d="M20 6 9 17l-5-5"/>@endif</svg>
            </span>
            <div><div class="stat-n">{{ $late->count() }}</div><div class="stat-l">{{ $late->count() === 1 ? 'Order with a late payment' : 'Orders with a late payment' }}</div></div>
        </div>
    </div>

    {{-- Where the application is. The detail is on its own page. --}}
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="h3">Where you are</h2>
                <p class="body">Six stages, from your CNIC to your first purchase.</p>
            </div>
            <a class="btn btn-g btn-sm" href="{{ route('account.verification') }}">See the detail</a>
        </div>
        <ol class="track">
            @foreach ($stages as $stage)
                @php($class = ['done' => 'done', 'current' => 'now', 'blocked' => 'stop'][$stage['state']] ?? '')
                <li class="{{ $class }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                    {{ $stage['title'] }}
                    <small>{{ $stage['hint'] }}</small>
                </li>
            @endforeach
        </ol>
    </div>

    <p class="body">
        Want to close your account? <a class="link" href="{{ route('account.delete') }}">Delete my account</a>
    </p>
@endsection
