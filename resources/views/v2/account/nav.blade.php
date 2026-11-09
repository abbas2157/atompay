{{-- Account sidebar. @include('v2.account.nav', ['active' => 'orders']) --}}
@php
    $me = auth()->user();
    $initials = collect(preg_split('/\s+/', trim((string) $me->name)))
        ->filter()->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    $items = [
        ['overview',     route('account.dashboard'),    'Overview',            '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>'],
        ['applications', route('account.applications'), 'My applications',     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 14h6M9 17.5h4"/>'],
        ['verification', route('account.verification'), 'KYC &amp; verification', '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>'],
        ['orders',       route('account.orders'),       'My orders',           '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>'],
        ['application',  route('account.application'),  'Update my details',   '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>'],
    ];
@endphp
<nav class="acct-side" aria-label="My account">
    <div class="acct-user">
        <span class="avatar" aria-hidden="true">{{ $initials ?: 'A' }}</span>
        <div>
            <b>{{ $me->shortName() }}</b>
            @if ($me->customer?->identifier)
                <small>Customer ID: {{ $me->customer->identifier }}</small>
            @endif
        </div>
    </div>

    @foreach ($items as [$key, $href, $label, $icon])
        <a href="{{ $href }}" @class(['on' => $active === $key]) @if ($active === $key) aria-current="page" @endif>
            <svg viewBox="0 0 24 24" aria-hidden="true">{!! $icon !!}</svg>{!! $label !!}
        </a>
    @endforeach

    @if ($me->isStaff())
        <div class="side-sep" role="presentation"></div>
        <a href="{{ route('staff.assessments.index') }}">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11l3 3 8-8"/><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h9"/></svg>Review queue
        </a>
    @endif

    <div class="side-sep" role="presentation"></div>
    <form method="POST" action="{{ route('logout') }}" data-busy>
        @csrf
        <button type="submit">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/></svg>Sign out
        </button>
    </form>
</nav>
