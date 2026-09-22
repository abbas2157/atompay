<section class="py-[60px] sm:py-[84px] bg-white border-y border-line" id="how">
    <div class="wrap">
        <x-section-head eyebrow="How a purchase actually happens" title="From product page to your last instalment." />
        <ol class="grid sm:grid-cols-2 lg:grid-cols-4 gap-[18px] list-none p-0 m-0">
            @foreach ($steps as $step)
                <li class="card p-5 relative">
                    <div class="font-disp text-[13px] font-bold w-7 h-7 rounded-lg bg-nucleus text-white grid place-items-center mb-4" aria-hidden="true">{{ $loop->iteration }}</div>
                    <h3 class="text-[16px] mb-1.5"><span class="sr-only">Step {{ $loop->iteration }}: </span>{{ $step["title"] }}</h3>
                    <p class="text-muted text-[13.8px]">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
