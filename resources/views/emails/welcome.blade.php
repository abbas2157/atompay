@extends('emails.layout')

@section('title', 'Welcome to AtomPay')
@section('preheader', 'Your account works on AtomPay and AtomShop.pk. Here is how to get your limit.')

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#6C6C74;">Welcome</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Hi {{ $name }}, your account is ready</h1>
    <p style="margin:0 0 12px;">You can now sign in to AtomPay and AtomShop.pk with the same email or mobile number and password.</p>
    <p style="margin:0 0 8px;">To shop in instalments, get your purchase limit in three steps:</p>
    <ol style="margin:0 0 4px;padding-left:20px;">
        <li style="margin-bottom:6px;">Verify your identity - CNIC photos and a selfie.</li>
        <li style="margin-bottom:6px;">Tell us your monthly income and expenses.</li>
        <li>Our team verifies your address once and confirms your limit.</li>
    </ol>
    @include('emails.partials.button', ['url' => $applyUrl, 'label' => 'Start my application'])
@endsection
