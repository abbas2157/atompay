{{-- Sticky header. Below 1100px the links fold into the drawer (site.css). --}}
@php
    $nav = trim($__env->yieldContent('nav'));
    // Four links, deliberately. Your limit and Ecosystem are still sections on
    // the home page, reached from the hero chips and the footer - they were
    // crowding the bar without earning their place in it.
    $links = [
        ['how', route('home').'#how', 'How it works'],
        ['plan', route('home').'#plan', 'The plan'],
        ['products', route('products'), 'Eligible products'],
        ['faq', route('faq'), 'Help'],
    ];
@endphp
<header style="background:rgba(255,255,255,.88);border-bottom:1px solid var(--line);padding:0 24px;position:sticky;top:0;z-index:30">
    <nav aria-label="Main" style="max-width:1240px;margin:0 auto;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px 20px;padding:10px 0">
        <a href="{{ route('home') }}" aria-label="AtomPay, Pay in Steps" style="flex:none">
            <span class="brand">
                <img src="{{ asset('web/v2/img/atompay-mark.png') }}" alt="" width="44" height="44">
                <span><strong>ATOMPAY</strong><small>PAY IN STEPS</small></span>
            </span>
        </a>

        <div class="nav-links" style="display:flex;flex-wrap:wrap;align-items:center;gap:2px;font-size:15px;font-weight:600;justify-content:center">
            @foreach ($links as [$key, $href, $label])
                <a class="nav-l {{ $nav === $key ? 'on' : '' }}" href="{{ $href }}" style="padding:10px 12px;border-radius:999px">{{ $label }}</a>
            @endforeach
        </div>

        <div class="nav-cta" style="display:flex;gap:8px;align-items:center;flex:none">
            @auth
                <a class="btn btn-g btn-sm nav-login" href="{{ auth()->user()->isStaff() ? route('staff.assessments.index') : route('account.dashboard') }}">My AtomPay</a>
                <form method="POST" action="{{ route('logout') }}" data-busy style="display:inline">
                    @csrf
                    <button class="btn btn-p btn-sm" type="submit">Sign out</button>
                </form>
            @else
                <a class="btn btn-g btn-sm nav-login" href="{{ route('login') }}">Sign in</a>
                <a class="btn btn-p btn-sm" href="{{ route('register') }}">Get a limit</a>
            @endauth
            <button class="nav-burger" type="button" data-drawer-open aria-label="Open menu" aria-expanded="false">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/></svg>
            </button>
        </div>
    </nav>
</header>
