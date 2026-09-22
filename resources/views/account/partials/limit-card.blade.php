@php $hasLimit = $credit['limit'] > 0; @endphp
<div class="card-dark px-8 py-[34px]">
    <div class="glow w-[420px] h-[420px] -right-[140px] -top-[160px]" aria-hidden="true"></div>
    <div class="relative z-[2]">
        <span class="eyebrow text-white/70">{{ $credit['active'] ? 'Approved purchase limit' : 'Purchase limit' }}</span>
        <div class="font-disp font-extrabold text-[clamp(34px,4.5vw,48px)] tracking-[-.03em] mt-2.5 spectrum-text">
            {{ $hasLimit ? \App\Support\Money::format($credit['limit']) : 'Not set yet' }}
        </div>
        <p class="text-white/75 text-[14px] mt-2">
            @if ($hasLimit)
                @pkr($credit['used']) in use &middot; @pkr($credit['available']) available
            @else
                Complete your application to get a limit.
            @endif
        </p>
        <div class="mt-6">
            <div class="h-2 rounded-lg bg-white/[.14] overflow-hidden" role="progressbar" aria-valuenow="{{ $credit['used_percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Limit used">
                <div class="h-full rounded-lg spectrum" style="width: {{ $credit['used_percent'] }}%"></div>
            </div>
            <div class="flex justify-between font-mono text-[10.5px] tracking-[.08em] uppercase text-white/70 mt-2"><span>Used</span><span>Available</span></div>
        </div>
        <p class="text-[11.5px] text-white/65 mt-[18px] leading-normal">
            Based on {{ (int) (config('atompay.credit.limit_ratio') * 100) }}% of your assessed monthly income. Maximum instalment amount per month is capped at {{ (int) (config('atompay.credit.instalment_ratio') * 100) }}% of income.
        </p>
    </div>
</div>
