@php
    $description = 'AtomPay is the installment payment option at Atomshop.pk checkout. Estimate a monthly plan, check your approved limit, and split any eligible purchase into easy monthly payments.';

    // Structured data: who we are, the 4-step process, and the FAQ.
    $schema = [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => config('app.name'),
            'url'      => route('home'),
            // schema.org wants the real logo, not the tab icon - same asset the
            // v2 home reports, so both designs describe the brand identically.
            'logo'     => asset('web/v2/img/atompay-logo.png'),
            'address'  => ['@type' => 'PostalAddress', 'addressCountry' => 'PK'],
            'parentOrganization' => ['@type' => 'Organization', 'name' => 'Atomshop', 'url' => $shopUrl],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => 'How to buy on Atomshop with AtomPay installments',
            'step'     => collect($steps)->map(fn ($s, $i) => [
                '@type' => 'HowToStep', 'position' => $i + 1, 'name' => $s['title'], 'text' => $s['text'],
            ])->values()->all(),
        ],
        [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($f) => [
                '@type' => 'Question', 'name' => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ])->values()->all(),
        ],
    ];
@endphp
<x-layouts.app
    title="Buy on Atomshop.pk, pay in easy monthly installments"
    :description="$description"
    :schema="$schema"
    :canonical="route('home')"
>
    @include('home.partials.hero')
    @include('home.partials.relation')
    @include('home.partials.how')
    @include('home.partials.calculator')
    @include('home.partials.why')
    @include('home.partials.assess')

    <section class="py-[60px] sm:py-[84px]" id="faq">
        <div class="wrap">
            <x-section-head eyebrow="Common questions" title="Before you check out with AtomPay." />
            <x-faq-list :faqs="$faqs" />
        </div>
    </section>
</x-layouts.app>
