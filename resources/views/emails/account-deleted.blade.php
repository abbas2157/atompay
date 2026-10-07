@extends('emails.layout')

@section('title', 'Your account has been deleted')
@section('preheader', 'Your AtomPay and AtomShop.pk account was deleted at your request.')

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#6C6C74;">Account deleted</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Your account has been deleted</h1>
    <p style="margin:0 0 12px;">Hi {{ $name }}, as you asked, we deleted your AtomPay account and the AtomShop.pk account that goes with it on <strong>{{ $when }}</strong> (Pakistan time).</p>
    <p style="margin:0 0 12px;">You have been signed out everywhere. Your identity documents and profile have been removed. Records of past orders and payments are kept only as long as the law requires, and are no longer linked to your name, email or mobile number.</p>
    <p style="margin:0;padding:14px 16px;background:rgba(240,84,101,0.08);border:1px solid rgba(240,84,101,0.30);border-radius:12px;font-size:14.5px;">
        <strong>Wasn't you?</strong> Contact AtomShop support straight away.
    </p>
@endsection
