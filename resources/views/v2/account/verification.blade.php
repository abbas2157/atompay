{{--
    KYC & verification (v2). Where the customer is in the process, the identity
    details behind it, the three documents, and the staff address visit.

    Stages come from ProcessTracker, which derives them from the KYC profile and
    the latest assessment - there is no separate state to keep in sync. The CNIC
    and mobile are masked once verified: this page gets opened on a phone in
    public, and nothing here needs the full number to be readable.
--}}
@extends('v2.layout.account')

@section('acct-nav', 'verification')
@section('acct-eyebrow', 'Verification')
@section('acct-title', 'KYC & verification')
@section('acct-lead', 'Your identity details, your documents and the address visit — and exactly which stage of the process you are at.')

@section('acct')
    @php
        use App\Models\Enums\VerificationStatus;

        $status = $profile?->verification_status;
        $statusTone = match ($status) {
            VerificationStatus::Verified => 'b-green',
            VerificationStatus::Rejected => 'b-rose',
            default => 'b-blue',
        };

        // ProcessTracker's states map onto the .track classes in atompay.css.
        $trackClass = fn (string $state) => match ($state) {
            'done' => 'done',
            'current' => 'now',
            'blocked' => 'stop',
            default => '',
        };

        $mask = function (?string $value, int $keepStart, int $keepEnd) {
            $value = (string) $value;
            if ($value === '') {
                return '—';
            }
            $digits = preg_replace('/\D/', '', $value);
            if (strlen($digits) <= $keepStart + $keepEnd) {
                return $value;
            }

            return substr($digits, 0, $keepStart)
                .str_repeat('•', max(0, strlen($digits) - $keepStart - $keepEnd))
                .substr($digits, -$keepEnd);
        };
    @endphp

    {{-- Where you are. --}}
    <div class="card">
        <div class="card-head">
            <div>
                <h2 class="h3">Where you are</h2>
                <p class="body">Six stages, from your CNIC to your first purchase. We work this out from your profile &mdash; there is nothing to tick off yourself.</p>
            </div>
            <span class="badge {{ $statusTone }}">{{ $status?->label() ?? 'Not submitted' }}</span>
        </div>

        <ol class="track">
            @foreach ($stages as $stage)
                <li class="{{ $trackClass($stage['state']) }}" @if ($stage['state'] === 'current') aria-current="step" @endif>
                    {{ $stage['title'] }}
                    <small>{{ $stage['hint'] }}</small>
                </li>
            @endforeach
        </ol>

        @if ($credit['limit'] > 0)
            <div class="apay-rule">
                <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="34" height="34">
                <div>
                    <b>Your limit is live</b>
                    <p>Shop on Atomshop.pk and choose AtomPay on any eligible listing. Three months, no advance, 0% markup &mdash; and nothing to pay at checkout.</p>
                </div>
            </div>
        @endif
    </div>

    @if (! $profile)
        <div class="card">
            <div class="card-head" style="margin:0">
                <div>
                    <h2 class="h3">Nothing submitted yet</h2>
                    <p class="body">Verification starts with your identity details and a photo of your CNIC. It takes about five minutes.</p>
                </div>
                <a class="btn btn-p" href="{{ route('account.application') }}">Start my application</a>
            </div>
        </div>
    @else
        @if ($status === VerificationStatus::Rejected && $profile->verification_notes)
            <div class="note rose">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                <span class="nbody"><b>Verification unsuccessful</b>{{ $profile->verification_notes }}</span>
            </div>
        @endif

        {{-- Identity. --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="h3">Identity details</h2>
                    <p class="body">These must match your CNIC. Changing any of them reopens verification.</p>
                </div>
                <a class="btn btn-g btn-sm" href="{{ route('account.application') }}">Edit details</a>
            </div>
            <dl class="kv">
                <div><dt>Full name</dt><dd>{{ $profile->full_name ?: '—' }}</dd></div>
                <div>
                    <dt>CNIC number</dt>
                    <dd>{{ $profile->isVerified() ? $mask($profile->cnic, 5, 1) : ($profile->cnic ?: '—') }}
                        @if ($profile->isVerified())<span class="sub">Masked after verification</span>@endif
                    </dd>
                </div>
                <div>
                    <dt>Mobile</dt>
                    <dd>{{ $profile->isVerified() ? $mask($profile->mobile, 4, 3) : ($profile->mobile ?: '—') }}</dd>
                </div>
                <div>
                    <dt>Date of birth</dt>
                    <dd>{{ $profile->date_of_birth?->format('d M Y') ?? '—' }}
                        @if ($profile->date_of_birth)<span class="sub">Age {{ $profile->date_of_birth->age }}</span>@endif
                    </dd>
                </div>
                <div><dt>City</dt><dd>{{ $profile->city?->title ?? '—' }}</dd></div>
                <div><dt>Area</dt><dd>{{ $profile->area?->title ?? '—' }}</dd></div>
                <div style="grid-column:1/-1"><dt>Residential address</dt><dd>{{ $profile->residential_address ?: '—' }}</dd></div>
            </dl>
        </div>

        {{-- Documents. --}}
        @php
            $documents = [
                'cnic-front' => ['Front of your CNIC', 'cnic_front_path', '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><circle cx="8.5" cy="11" r="2"/><path d="M14 10h4M14 13.5h4M5 16.5c1-1.6 2-2.4 3.5-2.4s2.5.8 3.5 2.4"/>'],
                'cnic-back' => ['Back of your CNIC', 'cnic_back_path', '<rect x="2.5" y="5" width="19" height="14" rx="2.5"/><path d="M6 9.5h12M6 13h8M6 16h5"/>'],
                'selfie' => ['Selfie with your CNIC', 'selfie_path', '<circle cx="12" cy="9" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>'],
            ];
            $uploaded = collect($documents)->filter(fn ($d) => filled($profile->{$d[1]}))->count();
        @endphp
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="h3">Your documents</h2>
                    <p class="body">Held on private storage and shown only to you and the staff reviewing your application. JPG or PNG, up to {{ (int) (config('atompay.kyc.max_upload_kb') / 1024) }} MB each.</p>
                </div>
                <span class="badge {{ $uploaded === count($documents) ? 'b-green' : 'b-amber' }}">{{ $uploaded }} of {{ count($documents) }} uploaded</span>
            </div>

            <div class="docs">
                @foreach ($documents as $slug => [$label, $column, $icon])
                    @php($has = filled($profile->{$column}))
                    <div @class(['doc', 'filled' => $has])>
                        <span class="pic" aria-hidden="true"><svg viewBox="0 0 24 24">{!! $icon !!}</svg></span>
                        {{ $label }}
                        @if ($has)
                            <small>Uploaded {{ $profile->submitted_at?->format('d M Y') }}</small>
                            <span class="acts">
                                <a class="btn btn-g btn-sm" href="{{ route('documents.show', [$profile, $slug]) }}" target="_blank" rel="noopener">View</a>
                                <a class="btn btn-g btn-sm" href="{{ route('account.application') }}">Replace</a>
                            </span>
                        @else
                            <small>Not uploaded yet</small>
                            <a class="btn btn-p btn-sm" href="{{ route('account.application') }}">Upload</a>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="note blue" style="margin-top:16px">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l7.5 3.5v5.3c0 4.4-3.2 7-7.5 9.2-4.3-2.2-7.5-4.8-7.5-9.2V6.5z"/><path d="M9 12l2.2 2.2L15.5 10"/></svg>
                <span class="nbody">Your documents are never attached to an order, a receipt or a listing, and sellers never see them.</span>
            </div>
        </div>

        {{-- Address verification: staff-owned, read-only here. --}}
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="h3">Address verification</h2>
                    <p class="body">A one-time visit by our field team. Carried out by AtomPay, not by you.</p>
                </div>
                <span class="badge {{ $profile->address_verified ? 'b-green' : 'b-amber' }}">{{ $profile->address_verified ? 'Verified' : 'Pending visit' }}</span>
            </div>

            @if ($profile->address_verified)
                <dl class="kv">
                    <div><dt>Visited on</dt><dd>{{ $profile->verified_at?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>Verified by</dt><dd>{{ $profile->verifier?->shortName() ?? 'AtomPay staff' }}<span class="sub">AtomPay field team</span></dd></div>
                    <div><dt>Signed form</dt><dd>{{ $profile->verification_form_path ? 'On file' : 'Not on file' }}<span class="sub">Signature taken at the address</span></dd></div>
                    <div><dt>Result</dt><dd>Address confirmed<span class="sub">Resident present, address matches CNIC</span></dd></div>
                </dl>
            @else
                <div class="note">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                    <span class="nbody"><b>A visit is still to happen</b>Our representative will visit the address you gave to verify it and take your signature. Keep the mobile number on this page reachable.</span>
                </div>
            @endif
        </div>

        <div class="card soft">
            <h3 class="h3">What a verified KYC also does</h3>
            <ul class="ticks" style="margin-top:14px">
                <li>Counts as Atomshop.pk verification too &mdash; no separate "get verified" step on the shop</li>
                <li>Lets you check out without adding a city and area first</li>
                <li>Stays in force while your details are unchanged; editing any of them reopens it</li>
            </ul>
        </div>
    @endif
@endsection
