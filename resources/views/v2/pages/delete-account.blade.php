{{--
    Public "delete your account" page (v2) - the link given to the Play Console
    and the App Store, so it must work signed out. The text itself is shared
    with the v1 view via pages/partials/delete-account-body.
--}}
@extends('v2.layout.app')

@section('seo')
    <x-seo
        title="Delete your account"
        description="How to delete your AtomPay account and what happens to your data when you do."
        :canonical="route('account.delete.info')"
    />
@endsection

@section('content')
    <div class="page-hero">
        <div class="wrap">
            <p class="crumbs"><a href="{{ route('home') }}">Home</a> / <span>Delete your account</span></p>
            <span class="eyebrow ap">Delete your account</span>
            <h1 class="h1">Delete your {{ config('app.name') }} account.</h1>
            <p class="lead" style="margin-top:12px">
                You can delete your {{ config('app.name') }} account, which is also your Atomshop.pk account,
                at any time once nothing is owed.
            </p>
        </div>
    </div>

    <section class="section">
        <div class="wrap" style="max-width:860px">
            @if (session('status'))
                <div class="note green" style="margin-bottom:28px">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l5 5L20 6"/></svg>
                    <span class="nbody">{{ session('status') }}</span>
                </div>
            @endif

            <div class="prose">
                @include('pages.partials.delete-account-body')
            </div>
        </div>
    </section>
@endsection
