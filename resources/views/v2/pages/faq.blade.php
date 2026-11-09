@extends('v2.layout.app')

@section('nav', 'faq')

@section('seo')
    @php
        $schema = [[
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => collect($faqs)->map(fn ($faq) => [
                '@type' => 'Question', 'name' => $faq['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a']],
            ])->values()->all(),
        ]];
    @endphp
    <x-seo
        title="Frequently asked questions"
        description="Answers about paying for Atomshop.pk purchases with an AtomPay limit: eligibility, the three-month term, what it costs, and what happens after approval."
        :schema="$schema"
        :canonical="route('faq')"
    />
@endsection

@section('content')
    <div class="page-hero">
        <div class="wrap">
            <p class="crumbs"><a href="{{ route('home') }}">Home</a> / <span>FAQs</span></p>
            <span class="eyebrow ap">Common questions</span>
            <h1 class="h1">Before you check out with AtomPay.</h1>
        </div>
    </div>

    <section class="section">
        <div class="wrap" style="max-width:860px">
            {{-- Hidden until site.js runs, because without JS it could not filter. --}}
            <label class="field" for="faq-search" data-faq-search="#faq-list" hidden>
                <span class="sr-only">Search the questions</span>
                <input id="faq-search" type="search" placeholder="Search questions, e.g. advance payment" autocomplete="off">
                <span class="hint" data-faq-count aria-live="polite"></span>
            </label>

            <div id="faq-list" style="margin-top:18px">
                @foreach ($faqs as $faq)
                    <div @class(['acc', 'open' => $loop->first]) data-search="{{ Str::lower($faq['q'].' '.$faq['a']) }}">
                        <button type="button">{{ $faq['q'] }}</button>
                        <div class="acc-body">{{ $faq['a'] }}</div>
                    </div>
                @endforeach
            </div>

            <div class="note blue" style="margin-top:28px">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg>
                <span class="nbody">
                    <b>Still unsure?</b>
                    <button class="link" type="button" data-ask="" style="background:none;border:0;padding:0;cursor:pointer;font:inherit">Ask Atom</button>,
                    <a class="link" href="{{ route('home') }}#plan">see what a plan costs</a>, or
                    <a class="link" href="{{ auth()->check() ? route('account.applications') : route('register') }}">check your limit</a>.
                </span>
            </div>
        </div>
    </section>
@endsection
