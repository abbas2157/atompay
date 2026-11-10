@extends(design_view('emails.layout'))

@php
    $rs = fn ($v) => 'Rs. '.number_format((int) $v);
    $tbd = 'To be confirmed';
    $row = 'padding:8px 0;border-top:1px solid #E3E8F1;vertical-align:top;';
@endphp

@section('title', 'Key Fact Statement')
@section('preheader', 'Order '.$kfs['order_number'].': amount, fees, APR and every payment date.')

@section('content')
    <p style="margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:1.8px;text-transform:uppercase;color:#656F88;">Key Fact Statement</p>
    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.2;">Order {{ $kfs['order_number'] }}</h1>
    <p style="margin:0 0 16px;">Hi {{ $name }}, here is the copy you asked for{{ $kfs['product'] ? ' of the terms for '.$kfs['product'] : '' }}.</p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;line-height:1.45;">
        <tr><td style="{{ $row }}color:#656F88;width:45%;">Lender</td><td style="{{ $row }}font-weight:700;">{{ $kfs['lender']['name'] ?? $tbd }}@if ($kfs['lender']['licence']) · SECP licence {{ $kfs['lender']['licence'] }}@endif</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Amount financed</td><td style="{{ $row }}font-weight:700;">{{ $rs($kfs['amount_financed']) }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Advance</td><td style="{{ $row }}font-weight:700;">{{ $rs($kfs['advance']) }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Markup</td><td style="{{ $row }}font-weight:700;">{{ rtrim(rtrim(number_format($kfs['markup_percent'], 2), '0'), '.') }}%</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Processing fee</td><td style="{{ $row }}font-weight:700;">{{ rtrim(rtrim(number_format($kfs['processing_fee_percent'], 2), '0'), '.') }}% · {{ $rs($kfs['processing_fee']) }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Annual percentage rate (APR)</td><td style="{{ $row }}font-weight:700;">{{ number_format($kfs['apr_percent'], 1) }}%</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Tenor</td><td style="{{ $row }}font-weight:700;">{{ $kfs['months'] }} months</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Instalments</td><td style="{{ $row }}font-weight:700;">
            @foreach ($kfs['instalments'] as $i)
                {{ $i['due_date'] ? \Illuminate\Support\Carbon::parse($i['due_date'])->format('j M Y') : 'Month '.$i['number'] }} · {{ $rs($i['amount']) }}<br>
            @endforeach
            @unless ($kfs['schedule_final'])<span style="font-weight:500;color:#656F88;">Dates are set when your order is delivered.</span>@endunless
        </td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Total you repay</td><td style="{{ $row }}font-weight:700;">{{ $rs($kfs['total_repayable']) }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Late payment</td><td style="{{ $row }}font-weight:700;">{{ $kfs['late_payment'] ?? $tbd }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Paying early</td><td style="{{ $row }}font-weight:700;">{{ $kfs['early_settlement'] ?? $tbd }}</td></tr>
        <tr><td style="{{ $row }}color:#656F88;">Complaints</td><td style="{{ $row }}">{{ $kfs['complaints'] }}</td></tr>
    </table>
@endsection
