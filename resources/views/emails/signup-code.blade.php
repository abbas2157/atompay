@extends('emails.layout')

@section('title', 'Confirm your email')
@section('preheader', "{$code} is your AtomPay sign-up code. It expires in {$minutes} minutes.")

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#6C6C74;">Create your account</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Confirm your email</h1>
    <p style="margin:0 0 20px;">Hi {{ $name }}, enter this code to confirm your email and finish creating your AtomPay / AtomShop account:</p>

    <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:34px;font-weight:700;letter-spacing:10px;background:#FBFAF7;border:1px solid rgba(20,21,26,0.10);border-radius:14px;padding:18px 0;text-align:center;">{{ $code }}</div>

    <p style="margin:20px 0 0;color:#6C6C74;font-size:14.5px;">It expires in {{ $minutes }} minutes. If you didn't try to sign up, ignore this email and no account will be created.</p>
@endsection
