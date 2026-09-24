{{-- One line that tells the customer where they are in the process and what happens next. --}}
{{-- Wording lives in App\Services\StatusBanner so the mobile dashboard says the same thing. --}}
@php
    // Section 1 and 3 share one form on the web, so every action opens it.
    $state = [...app(\App\Services\StatusBanner::class)->for($profile, $latest), 'href' => route('account.application')];
    $tone = match ($state['tone']) {
        'done'    => ['box' => 'bg-ok/[.08] border-ok/30',       'icon' => 'bg-ok/[.18] text-ok'],
        'blocked' => ['box' => 'bg-coral/[.08] border-coral/30', 'icon' => 'bg-coral/[.18] text-coral'],
        default   => ['box' => 'bg-amber/[.08] border-amber/30', 'icon' => 'bg-amber/[.18] text-amber'],
    };
@endphp
<div class="rounded-[18px] px-6 py-5 flex items-center gap-4 mb-7 border flex-wrap {{ $tone['box'] }}">
    <div class="w-[38px] h-[38px] rounded-[10px] grid place-items-center shrink-0 {{ $tone['icon'] }}" aria-hidden="true">
        <svg viewBox="0 0 24 24" class="w-[19px] h-[19px]" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            @if ($state['tone'] === 'done')<path d="M4 12l5 5L20 6"/>
            @elseif ($state['tone'] === 'blocked')<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>
            @else<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@endif
        </svg>
    </div>
    <div class="flex-1 min-w-[200px]">
        <h4 class="text-[15px] mb-0.5">{{ $state['title'] }}</h4>
        <p class="text-[13.5px] text-muted">{{ $state['text'] }}</p>
    </div>
    <a href="{{ $state['href'] }}" class="btn btn-ghost btn-sm">{{ $state['cta'] }}</a>
</div>
