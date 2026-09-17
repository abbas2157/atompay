{{-- One application, all five sections. Staff record Section 2 and decide Sections 4-5. --}}
@php
    $doc = fn (string $column, string $slug) => $profile && $profile->{$column} ? route('documents.show', [$profile, $slug]) : null;
    $riskDot = ['low' => 'paid', 'medium' => 'due', 'high' => 'late'];
@endphp
<x-layouts.account title="Application #{{ $assessment->id }}" :wide="true">
    <div class="flex justify-between items-end flex-wrap gap-4 mb-6">
        <div>
            <a href="{{ route('staff.assessments.index', ['status' => $assessment->status->value]) }}" class="eyebrow no-underline hover:text-ink">&larr; {{ $assessment->status->label() }} applications</a>
            <h1 class="mt-2 text-[clamp(26px,3.5vw,34px)]">{{ $user->name }}</h1>
            <p class="text-muted text-[14px] mt-1 font-mono">{{ $user->phone }} &middot; {{ $user->email }} &middot; Application #{{ $assessment->id }} &middot; {{ $assessment->created_at->format('d M Y H:i') }}</p>
        </div>
        <x-status-pill :state="$assessment->isUsable() ? 'ok' : ($assessment->isPending() ? 'pending' : 'late')">{{ $assessment->status->label() }}</x-status-pill>
    </div>

    @if ($errors->any())
        <x-alert type="error" class="mb-6">{{ $errors->first() }}</x-alert>
    @endif

    <div class="grid lg:grid-cols-[1.1fr_.9fr] gap-6 items-start">
        <div class="space-y-6">

            {{-- 1. Customer verification (read-only) --}}
            <x-form-section number="1" title="Customer verification">
                @if ($profile)
                    <dl class="grid sm:grid-cols-2 gap-x-6 text-[14px]">
                        <x-side-row label="Full name">{{ $profile->full_name }}</x-side-row>
                        <x-side-row label="CNIC">{{ $profile->cnic_formatted }}</x-side-row>
                        <x-side-row label="Mobile">{{ $profile->mobile }}</x-side-row>
                        <x-side-row label="Date of birth">{{ $profile->date_of_birth->format('d M Y') }} ({{ $profile->date_of_birth->age }})</x-side-row>
                        <x-side-row label="City">{{ $profile->city?->title ?? '—' }}</x-side-row>
                        <x-side-row label="Submitted">{{ $profile->submitted_at?->format('d M Y H:i') }}</x-side-row>
                    </dl>
                    <p class="text-[14px] mt-3"><span class="text-muted">Residential address:</span> {{ $profile->residential_address }}</p>
                    <div class="flex gap-3 flex-wrap mt-4">
                        @foreach (['cnic_front_path' => ['cnic-front', 'CNIC front'], 'cnic_back_path' => ['cnic-back', 'CNIC back'], 'selfie_path' => ['selfie', 'Selfie']] as $column => [$slug, $label])
                            @if ($url = $doc($column, $slug))
                                <a href="{{ $url }}" target="_blank" rel="noopener" class="btn btn-ghost btn-sm">{{ $label }} &nearr;</a>
                            @else
                                <span class="btn btn-ghost btn-sm opacity-50">{{ $label }} missing</span>
                            @endif
                        @endforeach
                    </div>
                @else
                    <p class="text-muted text-[14px]">The customer has not submitted KYC details yet.</p>
                @endif
            </x-form-section>

            {{-- 2. One-time address verification (staff) --}}
            <x-form-section number="2" title="One-time address verification" intro="Record the physical visit. This is done once per customer and reused for later applications.">
                @if ($profile)
                    <form method="POST" action="{{ route('staff.assessments.address', $assessment) }}" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-4">
                        @csrf
                        <x-select name="address_verified" label="Physical address verification" :options="['1' => 'Address found and confirmed', '0' => 'Address not found / mismatch']" :value="(int) $profile->address_verified" required />
                        <x-select name="face_verified" label="CNIC &amp; face verification" :options="['1' => 'Face matches CNIC', '0' => 'Could not confirm']" :value="(int) $profile->face_verified" required />
                        <x-input name="verified_at" label="Verification date" type="date" :value="($profile->verified_at ?? today())->format('Y-m-d')" max="{{ today()->format('Y-m-d') }}" required />
                        <x-select name="verification_status" label="Verification status" :options="$options['verification']" :value="$profile->verification_status->value" required />
                        <x-file-input name="verification_form" label="Signed verification form" :current="$doc('verification_form_path', 'form')" accept="image/jpeg,image/png,image/webp,application/pdf" class="sm:col-span-2" />
                        <div class="sm:col-span-2">
                            <label for="verification_notes" class="field-label text-muted">Notes</label>
                            <textarea id="verification_notes" name="verification_notes" rows="2" class="input">{{ old('verification_notes', $profile->verification_notes) }}</textarea>
                        </div>
                        <div class="sm:col-span-2 flex items-center justify-between gap-4 flex-wrap">
                            <p class="text-muted text-[12.5px]">
                                @if ($profile->verified_by) Last recorded by {{ $profile->verifier?->name }} on {{ $profile->verified_at?->format('d M Y') }}. @else Not yet verified. @endif
                            </p>
                            <button type="submit" class="btn btn-primary btn-sm">Save verification</button>
                        </div>
                    </form>
                @else
                    <p class="text-muted text-[14px]">Available once KYC is submitted.</p>
                @endif
            </x-form-section>

            {{-- 3. Income & financial profile (read-only) --}}
            <x-form-section number="3" title="Income &amp; financial profile">
                <dl class="grid sm:grid-cols-2 gap-x-6 text-[14px]">
                    <x-side-row label="Employment / business">{{ $assessment->employment_status->label() }}</x-side-row>
                    <x-side-row label="Employer / business name">{{ $assessment->employer_name ?? '—' }}</x-side-row>
                    <x-side-row label="Income source">{{ $assessment->income_source->label() }}</x-side-row>
                    <x-side-row label="Monthly income">@pkr($assessment->monthly_income)</x-side-row>
                    <x-side-row label="Existing monthly instalments">@pkr($assessment->existing_instalments)</x-side-row>
                    <x-side-row label="Monthly expenses">@pkr($assessment->monthly_expenses)</x-side-row>
                    <x-side-row label="Disposable income"><span class="{{ $assessment->disposable_income < 0 ? 'text-coral' : '' }}">@pkr($assessment->disposable_income)</span></x-side-row>
                    <x-side-row label="Owed to AtomShop now">@pkr($summary['used'])</x-side-row>
                </dl>
            </x-form-section>
        </div>

        <div class="space-y-6 lg:sticky lg:top-24">
            {{-- 4 + 5. Risk assessment and limit decision (staff) --}}
            <form method="POST" action="{{ route('staff.assessments.decide', $assessment) }}" class="space-y-6">
                @csrf
                <x-form-section number="4" title="Risk assessment" intro="Provisional score from the customer's record; your credit-history view adjusts it.">
                    <div class="flex items-center gap-4 mb-4">
                        <div class="font-disp font-extrabold text-[40px] tracking-tight leading-none">{{ $risk->score }}</div>
                        <div>
                            <span class="dot dot-{{ $riskDot[$risk->category->value] }}"></span><b>{{ $risk->category->label() }} risk</b>
                            <span class="block text-muted text-[12.5px] mt-0.5">Recorded: {{ $assessment->risk_score }} &middot; {{ $assessment->risk_category->label() }}</span>
                        </div>
                    </div>
                    <dl class="text-[14px]">
                        <x-side-row label="Previous payment history">{{ $risk->paymentHistory->label() }}</x-side-row>
                        <x-side-row label="Existing obligations (AtomShop)">@pkr($risk->existingObligations)</x-side-row>
                        <x-side-row label="Other lenders / month">@pkr($assessment->existing_instalments)</x-side-row>
                    </dl>
                    @if ($risk->reasons)
                        <ul class="mt-3 text-[13px] text-muted list-disc pl-5 space-y-0.5">
                            @foreach ($risk->reasons as $reason)<li>{{ $reason }}</li>@endforeach
                        </ul>
                    @endif
                    <x-select name="credit_history" label="Credit history (outside AtomShop)" :options="$options['creditHistory']" :value="$assessment->credit_history?->value" required class="mt-4" />
                </x-form-section>

                <x-form-section number="5" title="AtomPay limit decision" intro="Pre-filled with the provisional figures (30% / 10% rule). Adjust before deciding.">
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-input name="approved_limit" label="Approved purchase limit (PKR)" type="number" min="0" step="1000" :value="$assessment->approved_limit" required />
                        <x-input name="max_instalment" label="Maximum instalment (PKR)" type="number" min="0" step="500" :value="$assessment->max_instalment" required />
                        <x-select name="approved_tenure" label="Approved tenure" :options="array_combine($tenures, array_map(fn ($t) => $t.' months', $tenures))" :value="$assessment->approved_tenure" placeholder="Default (max offered)" />
                        <x-select name="status" label="Limit status" :options="$options['decision']" :value="$assessment->isPending() ? null : $assessment->status->value" required />
                        <div class="sm:col-span-2">
                            <label for="notes" class="field-label text-muted">Notes to customer (shown on their dashboard)</label>
                            <textarea id="notes" name="notes" rows="2" class="input">{{ old('notes', $assessment->notes) }}</textarea>
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-4 flex-wrap mt-5">
                        <p class="text-muted text-[12.5px]">
                            @if ($assessment->decided_by) Decided by {{ $assessment->decider?->name }} on {{ $assessment->decided_at?->format('d M Y H:i') }}. @endif
                        </p>
                        <button type="submit" class="btn btn-primary">Record decision</button>
                    </div>
                </x-form-section>
            </form>

            @if ($history->isNotEmpty())
                <div class="card p-6">
                    <h4 class="text-[14px] mb-3">Previous applications</h4>
                    @foreach ($history as $h)
                        <x-side-row :label="$h->created_at->format('d M Y')">
                            <a href="{{ route('staff.assessments.show', $h) }}" class="underline">#{{ $h->id }}</a> &middot; @pkr($h->approved_limit) &middot; {{ $h->status->label() }}
                        </x-side-row>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-layouts.account>
