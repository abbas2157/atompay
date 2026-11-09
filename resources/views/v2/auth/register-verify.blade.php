{{-- Sign-up step 2: the code sent by email or on WhatsApp. $state from SignupService::describe(). --}}
@extends('v2.layout.auth')

@section('auth-eyebrow', 'Create your account')
@section('auth-title', 'Enter your code')

@section('auth-lead')
    We sent a {{ config('atompay.signup.code_length', config('atompay.password_reset.code_length')) }}-digit code
    {{ $state['channel'] === 'whatsapp' ? 'on WhatsApp to' : 'by email to' }} {{ $state['destination'] }}.
    It expires in {{ config('atompay.signup.code_ttl_minutes') }} minutes.
@endsection

@section('auth-form')
    @php($length = (int) config('atompay.password_reset.code_length'))
    <form method="POST" action="{{ route('register.verify.perform') }}" data-busy>
        @csrf
        <div class="form">
            <x-v2.field name="code" label="One-time code" class="code-field" :full="true" required autofocus
                        inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*"
                        :maxlength="$length" />
            <div class="field full">
                <button class="btn btn-p btn-lg" type="submit" style="width:100%">Create account</button>
            </div>
        </div>
    </form>

    <div class="code-meta">
        <form method="POST" action="{{ route('register.resend') }}" data-resend="{{ $state['resend_in'] }}">
            @csrf
            <button class="link" type="button" data-resend-button style="background:none;border:0;padding:0;cursor:pointer">
                Didn&rsquo;t get it? Send a new code
            </button>
        </form>
        <span>Wrong {{ $state['channel'] === 'whatsapp' ? 'number' : 'email' }}?
            <a class="link" href="{{ route('register') }}">Change details</a>
        </span>
    </div>
@endsection
