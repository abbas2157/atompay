@extends('emails.layout')

@section('title', $title)
@section('preheader', $body)

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:{{ $toneColor }};">{{ $eyebrow }}</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">{{ $title }}</h1>
    <p style="margin:0 0 4px;">Hi {{ $name }},</p>
    <p style="margin:0;">{{ $body }}</p>
    @include('emails.partials.button', ['url' => $actionUrl, 'label' => $actionLabel])
@endsection

@section('footer')
    You're getting this because AtomPay alerts are on for your account.
    <a href="{{ $unsubscribeUrl }}" style="color:#6C6C74;">Turn off alert emails</a>.<br>
@endsection
