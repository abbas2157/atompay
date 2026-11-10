@extends(design_view('emails.layout'))

@section('title', 'Your password was changed')
@section('preheader', 'Your AtomPay / Atomshop password was just changed.')

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#6C6C74;">Security alert</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Your password was changed</h1>
    <p style="margin:0 0 12px;">Hi {{ $name }}, the password for your Atomshop account (which you also use for AtomPay) was changed on <strong>{{ $when }}</strong> (Pakistan time).</p>
    <p style="margin:0 0 12px;">{{ $keptThisDevice
        ? 'It was changed in the AtomPay app. That phone stays signed in; for your safety we signed you out on every other phone.'
        : 'For your safety we signed you out of the AtomPay app on every phone.' }}</p>
    <p style="margin:0;padding:14px 16px;background:rgba(240,84,101,0.08);border:1px solid rgba(240,84,101,0.30);border-radius:12px;font-size:14.5px;">
        <strong>Wasn't you?</strong> Reset your password straight away and contact Atomshop support.
    </p>
    @include(design_view('emails.partials.button'), ['url' => $resetUrl, 'label' => 'Reset my password'])
@endsection
