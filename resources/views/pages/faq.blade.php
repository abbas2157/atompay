@php
    $schema = [[
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => collect($faqs)->map(fn ($f) => [
            '@type' => 'Question', 'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ])->values()->all(),
    ]];
@endphp
<x-layouts.app
    title="Frequently asked questions"
    description="Answers about paying for AtomShop.pk purchases in instalments with AtomPay: eligibility, down payments, monthly amounts, and what happens after approval."
    :schema="$schema"
>
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <x-section-head eyebrow="Common questions" title="Before you check out with AtomPay." />
            <x-faq-list :faqs="$faqs" />
            <p class="mt-10 text-muted text-[15px]">
                Still unsure? <a href="{{ route('home') }}#calculator" class="underline">Estimate a plan</a> or
                <a href="{{ route('home') }}#assess" class="underline">check your limit</a>.
            </p>
        </div>
    </section>
</x-layouts.app>
