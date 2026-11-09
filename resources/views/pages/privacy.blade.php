{{--
    Privacy policy for the website and the mobile app (the store listings link here).
    Keep it true to the code: if a new field, document, provider or retention
    rule is added, update the matching section and the "Last updated" date.
--}}
@php
    $updated = '9 October 2026';
    $contacts = array_filter([
        'Email'    => $support['email'] ? '<a href="mailto:'.e($support['email']).'" class="underline">'.e($support['email']).'</a>' : null,
        'WhatsApp' => $support['whatsapp'] ? e($support['whatsapp']) : null,
        'Phone'    => $support['phone'] ? e($support['phone']) : null,
    ]);
    $sections = [
        'who'      => 'Who we are',
        'collect'  => 'What we collect',
        'use'      => 'How we use it',
        'share'    => 'Who we share it with',
        'keep'     => 'How long we keep it',
        'security' => 'How we protect it',
        'rights'   => 'Your choices and rights',
        'children' => 'Children',
        'changes'  => 'Changes to this policy',
        'contact'  => 'Contact us',
    ];
@endphp
<x-layouts.app
    title="Privacy policy"
    description="How AtomPay collects, uses, shares and protects your personal information when you check your installment limit, apply, and repay Atomshop.pk purchases."
>
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <x-section-head eyebrow="Privacy policy" title="How we handle your information.">
                Last updated {{ $updated }}. This policy covers the {{ config('app.name') }} website and mobile app.
            </x-section-head>

            <nav class="max-w-[760px] mb-10 font-mono text-[12px] tracking-[.03em] flex flex-wrap gap-x-5 gap-y-2" aria-label="On this page">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" class="text-muted hover:text-ink">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="max-w-[760px] text-[15.5px] leading-relaxed space-y-10 [&_h3]:font-disp [&_h3]:text-[20px] [&_h3]:font-bold [&_h3]:mb-3 [&_p]:text-muted [&_li]:text-muted [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_ul]:mt-3 [&_strong]:text-ink">
                @include('pages.partials.privacy-body')
            </div>
        </div>
    </section>
</x-layouts.app>
