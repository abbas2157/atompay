{{--
    AtomPay KYC & Purchase Limit Assessment - the customer's part, as a
    4-step wizard. One <form>; steps are shown/hidden client-side and the
    whole thing posts once, so KycApplicationRequest stays the single
    source of validation.
--}}
@php
    $ratios = ['limit' => config('atompay.credit.limit_ratio'), 'instalment' => config('atompay.credit.instalment_ratio')];
    $doc    = fn (string $column, string $slug) => $profile->exists && $profile->{$column} ? route('documents.show', [$profile, $slug]) : null;

    $steps = [
        ['title' => 'Personal details',   'hint' => 'As printed on your CNIC'],
        ['title' => 'Identity documents', 'hint' => 'CNIC photos and a selfie'],
        ['title' => 'Income profile',     'hint' => 'Work, income and outgoings'],
        ['title' => 'Review & submit',    'hint' => 'Check everything once'],
    ];

    // Which step owns each field - used to reopen the right step after a server-side error.
    $fieldStep = [
        'full_name' => 0, 'cnic' => 0, 'mobile' => 0, 'date_of_birth' => 0, 'city_id' => 0, 'residential_address' => 0,
        'cnic_front' => 1, 'cnic_back' => 1, 'selfie' => 1,
        'employment_status' => 2, 'employer_name' => 2, 'income_source' => 2, 'monthly_income' => 2, 'existing_instalments' => 2, 'monthly_expenses' => 2,
    ];
    $errorStep = collect($errors->keys())->map(fn ($k) => $fieldStep[$k] ?? null)->filter(fn ($s) => $s !== null)->min();

    // Seed the wizard's state with old input, then the saved profile / last assessment.
    $initial = [
        'full_name'            => old('full_name', $profile->full_name),
        // Shown the way people write them; the request normalises them back.
        'cnic'                 => \App\Support\Pakistan::formatCnic(old('cnic', $profile->cnic)),
        'mobile'               => \App\Support\Pakistan::formatMobile(old('mobile', $profile->mobile)),
        'date_of_birth'        => old('date_of_birth', $profile->date_of_birth?->format('Y-m-d')),
        'city_id'              => old('city_id', $profile->city_id),
        'residential_address'  => old('residential_address', $profile->residential_address),
        'employment_status'    => old('employment_status', $latest?->employment_status?->value),
        'employer_name'        => old('employer_name', $latest?->employer_name),
        'income_source'        => old('income_source', $latest?->income_source?->value),
        'monthly_income'       => old('monthly_income', $prefill['monthly_income'] ?? $latest?->monthly_income),
        'existing_instalments' => old('existing_instalments', $latest?->existing_instalments ?? 0),
        'monthly_expenses'     => old('monthly_expenses', $latest?->monthly_expenses ?? 0),
    ];
    $labels = [
        'employment_status' => $employment,
        'income_source'     => $sources,
        'city_id'           => $cities->pluck('title', 'id')->all(),
    ];
    $needDocs = ! $profile->hasDocuments();
