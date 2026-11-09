{{--
    Privacy policy (v2). The text itself lives in pages/partials/privacy-body,
    shared with the v1 view - this file supplies only the v2 chrome, so the
    legal wording exists exactly once.
--}}
@extends('v2.layout.app')

@section('seo')
    <x-seo
        title="Privacy policy"
        description="How AtomPay collects, uses, shares and protects your personal information when you check your installment limit, apply, and repay Atomshop.pk purchases."
        :canonical="route('privacy')"
    />
@endsection

@section('content')
    @php
        $updated = '9 October 2026';
        $contacts = array_filter([
            'Email' => $support['email'] ? '<a href="mailto:'.e($support['email']).'" class="link">'.e($support['email']).'</a>' : null,
            'WhatsApp' => $support['whatsapp'] ? e($support['whatsapp']) : null,
            'Phone' => $support['phone'] ? e($support['phone']) : null,
        ]);
        $sections = [
            'who' => 'Who we are',
            'collect' => 'What we collect',
            'use' => 'How we use it',
            'share' => 'Who we share it with',
            'keep' => 'How long we keep it',
            'security' => 'How we protect it',
            'rights' => 'Your choices and rights',
            'children' => 'Children',
            'changes' => 'Changes to this policy',
            'contact' => 'Contact us',
        ];
    @endphp

    <div class="page-hero">
        <div class="wrap">
            <p class="crumbs"><a href="{{ route('home') }}">Home</a> / <span>Privacy policy</span></p>
            <span class="eyebrow ap">Privacy policy</span>
            <h1 class="h1">How we handle your information.</h1>
            <p class="lead" style="margin-top:12px">
                Last updated {{ $updated }}. This policy covers the {{ config('app.name') }} website and mobile app.
            </p>
        </div>
    </div>

    <section class="section">
        <div class="wrap" style="display:flex;flex-wrap:wrap;gap:40px;align-items:flex-start">
            <nav class="card" style="flex:1 1 230px;max-width:280px;position:sticky;top:96px;padding:16px" aria-label="On this page">
                <span class="eyebrow" style="display:block;margin-bottom:10px">On this page</span>
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" style="display:block;padding:7px 0;font-size:14.5px;font-weight:600;color:var(--body)">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="prose" style="flex:999 1 480px;min-width:0;scroll-margin-top:96px">
                @include('pages.partials.privacy-body')
            </div>
        </div>
    </section>
@endsection
