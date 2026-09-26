@extends('emails.layout')

@section('title', 'Your AtomPay code')
@section('preheader', "{$code} is your AtomPay password reset code. It expires in {$minutes} minutes.")

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#6C6C74;">Password reset</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Your one-time code</h1>
    <p style="margin:0 0 20px;">Hi {{ $name }}, use this code to reset your AtomPay / AtomShop password:</p>

    <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:34px;font-weight:700;letter-spacing:10px;background:#FBFAF7;border:1px solid rgba(20,21,26,0.10);border-radius:14px;padding:18px 0;text-align:center;">{{ $code }}</div>

    <p style="margin:20px 0 0;color:#6C6C74;font-size:14.5px;">It expires in {{ $minutes }} minutes and works once. If you didn't ask to reset your password, ignore this email - your password stays the same.</p>
@endsection
