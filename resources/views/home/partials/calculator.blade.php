{{--
    Client-side estimate driven by the same numbers the server uses
    ($calculator = InstalmentQuoteService::clientConfig()). $sample is the
    server-rendered default so the figures are in the HTML before JS runs.
--}}
<section class="py-[60px] sm:py-[84px]" id="calculator">
    <div class="wrap">
        <div
            class="card-dark px-[22px] py-8 sm:px-10 sm:py-11"
            x-data="planCalculator(@js($calculator), @js(['price' => $sample->price, 'months' => $sample->months]))"
        >
            <div class="glow w-[480px] h-[480px] -right-[140px] -top-[180px]" aria-hidden="true"></div>
            <div class="relative z-[2]">
                <x-section-head eyebrow="Estimate a plan" title="See roughly what a monthly payment looks like." :dark="true" class="mb-0 max-w-none">
                    This is a close estimate to plan your budget &mdash; your exact plan is confirmed at AtomShop checkout once you pick a real product.
                </x-section-head>

                <div class="grid md:grid-cols-2 gap-7 mt-8 items-start">
                    <div>
                        <div class="mb-[18px]">
                            <label for="calc-price" class="field-label text-white/50">Product price (PKR)</label>
                            <input type="range" id="calc-price" x-model.number="price"
                                   min="{{ $calculator['price']['min'] }}" max="{{ $calculator['price']['max'] }}" step="{{ $calculator['price']['step'] }}">
                            <output for="calc-price" class="font-disp text-[19px] mt-1.5 block" x-text="fmt(price)">@pkr($sample->price)</output>
                        </div>
                        <div class="mb-[18px]">
                            <label for="calc-months" class="field-label text-white/50">Instalment term</label>
                            <input type="range" id="calc-months" x-model.number="tenureIndex" min="0" max="{{ count($calculator['tenures']) - 1 }}" step="1">
                            <output for="calc-months" class="font-disp text-[19px] mt-1.5 block" x-text="monthsLabel()">{{ $sample->months }} months</output>
                        </div>
                    </div>

                    <div class="p-5 rounded-2xl bg-white/[.06] border border-white/[.13]">
                        <div class="font-disp font-extrabold text-[36px] tracking-[-.03em] spectrum-text" x-text="fmt(monthly)">@pkr($sample->monthly)</div>
                        <div class="font-mono text-[10.5px] tracking-[.12em] uppercase text-white/50 mt-1.5">Estimated per month</div>
                        <div class="qline"><span>Down payment (min. {{ (int) ($calculator['minAdvance'] * 100) }}%)</span><b x-text="fmt(advance)">@pkr($sample->advance)</b></div>
                        <div class="qline"><span>Service charge</span><b x-text="fmt(markup)">@pkr($sample->markup)</b></div>
                        <div class="qline"><span>Total payable</span><b x-text="fmt(total)">@pkr($sample->total)</b></div>
                    </div>
                </div>

                <p class="text-[11.5px] text-white/40 mt-4 leading-normal">
                    Estimate uses the minimum {{ (int) ($calculator['minAdvance'] * 100) }}% down payment and AtomShop&rsquo;s current service rate of {{ $calculator['perMonth'] }}% per month on the financed amount. You can pay up to {{ (int) ($calculator['maxAdvance'] * 100) }}% upfront at checkout to lower the monthly figure. Final pricing depends on the product and your approval.
                </p>
            </div>
        </div>
    </div>
</section>
