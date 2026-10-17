{{--
    Native <details> so the answers are in the HTML for crawlers; no JS needed.
    `searchable` adds a filter box (hidden without JS, where it couldn't work).
--}}
@props(['faqs', 'searchable' => false])
<div {{ $attributes->merge(['class' => 'max-w-[760px]']) }} @if ($searchable) x-data="faqSearch" @endif>
    @if ($searchable)
        <div class="mb-4" x-cloak>
            <label for="faq-search" class="sr-only">Search the questions</label>
            <input id="faq-search" type="search" class="input bg-white" placeholder="Search questions, e.g. down payment"
                   x-model="query" @input.debounce.150ms="filter()" autocomplete="off">
            <p class="text-muted text-[13.5px] mt-3" x-show="matches !== null" aria-live="polite"
               x-text="matches === 0 ? 'No questions match that. Try a different word.' : matches + (matches === 1 ? ' question matches' : ' questions match')"></p>
        </div>
    @endif
    @foreach ($faqs as $faq)
        <details class="border-b border-line py-[22px]" @if ($loop->first) open @endif
                 @if ($searchable) data-search="{{ Str::lower($faq['q'].' '.$faq['a']) }}" @endif>
            <summary class="cursor-pointer font-disp text-[17px] font-bold flex justify-between items-center gap-4 list-none">
                {{ $faq['q'] }}
                <span class="plus font-mono text-[20px] text-muted shrink-0 transition-transform" aria-hidden="true">+</span>
            </summary>
            <p class="text-muted mt-3 text-[15px]">{{ $faq['a'] }}</p>
        </details>
    @endforeach
</div>
