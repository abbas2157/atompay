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
                You can delete your {{ config('app.name') }} account, which is also your AtomShop.pk account, at any time once nothing is owed.
            </x-section-head>

            <div class="max-w-[760px] text-[15.5px] leading-relaxed space-y-10 [&_h3]:font-disp [&_h3]:text-[20px] [&_h3]:font-bold [&_h3]:mb-3 [&_p]:text-muted [&_li]:text-muted [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_ul]:mt-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_ol]:space-y-1.5 [&_ol]:mt-3 [&_strong]:text-ink">
                <div>
                    <h3>How to delete it</h3>
                    <p><strong>In the {{ config('app.name') }} app:</strong> choose <strong>Delete account</strong> in your profile and enter your password.</p>
                    <p class="mt-3"><strong>On this website:</strong></p>
                    <ol>
                        <li>Sign in with the phone or email and password you use on AtomShop.pk.</li>
                        <li>Enter your password again and confirm.</li>
                    </ol>
                    <a href="{{ route('account.delete') }}" class="btn btn-primary mt-5">
                        {{ auth()->check() ? 'Continue to delete my account' : 'Sign in to delete my account' }} <span aria-hidden="true">&rarr;</span>
                    </a>
                    <p class="mt-4 text-[14px]">Forgot your password? <a href="{{ route('password.request') }}" class="underline text-ink">Reset it first</a>.</p>
                </div>

                <div>
                    <h3>Before you can delete</h3>
                    <p>Every instalment plan must be fully repaid, and no order can be waiting for approval. If something is still owed, we will tell you when you try.</p>
                </div>

                <div>
                    <h3>What is deleted straight away</h3>
                    <ul>
                        <li>Your sign-ins on every phone and browser, and your push-notification tokens.</li>
                        <li>Your identity details: CNIC number, date of birth, address, and the CNIC photos and selfie.</li>
                        <li>Your notifications inbox, preferences, and any sign-up or password-reset codes.</li>
                        <li>Your income and employment answers, if you never placed an order.</li>
                        <li>Your name, email, mobile number and password on the AtomShop account. The account is closed, and the email and number can be used to sign up again.</li>
                    </ul>
                </div>

                <div>
                    <h3>What we keep, and why</h3>
                    <p>We must keep financial records by law. Your past orders, instalment schedules and payments, and the credit decision behind any order (without your employer's name), are kept, linked only to an anonymous closed account. Nothing left in them names you.</p>
                </div>

                <div>
                    <h3>Need help?</h3>
                    <p>See our <a href="{{ route('privacy') }}" class="underline">privacy policy</a>, or contact us if you cannot sign in.</p>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
