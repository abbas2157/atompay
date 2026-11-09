@extends('v2.layout.auth')

@section('auth-eyebrow', 'One account, both sites')
@section('auth-title', 'Create your account')
@section('auth-lead', 'Sign up with your email and we will email you a code, or with your mobile number and we will send it on WhatsApp.')

@section('auth-form')
    <form method="POST" action="{{ route('register.perform') }}" data-busy>
        @csrf
        <div class="form">
            <x-v2.field name="name" label="Full name" autocomplete="name" :full="true" required autofocus />
            <x-v2.field name="login" label="Email or mobile number" autocomplete="username" :full="true" required
                        placeholder="you@example.com or 0300 1234567"
                        hint="An email gets its code by email; a mobile number gets it on WhatsApp." />

            <div class="field">
                Password
                <span class="pw-wrap">
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required
                           @if ($errors->has('password')) aria-invalid="true" @endif>
                    <button class="pw-toggle" type="button" aria-label="Show password" aria-pressed="false">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                    </button>
                </span>
                @error('password')
                    <span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>
                @else
                    <span class="hint">At least 8 characters.</span>
                @enderror
            </div>

            <label class="field" for="password_confirmation">
                Confirm password
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </label>

            <div class="field full">
                <button class="btn btn-p btn-lg" type="submit" style="width:100%">Continue</button>
            </div>
        </div>
    </form>
@endsection

@section('auth-foot')
    Already have one? <a class="link" href="{{ route('login') }}">Sign in</a>
@endsection
