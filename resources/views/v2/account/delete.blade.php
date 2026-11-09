@extends('v2.layout.account')

@section('acct-nav', 'overview')
@section('acct-eyebrow', 'My AtomPay')
@section('acct-title', 'Delete your account')
@section('acct-lead', 'This closes your AtomPay and Atomshop.pk account for good and deletes your identity details and documents. Past orders and payments are kept, without your name.')

@section('acct')
    @if ($blocked)
        <div class="note">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
            <span class="nbody"><b>Not yet</b>{{ $blocked }}</span>
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:12px">
            <a class="btn btn-p" href="{{ route('account.dashboard') }}">Back to my AtomPay</a>
            <a class="btn btn-g" href="{{ route('account.orders') }}">See what is owed</a>
        </div>
    @else
        <div class="card">
            <div class="card-head">
                <div>
                    <h2 class="h3">Confirm with your password</h2>
                    <p class="body">This cannot be undone. <a class="link" href="{{ route('account.delete.info') }}">What is deleted</a></p>
                </div>
            </div>

            <form method="POST" action="{{ route('account.delete.perform') }}" data-busy>
                @csrf
                <div class="form">
                    <div class="field full">
                        Your password
                        <span class="pw-wrap">
                            <input id="password" name="password" type="password" autocomplete="current-password" required autofocus
                                   @if ($errors->has('password')) aria-invalid="true" @endif>
                            <button class="pw-toggle" type="button" aria-label="Show password" aria-pressed="false">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </span>
                        @error('password')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                    </div>

                    <div class="field full">
                        <span class="check">
                            <input id="confirm" type="checkbox" name="confirm" value="1" required>
                            <label for="confirm" style="font-weight:600">I understand this can&rsquo;t be undone.</label>
                        </span>
                        @error('confirm')<span class="hint" style="color:var(--rose);font-weight:600">{{ $message }}</span>@enderror
                    </div>

                    <div class="field full" style="flex-direction:row;flex-wrap:wrap;gap:12px">
                        <button class="btn" type="submit" style="background:var(--rose);color:#fff">Delete my account</button>
                        <a class="btn btn-g" href="{{ route('account.dashboard') }}">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    @endif
@endsection
