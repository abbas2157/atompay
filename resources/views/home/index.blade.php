@php
    $description = 'AtomPay is the instalment payment option at AtomShop.pk checkout. Estimate a monthly plan, check your approved limit, and split any eligible purchase into easy monthly payments.';

    // Structured data: who we are, the 4-step process, and the FAQ.
    $schema = [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => config('app.name'),
            'url'      => route('home'),
            'logo'     => asset('favicon.svg'),
            'address'  => ['@type' => 'PostalAddress', 'addressCountry' => 'PK'],
            'parentOrganization' => ['@type' => 'Organization', 'name' => 'AtomShop', 'url' => $shopUrl],
        ],
        [
            '@context' => 'https://schema.org',
            '@type'    => 'HowTo',
            'name'     => 'How to buy on AtomShop with AtomPay instalments',
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
    title="Buy on AtomShop.pk, pay in easy monthly instalments"
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
