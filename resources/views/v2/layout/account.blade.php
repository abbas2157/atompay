{{--
    Signed-in shell, built on Atomshop.pk v2's account layout: page hero,
    sticky sidebar, .acct-main column. A page sets:

      @extends('v2.layout.account')
      @section('acct-nav', 'orders')      the sidebar's active item
      @section('acct-title', 'My orders')
      @section('acct-lead', '…')
      @section('acct') … @endsection      the cards

    Never indexed. The component classes used by the pages (.card-head, .kv,
    .bar, .note, .track, details.order, .doc) are in public/web/v2/css/atompay.css,
    not pushed from here, so more than one page can use them.
--}}
@extends('v2.layout.app')

@section('seo')
    <x-seo :title="trim($__env->yieldContent('acct-title', 'My AtomPay'))" :noindex="true" />
@endsection

@section('content')
    <div class="page-hero">
        <div class="wrap">
            <p class="crumbs">
                <a href="{{ route('home') }}">Home</a> /
                <a href="{{ route('account.dashboard') }}">My AtomPay</a> /
                <span>@yield('acct-title', 'My AtomPay')</span>
            </p>
            <span class="eyebrow ap">@yield('acct-eyebrow', 'My AtomPay')</span>
            <h1 class="h1">@yield('acct-title', 'My AtomPay')</h1>
            @hasSection('acct-lead')
                <p class="lead" style="margin-top:12px">@yield('acct-lead')</p>
            @endif
        </div>
    </div>

    <div class="section" style="padding-top:40px">
        <div class="wrap acct">
            @include('v2.account.nav', ['active' => trim($__env->yieldContent('acct-nav'))])

            <div class="acct-main">
                @yield('acct')
            </div>
        </div>
    </div>
@endsection
