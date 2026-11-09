@extends('v2.layout.auth')

@section('auth-eyebrow', 'My AtomPay')
@section('auth-title', 'Sign in')
@section('auth-lead', 'Use the same mobile number or email and password you use on Atomshop.pk.')

@section('auth-form')
    <form method="POST" action="{{ route('login.perform') }}" data-busy>
        @csrf
        <div class="form">
            <x-v2.field name="login" label="Mobile number or email" autocomplete="username" :full="true" required autofocus />

            <div class="field full">
                Password
                <span class="pw-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required
                           @if ($errors->has('password')) aria-invalid="true" @endif>
                    <button class="pw-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
                @error('password')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
            </div>

            <div class="field full" style="flex-direction:row;justify-content:space-between;align-items:center;gap:14px;flex-wrap:wrap">
                <span class="check">
                    <input id="remember" type="checkbox" name="remember" value="1">
                    <label for="remember" style="font-weight:600">Keep me signed in</label>
                </span>
                <a class="link" href="{{ route('password.request') }}">Forgot password?</a>
            </div>

            <div class="field full">
                <button class="btn btn-p btn-lg" type="submit" style="width:100%">Sign in</button>
            </div>
        </div>
    </form>
@endsection

@section('auth-foot')
    New to Atomshop? <a class="link" href="{{ route('register') }}">Create an account</a>
@endsection
