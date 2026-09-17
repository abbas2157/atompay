@php
    $limitPct = (int) (config('atompay.credit.limit_ratio') * 100);
    $instPct  = (int) (config('atompay.credit.instalment_ratio') * 100);
@endphp
<section class="py-[60px] sm:py-[84px] bg-white border-b border-line" id="assess">
    <div class="wrap">
        <x-section-head eyebrow="Get approved" title="Find out your limit before you shop.">
            Your approved AtomPay limit is {{ $limitPct }}% of your monthly income, and any single monthly instalment is capped at {{ $instPct }}% of it. The estimate is instant &mdash; your final limit is confirmed after verification.
        </x-section-head>

        @if ($estimate)
            <x-alert type="warn" class="mb-5 max-w-[640px]">
                We kept your estimate of <b>@pkr($estimate['approved_limit'])</b>.
                <a href="{{ route('login') }}" class="underline">Sign in</a> or
                <a href="{{ route('register') }}" class="underline">create an account</a> to have it verified.
            </x-alert>
        @endif

        @include('forms.assessment', ['estimate' => $estimate])
    </div>
</section>
