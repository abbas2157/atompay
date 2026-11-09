@extends('v2.layout.auth')

@section('auth-eyebrow', 'Forgot password')
@section('auth-title', 'Choose a new password')
@section('auth-lead', 'It will work on AtomPay and Atomshop.pk. You will be signed out of the AtomPay app on every phone.')

@section('auth-form')
    @error('reset_token')
        <div class="note rose" style="margin-bottom:20px">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            <span class="nbody">{{ $message }} <a class="link" href="{{ route('password.request') }}">Start again</a></span>
        </div>
    @enderror

    <form method="POST" action="{{ route('password.update') }}" data-busy>
        @csrf
        <div class="form">
            <div class="field full">
                New password
                <span class="pw-wrap">
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" required autofocus
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

            <label class="field full" for="password_confirmation">
                Confirm new password
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
            </label>

            <div class="field full">
                <button class="btn btn-p btn-lg" type="submit" style="width:100%">Save new password</button>
            </div>
        </div>
    </form>
@endsection
