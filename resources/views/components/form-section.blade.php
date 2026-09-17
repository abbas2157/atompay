{{-- Numbered section of the KYC form; the number is the section number from the AtomPay assessment form. --}}
@props(['number', 'title', 'intro' => null])
<section {{ $attributes->merge(['class' => 'card p-6 sm:p-8']) }}>
    <div class="flex items-start gap-3.5 mb-6">
        <span class="font-disp text-[13px] font-bold w-7 h-7 rounded-lg bg-nucleus text-white grid place-items-center shrink-0" aria-hidden="true">{{ $number }}</span>
        <div>
            <h2 class="text-[20px]">{{ $title }}</h2>
            @if ($intro)<p class="text-muted text-[14px] mt-1">{{ $intro }}</p>@endif
        </div>
    </div>
    {{ $slot }}
</section>
