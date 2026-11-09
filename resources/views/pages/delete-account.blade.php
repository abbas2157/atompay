{{--
    Public "delete your account" page - the link given to the Play Console and
    App Store. Keep the lists true to AccountDeletionService.
--}}
<x-layouts.app
    title="Delete your account"
    description="How to delete your AtomPay account and what happens to your data when you do."
>
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            @if (session('status'))
                <x-alert class="mb-8 max-w-[760px]">{{ session('status') }}</x-alert>
            @endif

            <x-section-head eyebrow="Delete your account" title="Delete your {{ config('app.name') }} account.">
                You can delete your {{ config('app.name') }} account, which is also your Atomshop.pk account, at any time once nothing is owed.
            </x-section-head>

            <div class="max-w-[760px] text-[15.5px] leading-relaxed space-y-10 [&_h3]:font-disp [&_h3]:text-[20px] [&_h3]:font-bold [&_h3]:mb-3 [&_p]:text-muted [&_li]:text-muted [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_ul]:mt-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-1.5 [&_ol]:mt-3 [&_strong]:text-ink">
                @include('pages.partials.delete-account-body')
            </div>
        </div>
    </section>
</x-layouts.app>
