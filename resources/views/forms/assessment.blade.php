{{--
    Income → limit form. Shows a live estimate while typing; the POST is
    what actually records anything. Used by home/partials/assess and
    account/partials/improve.

    @include('forms.assessment', ['estimate' => session estimate or null])
--}}
@php
    $ratios = ['limit' => config('atompay.credit.limit_ratio'), 'instalment' => config('atompay.credit.instalment_ratio')];
    $estimate ??= null;
@endphp
<div
    class="card p-7 sm:p-8 max-w-[640px]"
    x-data="incomeEstimate(@js($ratios), @js(old('monthly_income', $estimate['monthly_income'] ?? null)))"
>
    <form method="POST" action="{{ route('assess') }}" class="flex gap-3.5 flex-wrap items-end">
        @csrf
        <div class="flex-1 min-w-[220px]">
            <label for="monthly_income" class="field-label text-muted">Monthly income (PKR)</label>
            <input
                id="monthly_income" name="monthly_income" type="number" inputmode="numeric"
                min="1000" step="1000" placeholder="e.g. 150000" required
                class="input" x-model.number="income"
                @error('monthly_income') aria-invalid="true" @enderror
            >
        </div>
        <button type="submit" class="btn btn-primary" :disabled="!valid">
            {{ auth()->check() ? 'Continue to application' : 'Calculate my limit' }}
        </button>
    </form>
    @error('monthly_income')
        <p class="text-coral text-[13px] mt-2">{{ $message }}</p>
    @enderror

    <div class="mt-5 p-5 rounded-[14px] bg-paper border border-line" x-show="valid" x-cloak>
        <div class="flex justify-between text-[14.5px] py-2">
            <span>Estimated approved limit ({{ (int) ($ratios['limit'] * 100) }}% of income)</span>
            <b class="font-disp text-[17px]" x-text="fmt(limit)"></b>
        </div>
        <div class="flex justify-between text-[14.5px] py-2">
            <span>Maximum instalment per month ({{ (int) ($ratios['instalment'] * 100) }}% of income)</span>
            <b class="font-disp text-[17px]" x-text="fmt(maxInstalment)"></b>
        </div>
        @guest
            <p class="text-muted text-[13.5px] mt-3">
                Submit to keep this estimate, then <a href="{{ route('login') }}" class="underline">sign in</a> or
                <a href="{{ route('register') }}" class="underline">create your AtomShop account</a> so we can verify it.
            </p>
        @endguest
    </div>
</div>
