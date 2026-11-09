@extends('v2.layout.auth')

@section('auth-eyebrow', 'Forgot password')
@section('auth-title', 'Reset your password')
@section('auth-lead', 'Enter the email or mobile number on your Atomshop account. We will send a one-time code by email, or on WhatsApp if you enter a mobile number.')

@section('auth-form')
    <form method="POST" action="{{ route('password.email') }}" data-busy>
        @csrf
        <div class="form">
            <x-v2.field name="login" label="Email or mobile number" autocomplete="username" :full="true" required autofocus />
            <div class="field full">
                <button class="btn btn-p btn-lg" type="submit" style="width:100%">Send code</button>
            </div>
        </div>
    </form>
@endsection

@section('auth-foot')
    Remembered it? <a class="link" href="{{ route('login') }}">Back to sign in</a>
@endsection
