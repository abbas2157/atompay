{{-- The core process: KYC → Address → Income → Risk → Limit → Purchase. $stages from ProcessTracker. --}}
@props(['stages'])
<ol {{ $attributes->merge(['class' => 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 list-none p-0 m-0']) }}>
    @foreach ($stages as $stage)
        @php
            $tone = match ($stage['state']) {
                'done'    => ['ring' => 'border-ok/40 bg-ok/[.06]',      'badge' => 'bg-ok text-white',           'icon' => '✓'],
                'current' => ['ring' => 'border-amber/50 bg-amber/[.08]', 'badge' => 'bg-amber text-nucleus',     'icon' => $loop->iteration],
                'blocked' => ['ring' => 'border-coral/40 bg-coral/[.06]', 'badge' => 'bg-coral text-white',        'icon' => '!'],
                default   => ['ring' => 'border-line bg-white',           'badge' => 'bg-line2 text-muted',        'icon' => $loop->iteration],
            };
        @endphp
        <li class="rounded-2xl border px-3.5 py-3 {{ $tone['ring'] }}" aria-current="{{ $stage['state'] === 'current' ? 'step' : 'false' }}">
            <span class="font-disp text-[11px] font-bold w-6 h-6 rounded-md grid place-items-center mb-2 {{ $tone['badge'] }}" aria-hidden="true">{{ $tone['icon'] }}</span>
            <b class="block text-[13px] font-semibold leading-tight">{{ $stage['title'] }}</b>
            <span class="block text-[11.5px] text-muted mt-1 leading-snug">{{ $stage['hint'] }}</span>
        </li>
    @endforeach
</ol>
