{{--
    AtomPay KYC & purchase limit application (v2) - the customer's part, as a
    4-step wizard.

    Same contract as the v1 page it replaces: ONE <form> holding every step,
    steps shown and hidden client-side, posted once, so KycApplicationRequest
    stays the single source of validation and a customer without JavaScript
    still sees every field and can submit.

    v1 drove this with Alpine; v2 loads no Alpine, so the behaviour lives in
    site.js behind [data-kyc-wizard] and the rules come from data- attributes
    rather than being hardcoded there.
--}}
@extends('v2.layout.account')

@section('acct-nav', 'application')
@section('acct-eyebrow', 'KYC & purchase limit')

@php
    $kycVerified = $profile->exists && $profile->isVerified();
    $approved    = $active !== null;                    // a limit is in force
    $reviewing   = $approved && $latest?->isPending();  // a review is already with staff

    $title = $approved
        ? 'Request a limit review'
        : ($profile->exists ? 'Update your application' : 'Apply for your AtomPay limit');

    $doc = fn (string $column, string $slug) => $profile->exists && $profile->{$column}
        ? route('documents.show', [$profile, $slug])
        : null;

    $steps = [
        ['title' => 'Personal details',    'hint' => 'As printed on your CNIC',    'done' => $kycVerified,                               'doneLabel' => 'Verified'],
        ['title' => 'Identity documents',  'hint' => 'CNIC photos and a selfie',   'done' => $kycVerified && $profile->hasDocuments(),    'doneLabel' => 'Verified'],
        ['title' => 'Income & financials', 'hint' => 'Work, income and outgoings', 'done' => $approved,                                  'doneLabel' => $reviewing ? 'Approved · review pending' : 'Approved'],
        ['title' => 'Review & submit',     'hint' => 'Check everything once',      'done' => $kycVerified && $approved,                   'doneLabel' => 'Limit approved'],
    ];

    // Everything already on file: any step can be opened straight away.
    $furthest = $profile->exists && $profile->isSubmitted() && $latest ? count($steps) - 1 : 0;

    // Which step owns each field, so a server-side error reopens the right one.
    $fieldStep = [
        'full_name' => 0, 'cnic' => 0, 'mobile' => 0, 'date_of_birth' => 0, 'city_id' => 0, 'area_id' => 0, 'residential_address' => 0,
        'cnic_front' => 1, 'cnic_back' => 1, 'selfie' => 1,
        'employment_status' => 2, 'employer_name' => 2, 'income_source' => 2,
        'monthly_income' => 2, 'existing_instalments' => 2, 'monthly_expenses' => 2,
    ];
    $errorStep = collect($errors->keys())
        ->map(fn ($k) => $fieldStep[$k] ?? null)
        ->filter(fn ($s) => $s !== null)
        ->min();

    // Shown the way people write them; KycApplicationRequest normalises them back.
    $vCnic   = \App\Support\Pakistan::formatCnic(old('cnic', $profile->cnic));
    $vMobile = \App\Support\Pakistan::formatMobile(old('mobile', $profile->mobile));

    // Rendered open when the chosen status has an employer, so the field is
    // present without JavaScript too - Rule::requiredIf would otherwise demand
    // a field the customer could never see. site.js keeps it in sync after that.
    $chosenStatus  = old('employment_status', $latest?->employment_status?->value);
    $needsEmployer = \App\Models\Enums\EmploymentStatus::tryFrom((string) $chosenStatus)?->hasEmployer() ?? false;

    $needDocs   = ! $profile->hasDocuments();
    $maxUploadMb = (int) (config('atompay.kyc.max_upload_kb') / 1024);
    $limitRatio = (float) config('atompay.credit.limit_ratio');
    $capRatio   = (float) config('atompay.credit.instalment_ratio');

    $documents = [
        ['cnic_front', 'CNIC front', 'Front of the card', $doc('cnic_front_path', 'cnic-front')],
        ['cnic_back',  'CNIC back',  'Back of the card',  $doc('cnic_back_path', 'cnic-back')],
        ['selfie',     'Selfie with CNIC', 'Hold your CNIC next to your face', $doc('selfie_path', 'selfie')],
    ];
