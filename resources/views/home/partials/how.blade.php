<section class="py-[60px] sm:py-[84px] bg-white border-y border-line" id="how">
    <div class="wrap">
        <x-section-head eyebrow="How a purchase actually happens" title="From product page to your last installment." />
        <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-[18px] list-none p-0 m-0">
            @foreach ($steps as $step)
                {{-- No step number: the <ol> already tells assistive tech these
                     are ordered, and the counters were asked to come off both
                     designs, not only v2. --}}
                <li class="card p-5 relative">
                    <h3 class="text-[16px] mb-1.5">{{ $step["title"] }}</h3>
                    <p class="text-muted text-[13.8px]">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
