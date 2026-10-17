{{--
    Floating "chat on WhatsApp" link to the support number
    (ATOMPAY_SUPPORT_WHATSAPP). Renders nothing until that is set.
    wa.me wants the number in international form with digits only, so a
    local 03XX number is rewritten to 923XX.
--}}
@php
    $digits = preg_replace('/\D/', '', (string) config('atompay.api.support.whatsapp'));
    if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
    if (str_starts_with($digits, '03')) $digits = '92'.substr($digits, 1);
    $message = 'Hi AtomPay, I have a question.';
@endphp
@if (strlen($digits) >= 10)
    <a href="https://wa.me/{{ $digits }}?text={{ rawurlencode($message) }}" target="_blank" rel="noopener"
       {{ $attributes->merge(['class' => 'fab bg-[#25D366] text-white hover:bg-[#1ebe5b] hover:scale-105']) }}
       aria-label="Chat with AtomPay on WhatsApp">
        <svg viewBox="0 0 24 24" class="w-6 h-6" fill="currentColor" aria-hidden="true"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1-.8 1-.9 1.2-.3.2-.6.1a8 8 0 0 1-2.4-1.5 9 9 0 0 1-1.6-2c-.2-.3 0-.5.1-.6l.4-.5.3-.5v-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6a1.1 1.1 0 0 0-.8.4 3.4 3.4 0 0 0-1 2.5 5.9 5.9 0 0 0 1.2 3.1 13.5 13.5 0 0 0 5.2 4.6c1.9.8 2.7.9 3.6.7a3.1 3.1 0 0 0 2-1.4 2.5 2.5 0 0 0 .2-1.4c-.1-.1-.3-.2-.6-.3zM12 21.8a9.8 9.8 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.8 9.8 0 1 1 12 21.8zm8.4-18.3A11.8 11.8 0 0 0 1.8 17.8L.1 24l6.4-1.7a11.8 11.8 0 0 0 5.6 1.4A11.8 11.8 0 0 0 20.4 3.5z"/></svg>
    </a>
@endif
