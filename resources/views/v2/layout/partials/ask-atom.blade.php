{{--
    Ask Atom: floating button + chat panel, the same component as the one on
    atomshop.pk (its classes are in site.css, copied verbatim). site.js wires it
    and posts each question to POST /ask-atom, answered by App\Services\AskAtomService.

    #atom-config carries the endpoint and the opening chips, so no URL is
    hardcoded in JavaScript.

    Not rendered on the staff review screens: they are internal.
--}}
@php
    $atomConfig = [
        'url' => route('ask-atom'),
        'csrf' => csrf_token(),
        'chips' => app(\App\Services\AskAtomService::class)->chips(),
        'greeting' => 'Hello. I can help with what AtomPay costs, how to get a limit, which products are eligible, '
            .'and when your payments fall due.',
    ];
    $atomSvg = '<svg viewBox="0 0 64 64" aria-hidden="true">'
        .'<line x1="32" y1="7" x2="32" y2="13" stroke="#14213D" stroke-width="2.6" stroke-linecap="round"/>'
        .'<circle cx="32" cy="5.5" r="3.6" fill="#F7A928"/>'
        .'<circle cx="8" cy="37" r="5.2" fill="#F05465"/><circle cx="56" cy="37" r="5.2" fill="#F05465"/>'
        .'<circle cx="32" cy="37" r="24" fill="#fff" stroke="#14213D" stroke-width="2.6"/>'
        .'<rect x="15" y="27" width="34" height="21" rx="10.5" fill="#14213D"/>'
        .'<circle cx="25" cy="36.5" r="3.6" fill="#8FE3FF"/><circle cx="39" cy="36.5" r="3.6" fill="#8FE3FF"/>'
        .'<circle cx="26.2" cy="35.3" r="1.1" fill="#fff"/><circle cx="40.2" cy="35.3" r="1.1" fill="#fff"/>'
        .'<path d="M27.5 42.2q4.5 3.4 9 0" stroke="#8FE3FF" stroke-width="2.3" fill="none" stroke-linecap="round"/></svg>';
@endphp

<script type="application/json" id="atom-config">{!! json_encode($atomConfig, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>

<button class="atom-fab" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="atom-panel">
    <span class="atom-av">{!! $atomSvg !!}</span>
    <span class="atom-fab-t">Ask Atom</span>
</button>

<div class="atom-panel" id="atom-panel" role="dialog" aria-label="Ask Atom, the AtomPay assistant" hidden>
    <div class="atom-head">
        <span class="atom-av">{!! $atomSvg !!}</span>
        <span class="atom-id">
            <strong>Atom</strong>
            <span><i class="atom-dot"></i>AtomPay assistant &middot; Online</span>
        </span>
        <button class="atom-close" type="button" aria-label="Close Ask Atom">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12"/><path d="M18 6 6 18"/></svg>
        </button>
    </div>

    <div class="atom-log" aria-live="polite"></div>
    <div class="atom-chips"></div>

    <form class="atom-form" data-atom-form novalidate>
        <label class="atom-sr" for="atom-input">Ask Atom</label>
        <input id="atom-input" type="text" placeholder="Ask anything&hellip;" autocomplete="off" maxlength="300">
        <button class="atom-send" type="submit">Send</button>
    </form>

    <div class="atom-note">Atom can make mistakes. Your limit and plan are confirmed by our team.</div>
</div>
