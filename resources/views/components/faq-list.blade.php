{{-- Native <details> so the answers are in the HTML for crawlers; no JS needed. --}}
@props(['faqs'])
<div {{ $attributes->merge(['class' => 'max-w-[760px]']) }}>
    @foreach ($faqs as $faq)
        <details class="border-b border-line py-[22px]" @if ($loop->first) open @endif>
            <summary class="cursor-pointer font-disp text-[17px] font-bold flex justify-between items-center gap-4 list-none">
                {{ $faq['q'] }}
                <span class="plus font-mono text-[20px] text-muted shrink-0 transition-transform" aria-hidden="true">+</span>
            </summary>
            <p class="text-muted mt-3 text-[15px]">{{ $faq['a'] }}</p>
        </details>
    @endforeach
</div>
