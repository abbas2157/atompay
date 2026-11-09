{{--
    My applications (v2). Every limit application and its decision.

    A submission never overwrites the limit in force: CreditAssessmentService
    opens a new pending row, or updates the latest one while it is still
    pending. So this history is real data, and the limit at the top is the
    assessment currently in force, which is not always the newest row.
--}}
@extends('v2.layout.account')

@section('acct-nav', 'applications')
@section('acct-eyebrow', 'Purchase limit')
@section('acct-title', 'My applications')
@section('acct-lead', 'Every limit application you have made, the decision on each one, and the income figures it was based on.')

@section('acct')
    @php
        $active = $credit['active'];
        $latest = $credit['latest'];
        $banner = app(\App\Services\StatusBanner::class)->for($profile, $latest);
        $noteTone = ['done' => 'green', 'blocked' => 'rose'][$banner['tone']] ?? '';
    @endphp

    {{-- The limit in force, as the AtomPay app shows it. --}}
    <div class="card" style="padding:20px">
        @if ($credit['limit'] > 0)
            <div class="spend">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                    <span class="lbl">Available to spend</span>
                    <span class="badge" style="background:rgba(255,255,255,.75);color:var(--ink)">{{ $active?->status->label() }}</span>
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

            <dl class="kv" style="margin-top:20px">
                <div><dt>Approved limit</dt><dd>@rs($credit['limit'])<span class="sub">{{ (int) (config('atompay.credit.limit_ratio') * 100) }}% of declared income</span></dd></div>
                <div><dt>Monthly cap</dt><dd>@rs($credit['max_instalment'])<span class="sub">{{ (int) (config('atompay.credit.instalment_ratio') * 100) }}% of income, or disposable</span></dd></div>
                <div><dt>Term</dt><dd>3 months<span class="sub">Fixed for every AtomPay plan</span></dd></div>
                <div><dt>Decided</dt><dd>{{ $active?->decided_at?->format('d M Y') ?? '—' }}<span class="sub">by AtomPay staff</span></dd></div>
            </dl>
        @else
            <div class="card-head" style="margin:0">
                <div>
                    <h2 class="h3">No limit yet</h2>
                    <p class="body">Apply once with your identity and income details and we will set a limit you can spend across Atomshop.pk.</p>
                </div>
                <a class="btn btn-p" href="{{ route('account.application') }}">Start my application</a>
            </div>
        @endif
    </div>

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

    {{-- The history. --}}
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="h3">Application history</h2>
                <p class="body">A new application never replaces the limit in force &mdash; it is reviewed alongside it.</p>
            </div>
            <span class="badge b-grey">{{ trans_choice('{1} 1 application|[2,*] :count applications', $assessments->count(), ['count' => $assessments->count()]) }}</span>
        </div>

        @if ($assessments->isEmpty())
            <p class="body">You have not applied yet. <a class="link" href="{{ route('account.application') }}">Start your application</a> and it will appear here.</p>
        @else
            <div class="table-box">
                <table class="t">
                    <caption class="sr-only">Limit applications and their decisions</caption>
                    <thead>
                        <tr>
                            <th scope="col">Application</th>
                            <th scope="col">Declared income</th>
                            <th scope="col">Decision</th>
                            <th scope="col">Limit granted</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assessments as $assessment)
                            @php
                                $tone = match ($assessment->status) {
                                    \App\Models\Enums\AssessmentStatus::Approved => 'b-green',
                                    \App\Models\Enums\AssessmentStatus::Conditional => 'b-amber',
                                    \App\Models\Enums\AssessmentStatus::Rejected => 'b-rose',
                                    default => 'b-blue',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <b>AP-{{ $assessment->id }}</b>
                                    <span class="sub">Submitted {{ $assessment->created_at?->format('d M Y') }}</span>
                                </td>
                                <td class="money">
                                    @rs($assessment->monthly_income)
                                    <span class="sub">{{ $assessment->employment_status?->label() }}@if ($assessment->employer_name) &middot; {{ $assessment->employer_name }}@endif</span>
                                </td>
                                <td>
                                    {{ $assessment->decided_at?->format('d M Y') ?? 'Awaiting review' }}
                                    @if ($assessment->risk_score !== null)
                                        <span class="sub">Risk {{ $assessment->risk_score }} &middot; {{ $assessment->risk_category?->label() }}</span>
                                    @endif
                                </td>
                                <td class="money">
                                    {{ $assessment->approved_limit ? \App\Support\Money::format($assessment->approved_limit, 'Rs. ') : '—' }}
                                    @if ($assessment->max_instalment)
                                        <span class="sub">Cap @rs($assessment->max_instalment)/mo</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $tone }}">{{ $assessment->status->label() }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- A reviewer's own words, where they wrote any. --}}
            @foreach ($assessments->whereNotNull('notes')->take(1) as $noted)
                <div class="note {{ $noted->status === \App\Models\Enums\AssessmentStatus::Rejected ? 'rose' : 'blue' }}" style="margin-top:16px">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                    <span class="nbody"><b>Note on AP-{{ $noted->id }}</b>{{ $noted->notes }}</span>
                </div>
            @endforeach
        @endif
    </div>

    {{-- What the limit in force was calculated from. --}}
    @if ($active)
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="h3">The income this limit is based on</h2>
                    <p class="body">From application AP-{{ $active->id }}. Change any figure and we will reassess.</p>
                </div>
                <a class="btn btn-g btn-sm" href="{{ route('account.application') }}">Improve my limit</a>
            </div>
            <dl class="kv">
                <div><dt>Employment</dt><dd>{{ $active->employment_status?->label() ?? '—' }}</dd></div>
                <div><dt>Employer</dt><dd>{{ $active->employer_name ?: '—' }}</dd></div>
                <div><dt>Income source</dt><dd>{{ $active->income_source?->label() ?? '—' }}</dd></div>
                <div><dt>Monthly income</dt><dd>@rs($active->monthly_income)</dd></div>
                <div><dt>Existing installments</dt><dd>@rs($active->existing_instalments)</dd></div>
                <div><dt>Household expenses</dt><dd>@rs($active->monthly_expenses)</dd></div>
                <div><dt>Disposable income</dt><dd>@rs($active->disposable_income)<span class="sub">Income &minus; installments &minus; expenses</span></dd></div>
                <div><dt>Risk band</dt><dd>{{ $active->risk_category?->label() ?? '—' }}<span class="sub">Score {{ $active->risk_score }} of 100</span></dd></div>
            </dl>
        </div>
    @endif

    <div class="card soft">
        <h3 class="h3">How the two numbers are worked out</h3>
        <ul class="ticks" style="margin-top:14px">
            <li>Your purchase limit is {{ (int) (config('atompay.credit.limit_ratio') * 100) }}% of the monthly income you declare</li>
            <li>No single month may cost more than {{ (int) (config('atompay.credit.instalment_ratio') * 100) }}% of that income, or your disposable income &mdash; whichever is lower</li>
            <li>AtomPay takes no advance, so your limit has to cover a product's whole price, not part of it</li>
        </ul>
    </div>
@endsection
