{{-- The one-line rule, stated before anything else: 3 months, no advance, 0% markup. --}}
@php
    $support = config('atompay.api.support');
    $whatsapp = $support['whatsapp'] ?? $support['phone'] ?? null;
    // wa.me needs the international form; a link on the local 03XX number does
    // not resolve. Pakistan::whatsappUrl is what both designs use.
    $whatsappUrl = \App\Support\Pakistan::whatsappUrl($whatsapp, 'Hi AtomPay, I have a question.');
@endphp
<div class="top" style="background:var(--ink);color:#B9C2D6;font-size:13px;font-weight:600;padding:9px 0">
    <div class="wrap" style="display:flex;flex-wrap:wrap;gap:8px 20px;justify-content:space-between;align-items:center;padding:0 24px">
        <span>AtomPay is the smart limit for Atomshop.pk &mdash; <strong style="color:#fff">3 months, no advance, 0% markup</strong></span>
        <span class="top-links" style="display:flex;gap:18px;flex-wrap:wrap">
            @if ($whatsappUrl)
                <a href="{{ $whatsappUrl }}" target="_blank" rel="noopener"><span class="wa-dot wa-inline"></span>{{ $whatsapp }}</a>
            @endif
            <span>Delivery: Lahore only</span>
        </span>
    </div>
</div>
