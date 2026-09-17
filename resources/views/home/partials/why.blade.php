@php
    $reasons = [
        ['tone' => 'bg-ok/[.13]',     'stroke' => '#1E9E6A', 'icon' => '<path d="M12 3l7 4v6c0 4-3 6-7 8-4-2-7-4-7-8V7z"/><path d="M9 12l2 2 4-4"/>',
         'title' => 'No separate account needed', 'text' => 'AtomPay uses your AtomShop account &mdash; sign in here with the same details you use at checkout.'],
        ['tone' => 'bg-coral/[.14]',  'stroke' => '#F05465', 'icon' => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 11h20M6 15h4"/>',
         'title' => 'Same product, same warranty', 'text' => 'Paying with AtomPay does not change what you receive &mdash; same item, same seller, same warranty as paying cash.'],
        ['tone' => 'bg-violet/[.14]', 'stroke' => '#62459B', 'icon' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
         'title' => 'Clear terms upfront', 'text' => 'Your down payment, monthly amount, and total cost are shown before you confirm the order.'],
    ];
@endphp
<section class="py-[60px] sm:py-[84px] bg-white border-y border-line">
    <div class="wrap">
        <x-section-head eyebrow="Why it exists" title="What AtomPay changes about buying on AtomShop." />
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @foreach ($reasons as $r)
                <div class="card p-6">
                    <div class="w-9 h-9 rounded-[10px] grid place-items-center mb-[15px] {{ $r['tone'] }}" aria-hidden="true">
                        <svg viewBox="0 0 24 24" class="w-[19px] h-[19px]" fill="none" stroke="{{ $r['stroke'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $r['icon'] !!}</svg>
                    </div>
                    <h3 class="text-[18px]">{{ $r['title'] }}</h3>
                    <p class="text-muted text-[14.5px] mt-2">{!! $r['text'] !!}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
