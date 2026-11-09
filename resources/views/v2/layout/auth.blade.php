{{--
    Auth shell (v2), the same shape as Atomshop.pk's website/v2/auth/layout:
    the form on the left, a shared "one account" panel on the right that says
    why signing in here is signing in to the shop.

      @extends('v2.layout.auth')
      @section('auth-title', 'Sign in')
      @section('auth-eyebrow', 'My AtomPay')
      @section('auth-lead', '…')
      @section('auth-form') … @endsection
      @section('auth-foot') … @endsection    the line under the form

    Never indexed.
--}}
@extends('v2.layout.app')

@section('seo')
    <x-seo :title="trim($__env->yieldContent('auth-title', 'My AtomPay'))" :noindex="true" />
@endsection

@section('content')
    <section class="section">
        <div class="wrap">
            <div class="panel" style="background:#fff">
                <div class="auth-form">
                    <span class="eyebrow ap">@yield('auth-eyebrow', 'My AtomPay')</span>
                    <h1 class="h2" style="margin-top:10px">@yield('auth-title')</h1>
                    @hasSection('auth-lead')
                        <p class="body" style="margin-top:10px;max-width:46ch">@yield('auth-lead')</p>
                    @endif

                    @php
                        // Render the form first so we can tell which errors it
                        // already shows against a field. Without this the login
                        // page printed "Those details do not match an AtomShop
                        // account" twice: once here and once under the field.
                        $formHtml = $__env->yieldContent('auth-form');

                        $bannerError = null;
                        foreach ($errors->keys() as $key) {
                            if (! str_contains($formHtml, 'name="'.$key.'"')) {
                                $bannerError = $errors->first($key);
                                break;
                            }
                        }
                    @endphp

                    @if ($bannerError)
                        <div class="note rose" style="margin-top:20px">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
                            <span class="nbody">{{ $bannerError }}</span>
                        </div>
                    @endif

                    @if (session('status'))
                        <div class="note green" style="margin-top:20px">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12l5 5L20 6"/></svg>
                            <span class="nbody">{{ session('status') }}</span>
                        </div>
                    @endif

                    <div style="margin-top:22px">
                        {!! $formHtml !!}
                    </div>

                    @hasSection('auth-foot')
                        <p class="body" style="margin-top:22px">@yield('auth-foot')</p>
                    @endif
                </div>

                {{-- One account, both sites. The reason this page exists at all. --}}
                <aside class="auth-aside" aria-label="About your account">
                    <span class="brand" style="margin-bottom:20px">
                        <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="44" height="44" style="background:#fff;border-radius:11px;padding:4px;box-sizing:border-box">
                        <span><strong style="color:#fff">ATOMPAY</strong><small style="color:rgba(255,255,255,.6)">PAY IN STEPS</small></span>
                    </span>

                    <h2 class="h3" style="color:#fff">One account, both sites</h2>
                    <p style="color:rgba(255,255,255,.72);font-size:15px;line-height:1.6;margin-top:10px">
                        The same email or mobile number and password sign you in here, in the AtomPay app, and at
                        Atomshop.pk checkout. There is no second sign-up.
                    </p>

                    <ul class="auth-ticks">
                        <li>Check what is left of your limit</li>
                        <li>See every payment with the date it falls due</li>
                        <li>Apply once, then shop whenever you like</li>
                    </ul>

                    {{-- The same account signs in to the app, so the stores
                         belong on the panel that makes that point. --}}
                    <div class="auth-apps">
                        <span class="lbl">Get the app</span>
                        @include('v2.layout.partials.store-badges', ['dark' => true])
                    </div>

                    <div class="auth-rule">
                        <span>AtomPay plan</span>
                        <b>3 months &middot; no advance &middot; 0% markup</b>
                    </div>
                </aside>
            </div>
        </div>
    </section>
@endsection