@endphp
<x-layouts.account title="AtomPay application" :wide="true">
    <div
        class="grid lg:grid-cols-[300px_1fr] gap-8 lg:gap-12 items-start"
        x-data="applicationWizard(@js(['steps' => $steps, 'initial' => $initial, 'ratios' => $ratios, 'errorStep' => $errorStep, 'labels' => $labels]))"
    >
        {{-- ============================================================ sidebar --}}
        <aside class="lg:sticky lg:top-24">
            <span class="eyebrow">KYC &amp; purchase limit</span>
            <h1 class="mt-2 text-[clamp(24px,3vw,30px)]">{{ $profile->exists ? 'Update your application' : 'Apply for your AtomPay limit' }}</h1>
            <p class="text-muted text-[14px] mt-2">About five minutes. You can go back to any step before you submit.</p>

            {{-- progress bar (mobile) --}}
            <div class="lg:hidden mt-5">
                <div class="flex justify-between font-mono text-[10.5px] tracking-[.1em] uppercase text-muted mb-2">
                    <span x-text="'Step ' + (current + 1) + ' of ' + steps.length"></span>
                    <span x-text="steps[current].title"></span>
                </div>
                <div class="h-1.5 rounded bg-line overflow-hidden"><div class="h-full spectrum transition-all duration-300" :style="'width:' + Math.max(progress, 8) + '%'"></div></div>
            </div>

            {{-- vertical stepper (desktop) --}}
            <ol class="hidden lg:block mt-7 relative list-none p-0 m-0">
                <span class="absolute left-[15px] top-4 bottom-4 w-px bg-line" aria-hidden="true"></span>
                <template x-for="(step, i) in steps" :key="i">
                    <li class="relative pl-11 py-2.5">
                        <button type="button" @click="go(i)" :disabled="i > furthest" class="text-left w-full disabled:cursor-default"
                                :aria-current="i === current ? 'step' : null">
                            <span class="absolute left-0 top-2.5 w-8 h-8 rounded-full grid place-items-center font-disp text-[12px] font-bold border-2 transition"
                                  :class="{
                                      'bg-nucleus border-nucleus text-white': i === current,
                                      'bg-ok border-ok text-white': i < current,
                                      'bg-white border-line text-muted': i > current,
                                  }">
                                <span x-show="i < current" aria-hidden="true">&#10003;</span>
                                <span x-show="i >= current" x-text="i + 1"></span>
                            </span>
                            <b class="block text-[14px]" :class="i === current ? 'text-ink' : (i < current ? 'text-ink' : 'text-muted')" x-text="step.title"></b>
                            <span class="block text-[12px] text-muted mt-0.5" x-text="step.hint"></span>
                        </button>
                    </li>
                </template>
            </ol>

            {{-- what happens after --}}
            <div class="hidden lg:block mt-7 rounded-2xl bg-white border border-line p-5">
                <span class="eyebrow">After you submit</span>
                <ol class="mt-3 space-y-2.5 text-[13px] list-none p-0 m-0">
                    <li class="flex gap-2.5"><span class="font-mono text-[10.5px] text-muted mt-0.5">02</span><span><b>Address verification</b><span class="block text-muted text-[12px]">One visit to your address, signature on the form.</span></span></li>
                    <li class="flex gap-2.5"><span class="font-mono text-[10.5px] text-muted mt-0.5">04</span><span><b>Risk assessment</b><span class="block text-muted text-[12px]">Payment record and profile reviewed by AtomPay.</span></span></li>
                    <li class="flex gap-2.5"><span class="font-mono text-[10.5px] text-muted mt-0.5">05</span><span><b>Limit decision</b><span class="block text-muted text-[12px]">Approved limit, instalment cap and tenure.</span></span></li>
                </ol>
            </div>
        </aside>

        {{-- =============================================================== form --}}
        <form method="POST" action="{{ route('account.application.store') }}" enctype="multipart/form-data" novalidate
              @submit="if (!consent) { $event.preventDefault(); alert('Please confirm the declaration first.'); }">
            @csrf

            @if ($errors->any())
                <x-alert type="error" class="mb-5">Some details need attention &mdash; we&rsquo;ve opened the step that has them.</x-alert>
            @endif

            {{-- ---------------------------------------------- 1. personal --}}
            <section data-step="0" x-show="current === 0" x-cloak class="card p-6 sm:p-8">
                @include('account.application.step-head', ['n' => 1, 'title' => 'Personal details', 'intro' => 'Enter these exactly as they appear on your CNIC - the visit team checks them against the card.'])
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-input name="full_name" label="Full name" autocomplete="name" required class="sm:col-span-2" x-model="form.full_name" />
                    {{-- x-pk-format inserts the dashes and the 03 prefix while typing; the
                         patterns still hold for anyone without JavaScript. --}}
                    <x-input name="cnic" label="CNIC number" inputmode="numeric" autocomplete="off"
                             placeholder="42101-1234567-1" maxlength="15" pattern="[0-9]{5}-?[0-9]{7}-?[0-9]"
                             required x-model="form.cnic" x-pk-format="cnic" />
                    <x-input name="mobile" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel"
                             placeholder="0300 1234567" maxlength="12" pattern="(\+?92|0)?[\s-]?3[0-9]{2}[\s-]?[0-9]{7}"
                             required x-model="form.mobile" x-pk-format="mobile" />
                    <x-input name="date_of_birth" label="Date of birth" type="date" max="{{ now()->subYears(config('atompay.kyc.min_age'))->format('Y-m-d') }}" required x-model="form.date_of_birth" />
                    <x-select name="city_id" label="City" :options="$cities->pluck('title', 'id')" placeholder="Select city" x-model="form.city_id" />
                    <div class="sm:col-span-2">
                        <label for="residential_address" class="field-label text-muted">Residential address</label>
                        <textarea id="residential_address" name="residential_address" rows="3" required class="input" x-model="form.residential_address" placeholder="House / flat, street, area" @error('residential_address') aria-invalid="true" @enderror></textarea>
                        @error('residential_address')<p class="text-coral text-[13px] mt-1.5">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            {{-- --------------------------------------------- 2. documents --}}
            <section data-step="1" x-show="current === 1" x-cloak class="card p-6 sm:p-8">
                @include('account.application.step-head', ['n' => 2, 'title' => 'Identity documents', 'intro' => 'Clear, well-lit photos with all four corners visible. Max '.(int) (config('atompay.kyc.max_upload_kb') / 1024).' MB each. Stored privately and seen only by the verification team.'])
                <div class="grid sm:grid-cols-3 gap-4">
                    <x-document-upload name="cnic_front" label="CNIC front" :current="$doc('cnic_front_path', 'cnic-front')" :required="$needDocs" />
                    <x-document-upload name="cnic_back"  label="CNIC back"  :current="$doc('cnic_back_path', 'cnic-back')"   :required="$needDocs" />
                    <x-document-upload name="selfie"     label="Selfie with CNIC" hint="Hold your CNIC next to your face" :current="$doc('selfie_path', 'selfie')" :required="$needDocs" />
                </div>
                <div class="mt-5 rounded-xl bg-paper border border-line px-4 py-3 text-[13px] text-muted flex gap-3">
                    <svg viewBox="0 0 24 24" class="w-5 h-5 shrink-0 text-violet" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l7 4v6c0 4-3 6-7 8-4-2-7-4-7-8V7z"/><path d="M9 12l2 2 4-4"/></svg>
                    Your documents are used only to verify your identity for AtomPay and are never shown on AtomShop.
                </div>
            </section>

            {{-- ------------------------------------------------ 3. income --}}
            <section data-step="2" x-show="current === 2" x-cloak class="card p-6 sm:p-8">
                @include('account.application.step-head', ['n' => 3, 'title' => 'Income & financial profile', 'intro' => 'Honest figures get you the right limit - they are checked during verification.'])
                <div class="grid sm:grid-cols-2 gap-4">
                    <x-select name="employment_status" label="Employment / business status" :options="$employment" required x-model="form.employment_status" />
                    <x-select name="income_source" label="Income source" :options="$sources" required x-model="form.income_source" />
                    <div class="sm:col-span-2" x-show="hasEmployer" x-cloak>
                        <x-input name="employer_name" label="Employer / business name" x-model="form.employer_name" x-bind:required="hasEmployer" />
                    </div>
                    <x-input name="monthly_income" label="Monthly income (PKR)" type="number" inputmode="numeric" min="1000" step="1000" placeholder="e.g. 150000" required x-model.number="form.monthly_income" />
                    <x-input name="existing_instalments" label="Existing monthly instalments (PKR)" type="number" inputmode="numeric" min="0" step="500" placeholder="0" x-model.number="form.existing_instalments" />
                    <x-input name="monthly_expenses" label="Monthly household expenses (PKR)" type="number" inputmode="numeric" min="0" step="1000" placeholder="0" x-model.number="form.monthly_expenses" />
                    <div>
                        <span class="field-label text-muted">Disposable income</span>
                        <div class="input bg-white font-disp text-[17px]" :class="disposable < 0 && 'text-coral'" x-text="fmt(disposable)">—</div>
                    </div>
                </div>

                <div class="mt-5 card-dark px-6 py-5" x-show="incomeValid" x-cloak>
                    <div class="glow w-[260px] h-[260px] -right-[80px] -top-[120px]" aria-hidden="true"></div>
                    <div class="relative z-[2] grid sm:grid-cols-2 gap-4">
                        <div>
                            <span class="eyebrow text-white/70">Provisional purchase limit</span>
                            <div class="font-disp font-extrabold text-[28px] tracking-tight spectrum-text mt-1" x-text="fmt(limit)"></div>
                            <span class="text-white/70 text-[12px]">{{ (int) ($ratios['limit'] * 100) }}% of monthly income</span>
                        </div>
                        <div>
                            <span class="eyebrow text-white/70">Max instalment / month</span>
                            <div class="font-disp font-extrabold text-[28px] tracking-tight mt-1" x-text="fmt(maxInstalment)"></div>
                            <span class="text-white/70 text-[12px]">{{ (int) ($ratios['instalment'] * 100) }}% of income, within disposable</span>
                        </div>
                    </div>
                    <p class="relative z-[2] text-white/65 text-[11.5px] mt-3">Final figures are set by our team after the risk assessment.</p>
                </div>
            </section>

            {{-- ------------------------------------------------ 4. review --}}
            <section data-step="3" x-show="current === 3" x-cloak class="card p-6 sm:p-8">
                @include('account.application.step-head', ['n' => 4, 'title' => 'Review & submit', 'intro' => 'Check everything once - you can jump back to any step to change it.'])

                <div class="space-y-4">
                    @include('account.application.review-block', ['title' => 'Personal details', 'step' => 0, 'rows' => [
                        'Full name' => 'form.full_name', 'CNIC' => 'form.cnic', 'Mobile' => 'form.mobile',
                        'Date of birth' => 'form.date_of_birth', 'City' => "label('city_id')", 'Address' => 'form.residential_address',
                    ]])
                    @include('account.application.review-block', ['title' => 'Identity documents', 'step' => 1, 'rows' => [
                        'CNIC front' => "files.cnic_front?.name ?? ".json_encode($doc('cnic_front_path', 'cnic-front') ? 'On file' : 'Missing'),
                        'CNIC back'  => "files.cnic_back?.name ?? ".json_encode($doc('cnic_back_path', 'cnic-back') ? 'On file' : 'Missing'),
                        'Selfie'     => "files.selfie?.name ?? ".json_encode($doc('selfie_path', 'selfie') ? 'On file' : 'Missing'),
                    ]])
                    @include('account.application.review-block', ['title' => 'Income & financial profile', 'step' => 2, 'rows' => [
                        'Employment' => "label('employment_status')", 'Employer / business' => 'form.employer_name || "—"', 'Income source' => "label('income_source')",
                        'Monthly income' => 'fmt(form.monthly_income || 0)', 'Existing instalments' => 'fmt(form.existing_instalments || 0)',
                        'Monthly expenses' => 'fmt(form.monthly_expenses || 0)', 'Disposable income' => 'fmt(disposable)',
                    ]])
                </div>

                <label class="mt-6 flex items-start gap-3 rounded-xl border border-line bg-paper px-4 py-3.5 cursor-pointer text-[13.5px]">
                    <input type="checkbox" class="mt-1 accent-nucleus" x-model="consent" required>
                    <span>I confirm these details are accurate and I authorise AtomPay to verify them, including a one-time visit to my residential address, for the purpose of setting my purchase limit.</span>
                </label>
            </section>

            {{-- ------------------------------------------------ nav --}}
            <div class="flex items-center justify-between gap-4 mt-5">
                <button type="button" class="btn btn-ghost" @click="back()" x-show="!isFirst" x-cloak>&larr; Back</button>
                <span x-show="isFirst"></span>
                <button type="button" class="btn btn-primary" @click="next()" x-show="!isLast">Continue &rarr;</button>
                <button type="submit" class="btn btn-primary" x-show="isLast" x-cloak :disabled="!consent">Submit application</button>
            </div>
        </form>
    </div>
</x-layouts.account>
