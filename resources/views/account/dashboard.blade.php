<x-layouts.account title="My AtomPay">
    <div class="flex justify-between items-end flex-wrap gap-4 mb-7">
        <div>
            <span class="eyebrow">My AtomPay</span>
            <h1 class="mt-2 text-[clamp(26px,3.5vw,34px)]">Your approved limit &amp; payments</h1>
            <p class="text-muted mt-2 text-[15px]">Everything you&rsquo;re approved to spend, and everything you owe, in one place.</p>
        </div>
        <a href="{{ route('account.application') }}" class="btn btn-ghost btn-sm">{{ $profile ? 'Update application' : 'Start application' }}</a>
    </div>

    @include('account.partials.verify-banner', ['profile' => $profile, 'latest' => $credit['latest']])

    <section class="mb-7" aria-label="Application progress">
        <x-process-steps :stages="$stages" />
    </section>

    <section class="grid lg:grid-cols-[1.15fr_.85fr] gap-5">
        @include('account.partials.limit-card', ['credit' => $credit])

        <div class="card p-6">
            <h4 class="text-[14px] mb-3.5">Account summary</h4>
            <x-side-row label="KYC status">{{ $profile?->verification_status->label() ?? 'Not submitted' }}</x-side-row>
            <x-side-row label="Limit decision">{{ $credit['latest']?->status->label() ?? 'Not started' }}</x-side-row>
            <x-side-row label="Approved tenure">{{ $credit['tenure'] ? $credit['tenure'].' months' : '—' }}</x-side-row>
            <x-side-row label="Max instalment / month">{{ $credit['max_instalment'] ? \App\Support\Money::format($credit['max_instalment']) : '—' }}</x-side-row>
            <x-side-row label="Active plans">{{ $plans->count() }}</x-side-row>
            <x-side-row label="Next payment due">{{ $nextDue?->installment_date?->format('d M Y') ?? '—' }}</x-side-row>
            <x-side-row label="Next payment amount">{{ $nextDue ? \App\Support\Money::format($nextDue->installment_price) : '—' }}</x-side-row>
        </div>
    </section>

    <section class="mt-10">
        <div class="mb-4">
            <span class="eyebrow">Payment schedule</span>
            <h2 class="mt-2 text-[22px]">All instalments across your orders</h2>
        </div>

        @forelse ($plans as $plan)
            @include('account.partials.plan-card', $plan)
        @empty
            <div class="card p-7 text-center">
                <p class="text-muted text-[15px]">No instalment plans yet. Choose AtomPay at checkout on AtomShop to start one.</p>
                <a class="btn btn-primary mt-4" href="{{ $shopUrl }}" target="_blank" rel="noopener">Browse AtomShop.pk <span aria-hidden="true">&rarr;</span></a>
            </div>
        @endforelse
    </section>
</x-layouts.account>
