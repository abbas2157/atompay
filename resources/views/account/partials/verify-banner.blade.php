{{-- One line that tells the customer where they are in the process and what happens next. --}}
@php
    use App\Models\Enums\AssessmentStatus;
    use App\Models\Enums\VerificationStatus;

    $state = match (true) {
        $profile === null => ['tone' => 'pending', 'title' => 'Start your AtomPay application',
            'text' => 'Verify your identity and tell us about your income - it takes about five minutes.', 'cta' => 'Apply now', 'href' => route('account.application')],
        $latest?->status === AssessmentStatus::Rejected => ['tone' => 'blocked', 'title' => 'Application not approved',
            'text' => $latest->notes ?: 'We could not approve a limit this time. You can re-apply if your circumstances change.', 'cta' => 'Re-apply', 'href' => route('account.application')],
        $profile->verification_status === VerificationStatus::Rejected => ['tone' => 'blocked', 'title' => 'Verification unsuccessful',
            'text' => $profile->verification_notes ?: 'We could not verify your details. Please review and resubmit your application.', 'cta' => 'Update details', 'href' => route('account.application')],
        $latest?->isUsable() => ['tone' => 'done', 'title' => $latest->status === AssessmentStatus::Conditional ? 'Limit approved with conditions' : 'Verification complete',
            'text' => $latest->notes ?: 'Your limit below is confirmed and ready to use at AtomShop checkout.', 'cta' => 'Improve my limit', 'href' => route('account.application')],
        ! $profile->isVerified() => ['tone' => 'pending', 'title' => 'Address verification pending',
            'text' => 'Our representative will visit the address you gave to verify it and take your signature.', 'cta' => 'Update details', 'href' => route('account.application')],
        default => ['tone' => 'pending', 'title' => 'Risk assessment in progress',
            'text' => 'Your address is verified. We are reviewing your financial profile and will confirm your limit shortly.', 'cta' => 'Update income', 'href' => route('account.application')],
    };
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