@endphp

@section('acct-title', $title)
@section('acct-lead', $approved
    ? 'Earning more or spending less? Update your income details and our team will review your limit.'
    : 'About five minutes. You can go back to any step before you submit.')

@section('acct')
    <form method="POST" action="{{ route('account.application.store') }}" enctype="multipart/form-data" novalidate
          data-kyc-wizard
          data-error-step="{{ $errorStep !== null ? (int) $errorStep : '' }}"
          data-furthest="{{ $furthest }}"
          data-limit-ratio="{{ $limitRatio }}"
          data-cap-ratio="{{ $capRatio }}">
        @csrf

        {{-- The stepper doubles as the progress indicator; .track is the same
             component the verification page uses, so the two pages read alike. --}}
        <ol class="track" data-wizard-track>
            @foreach ($steps as $i => $step)
                <li @class(['done' => $step['done'], 'now' => $i === ($errorStep ?? 0)]) data-wizard-dot="{{ $i }}">
                    {{ $step['title'] }}
                    <small>{{ $step['done'] ? '✓ '.$step['doneLabel'] : $step['hint'] }}</small>
                </li>
            @endforeach
        </ol>

        @if ($approved && ! $errors->any())
            <div class="note green" style="margin-bottom:18px">
                <span>
                    <b>Your @pkr($active->approved_limit) limit stays active</b> while we review &mdash; you can keep shopping with it.
                    {{ $reviewing ? 'A review is already with our team; submitting again updates it.' : 'Change your income details to request a higher limit.' }}
                    Changing your name, CNIC, date of birth, address or documents means we verify your identity again.
                </span>
            </div>
        @endif

        @if ($errors->any())
            <div class="note rose" style="margin-bottom:18px">
                <span>Some details need attention &mdash; we&rsquo;ve opened the step that has them.</span>
            </div>
        @endif

        {{-- ------------------------------------------------ 1. personal --}}
        <section class="card" data-wizard-step="0">
            <div class="card-head">
                <h2 class="h3">Personal details</h2>
                <p>Enter these exactly as they appear on your CNIC &mdash; the visit team checks them against the card.</p>
            </div>
            <div class="form">
                <x-v2.field name="full_name" label="Full name" autocomplete="name" :value="$profile->full_name" :full="true" required />
                <x-v2.field name="cnic" label="CNIC number" inputmode="numeric" autocomplete="off"
                            placeholder="42101-1234567-1" maxlength="15" pattern="[0-9]{5}-?[0-9]{7}-?[0-9]"
                            :value="$vCnic" data-pk-format="cnic" required />
                <x-v2.field name="mobile" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel"
                            placeholder="0300 1234567" maxlength="12" pattern="(\+?92|0)?[\s-]?3[0-9]{2}[\s-]?[0-9]{7}"
                            :value="$vMobile" data-pk-format="mobile" required />
                <x-v2.field name="date_of_birth" label="Date of birth" type="date"
                            max="{{ now()->subYears(config('atompay.kyc.min_age'))->format('Y-m-d') }}"
                            :value="$profile->date_of_birth?->format('Y-m-d')" required />

                <label class="field" for="city_id">
                    City
                    <select id="city_id" name="city_id" data-search @if ($errors->has('city_id')) aria-invalid="true" @endif>
                        <option value="">Select city</option>
                        @foreach ($cities as $city)
                            <option value="{{ $city->id }}" @selected((string) old('city_id', $profile->city_id) === (string) $city->id)>{{ $city->title }}</option>
                        @endforeach
                    </select>
                    @error('city_id')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                </label>

                {{-- Reloaded from the chosen city. Server-rendered for the city
                     already on file, so it is right before any JavaScript runs
                     and after a validation error. --}}
                <label class="field" for="area_id">
                    Area
                    <select id="area_id" name="area_id" data-search
                            data-area-for="city_id"
                            data-area-url="{{ route('account.areas', ['city' => '__CITY__']) }}"
                            @disabled($areas->isEmpty())
                            @if ($errors->has('area_id')) aria-invalid="true" @endif>
                        <option value="">{{ $areas->isEmpty() ? 'Choose a city first' : 'Select area' }}</option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" @selected((string) old('area_id', $profile->area_id) === (string) $area->id)>{{ $area->title }}</option>
                        @endforeach
                    </select>
                    @error('area_id')
                        <span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>
                    @else
                        <span class="hint">Helps us deliver to the right part of {{ optional($cities->firstWhere('id', (int) old('city_id', $profile->city_id)))->title ?? 'your city' }}.</span>
                    @enderror
                </label>

                <label class="field full" for="residential_address">
                    Residential address
                    <textarea id="residential_address" name="residential_address" rows="3" required
                              placeholder="House / flat, street, area"
                              @if ($errors->has('residential_address')) aria-invalid="true" @endif>{{ old('residential_address', $profile->residential_address) }}</textarea>
                    @error('residential_address')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                </label>
            </div>
        </section>

        {{-- ----------------------------------------------- 2. documents --}}
        <section class="card" data-wizard-step="1">
            <div class="card-head">
                <h2 class="h3">Identity documents</h2>
                <p>Clear, well-lit photos with all four corners visible. Max {{ $maxUploadMb }} MB each. Stored privately and seen only by the verification team.</p>
            </div>

            <div class="docs">
                @foreach ($documents as [$name, $label, $hint, $current])
                    <label @class(['doc', 'filled' => (bool) $current]) for="{{ $name }}" data-doc-tile>
                        <span class="pic" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 15 4-4 3 3 4-4 7 6"/><circle cx="9" cy="10" r="1.4"/>
                            </svg>
                        </span>
                        {{ $label }}
                        <small data-doc-name>{{ $current ? 'On file — choose a file to replace it' : $hint }}</small>
                        <input id="{{ $name }}" name="{{ $name }}" type="file" accept="image/jpeg,image/png,image/webp"
                               @if ($needDocs) required @endif
                               @if ($errors->has($name)) aria-invalid="true" @endif>
                        @if ($current)
                            <span class="acts"><a class="link" href="{{ $current }}" target="_blank" rel="noopener">View</a></span>
                        @endif
                    </label>
                @endforeach
            </div>

            @foreach ($documents as [$name, $label, , ])
                @error($name)<p class="field-error" style="margin-top:10px">{{ $message }}</p>@enderror
            @endforeach

            <div class="note blue" style="margin-top:18px">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:none">
                    <path d="M12 3l7 4v6c0 4-3 6-7 8-4-2-7-4-7-8V7z"/><path d="M9 12l2 2 4-4"/>
                </svg>
                <span>Your documents are used only to verify your identity for AtomPay and are never shown on Atomshop.</span>
            </div>
        </section>

        {{-- -------------------------------------------------- 3. income --}}
        <section class="card" data-wizard-step="2">
            <div class="card-head">
                <h2 class="h3">Income &amp; financial profile</h2>
                <p>Honest figures get you the right limit &mdash; they are checked during verification.</p>
            </div>

            <div class="form">
                <label class="field" for="employment_status">
                    Employment / business status
                    <select id="employment_status" name="employment_status" data-search required data-wizard-employment
                            @if ($errors->has('employment_status')) aria-invalid="true" @endif>
                        <option value="">Select status</option>
                        @foreach ($employment as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_status', $latest?->employment_status?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('employment_status')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                </label>

                <label class="field" for="income_source">
                    Income source
                    <select id="income_source" name="income_source" data-search required
                            @if ($errors->has('income_source')) aria-invalid="true" @endif>
                        <option value="">Select source</option>
                        @foreach ($sources as $value => $label)
                            <option value="{{ $value }}" @selected(old('income_source', $latest?->income_source?->value) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('income_source')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                </label>

                {{-- Shown only for the statuses that have an employer. Server-side
                     Rule::requiredIf already enforces it, so hiding it is safe. --}}
                <div class="field full" data-wizard-employer @if (! $needsEmployer) hidden @endif>
                    <x-v2.field name="employer_name" label="Employer / business name"
                                :value="$latest?->employer_name" :full="true" />
                </div>

                <x-v2.field name="monthly_income" label="Monthly income (PKR)" type="number" inputmode="numeric"
                            min="1000" step="1000" placeholder="e.g. 150000"
                            :value="old('monthly_income', $prefill['monthly_income'] ?? $latest?->monthly_income)"
                            data-wizard-income required />
                <x-v2.field name="existing_instalments" label="Existing monthly installments (PKR)" type="number" inputmode="numeric"
                            min="0" step="500" placeholder="0"
                            :value="$latest?->existing_instalments ?? 0" data-wizard-outgoing />
                <x-v2.field name="monthly_expenses" label="Monthly household expenses (PKR)" type="number" inputmode="numeric"
                            min="0" step="1000" placeholder="0"
                            :value="$latest?->monthly_expenses ?? 0" data-wizard-outgoing />

                <div class="field">
                    Disposable income
                    <output class="disp" data-wizard-out="disposable">&mdash;</output>
                    <span class="hint">Income less installments and expenses.</span>
                </div>
            </div>

            {{-- Provisional figures, same ratios the server uses. --}}
            <div class="spend" style="margin-top:20px" data-wizard-estimate hidden>
                <span class="lbl">Provisional purchase limit</span>
                <div class="amt" data-wizard-out="limit">&mdash;</div>
                <div class="row">
                    <span><b data-wizard-out="cap">&mdash;</b> max installment / month</span>
                    <span>{{ (int) ($limitRatio * 100) }}% of income &middot; cap {{ (int) ($capRatio * 100) }}%, within disposable</span>
                </div>
                <p class="hint" style="margin-top:10px">Final figures are set by our team after the risk assessment.</p>
            </div>
        </section>

        {{-- -------------------------------------------------- 4. review --}}
        <section class="card" data-wizard-step="3">
            <div class="card-head">
                <h2 class="h3">Review &amp; submit</h2>
                <p>Check everything once &mdash; you can jump back to any step to change it.</p>
            </div>

            <dl class="kv" data-wizard-review>
                <dt>Full name</dt><dd data-wizard-echo="full_name">&mdash;</dd>
                <dt>CNIC</dt><dd data-wizard-echo="cnic">&mdash;</dd>
                <dt>Mobile</dt><dd data-wizard-echo="mobile">&mdash;</dd>
                <dt>Date of birth</dt><dd data-wizard-echo="date_of_birth">&mdash;</dd>
                <dt>City</dt><dd data-wizard-echo="city_id">&mdash;</dd>
                <dt>Area</dt><dd data-wizard-echo="area_id">&mdash;</dd>
                <dt>Address</dt><dd data-wizard-echo="residential_address">&mdash;</dd>
                <dt>Documents</dt><dd data-wizard-echo="documents">&mdash;</dd>
                <dt>Employment</dt><dd data-wizard-echo="employment_status">&mdash;</dd>
                <dt>Employer / business</dt><dd data-wizard-echo="employer_name">&mdash;</dd>
                <dt>Income source</dt><dd data-wizard-echo="income_source">&mdash;</dd>
                <dt>Monthly income</dt><dd data-wizard-echo="monthly_income">&mdash;</dd>
                <dt>Existing installments</dt><dd data-wizard-echo="existing_instalments">&mdash;</dd>
                <dt>Monthly expenses</dt><dd data-wizard-echo="monthly_expenses">&mdash;</dd>
                <dt>Disposable income</dt><dd data-wizard-out="disposable">&mdash;</dd>
            </dl>

            <label class="consent" for="kyc_consent">
                <input id="kyc_consent" type="checkbox" data-wizard-consent required>
                <span>I confirm these details are accurate and I authorise AtomPay to verify them, including a
                      one-time visit to my residential address, for the purpose of setting my purchase limit.</span>
            </label>
        </section>

        {{-- ----------------------------------------------------- nav --}}
        <div class="wizard-nav">
            <button type="button" class="btn btn-g" data-wizard-back hidden>&larr; Back</button>
            <span></span>
            <button type="button" class="btn btn-p" data-wizard-next hidden>Continue &rarr;</button>
            <button type="submit" class="btn btn-p" data-wizard-submit>
                {{ $approved ? 'Request limit review' : 'Submit application' }}
            </button>
        </div>
    </form>
@endsection
