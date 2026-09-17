<section class="border-b border-line overflow-hidden relative">
    <div class="wrap grid lg:grid-cols-[1.05fr_.95fr] gap-2 lg:gap-10 items-center pt-10 pb-13 lg:py-16">
        <div>
            <span class="inline-flex items-center gap-2 font-mono text-[11px] tracking-[.08em] px-3.5 py-2 rounded-full bg-white border border-line mb-6">
                <span class="flex items-center gap-1.5" aria-hidden="true">
                    <span class="w-2 h-2 rounded-full bg-nucleus"></span>
                    <span class="w-2 h-2 rounded-full spectrum"></span>
                </span>
                AtomShop.pk &times; AtomPay
            </span>
            <h1 class="text-[clamp(36px,5vw,54px)] max-w-[14ch]">Shop on AtomShop. Pay for it in easy monthly steps.</h1>
            <p class="text-[clamp(16px,1.7vw,18.5px)] text-[#3a3b42] max-w-[42ch] mt-5">
                AtomPay is the instalment payment option built into AtomShop.pk checkout. Pick any eligible product on AtomShop, choose AtomPay at checkout, and spread the cost over a few months instead of paying it all at once.
            </p>
            <div class="flex flex-wrap gap-3.5 mt-8">
                <a class="btn btn-primary" href="{{ $shopUrl }}" target="_blank" rel="noopener">Browse AtomShop.pk <span aria-hidden="true">&rarr;</span></a>
                <a class="btn btn-ghost" href="#how">See how it works</a>
            </div>
        </div>
        <div class="flex items-center justify-center min-h-[300px] lg:min-h-[380px] order-first lg:order-none" aria-hidden="true">
            <div class="atom">
                <div class="halo"></div>
                <div class="orbit o1"><span class="electron e1"></span></div>
                <div class="orbit o2"><span class="electron e2"></span></div>
                <div class="absolute inset-0 m-auto w-[44%] h-[44%] rounded-[22px] bg-white border border-line grid place-items-center shadow-[0_16px_36px_rgba(20,21,26,.10)] text-center p-2.5">
                    <div>
                        <b class="font-disp text-[14px] block">Your AtomShop order</b>
                        <i class="font-mono not-italic text-[9.5px] tracking-[.1em] text-muted block mt-1.5">PAID IN INSTALMENTS</i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
