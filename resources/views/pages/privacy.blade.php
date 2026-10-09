{{--
    Privacy policy for the website and the mobile app (the store listings link here).
    Keep it true to the code: if a new field, document, provider or retention
    rule is added, update the matching section and the "Last updated" date.
--}}
@php
    $updated = '9 October 2026';
    $contacts = array_filter([
        'Email'    => $support['email'] ? '<a href="mailto:'.e($support['email']).'" class="underline">'.e($support['email']).'</a>' : null,
        'WhatsApp' => $support['whatsapp'] ? e($support['whatsapp']) : null,
        'Phone'    => $support['phone'] ? e($support['phone']) : null,
    ]);
    $sections = [
        'who'      => 'Who we are',
        'collect'  => 'What we collect',
        'use'      => 'How we use it',
        'share'    => 'Who we share it with',
        'keep'     => 'How long we keep it',
        'security' => 'How we protect it',
        'rights'   => 'Your choices and rights',
        'children' => 'Children',
        'changes'  => 'Changes to this policy',
        'contact'  => 'Contact us',
    ];
@endphp
<x-layouts.app
    title="Privacy policy"
    description="How AtomPay collects, uses, shares and protects your personal information when you check your instalment limit, apply, and repay AtomShop.pk purchases."
>
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <x-section-head eyebrow="Privacy policy" title="How we handle your information.">
                Last updated {{ $updated }}. This policy covers the {{ config('app.name') }} website and mobile app.
            </x-section-head>

            <nav class="max-w-[760px] mb-10 font-mono text-[12px] tracking-[.03em] flex flex-wrap gap-x-5 gap-y-2" aria-label="On this page">
                @foreach ($sections as $id => $label)
                    <a href="#{{ $id }}" class="text-muted hover:text-ink">{{ $label }}</a>
                @endforeach
            </nav>

            <div class="max-w-[760px] text-[15.5px] leading-relaxed space-y-10 [&_h3]:font-disp [&_h3]:text-[20px] [&_h3]:font-bold [&_h3]:mb-3 [&_p]:text-muted [&_li]:text-muted [&_p+p]:mt-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ul]:space-y-1.5 [&_ul]:mt-3 [&_strong]:text-ink">

                <div id="{{ $k = 'who' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>{{ config('app.name') }} is the instalment payment option for purchases made on
                        <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="underline">AtomShop.pk</a> in Pakistan.
                        It is run by AtomShop, and your {{ config('app.name') }} account is your AtomShop account.
                        In this policy, "we" and "us" means {{ config('app.name') }} and AtomShop.</p>
                </div>

                <div id="{{ $k = 'collect' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We only ask for what we need to open your account, decide your limit, and run your plan.</p>
                    <ul>
                        <li><strong>Account details:</strong> your name, mobile number, email address and password (stored only as a one-way hash).</li>
                        <li><strong>Identity (KYC):</strong> your CNIC number, date of birth, photos of the front and back of your CNIC, a selfie to match against it, and your residential address and city.</li>
                        <li><strong>Financial details:</strong> employment status, employer, source of income, monthly income and expenses, existing instalments, and the credit history you tell us about.</li>
                        <li><strong>Purchase and payment history:</strong> your AtomShop orders, instalment plans, due dates and whether payments were on time.</li>
                        <li><strong>Verification records:</strong> notes and signed forms from our staff when they check your address or identity.</li>
                        <li><strong>Plan estimates:</strong> the price, down payment and term you enter into the calculator.</li>
                        <li><strong>App and device data:</strong> the device name, platform and app version, and a push-notification token so we can send you reminders.</li>
                        <li><strong>Technical data:</strong> IP address, browser type and pages visited, used for security and, on the website, anonymous usage statistics.</li>
                    </ul>
                    <p>We do not access your contacts, call logs, SMS messages, photo gallery or location. The app only uses the camera when you choose to photograph your CNIC or take a selfie.</p>
                </div>

                <div id="{{ $k = 'use' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <ul>
                        <li>To create your account and confirm your mobile number and email with one-time codes.</li>
                        <li>To verify your identity and address, and to prevent fraud.</li>
                        <li>To assess whether you can afford an instalment plan and set your limit. This uses your declared income and obligations and your repayment history with AtomShop, and every decision is reviewed by our staff.</li>
                        <li>To run your plan: confirm orders, track payments, and send due-date reminders.</li>
                        <li>To send account messages, such as sign-in codes, password changes and decisions on your application.</li>
                        <li>To keep the service secure, investigate misuse, and meet our legal and regulatory obligations.</li>
                        <li>To understand how the website is used so we can improve it.</li>
                    </ul>
                    <p>We do not sell your personal information, and we do not use it for third-party advertising.</p>
                </div>

                <div id="{{ $k = 'share' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We share information only where it is needed to provide the service:</p>
                    <ul>
                        <li><strong>AtomShop staff</strong> who review applications, verify customers and handle repayments and recovery, with access limited by role.</li>
                        <li><strong>Meta (WhatsApp Business)</strong> to deliver one-time codes and messages to your mobile number.</li>
                        <li><strong>Email providers</strong> to deliver codes and account emails.</li>
                        <li><strong>Google Firebase Cloud Messaging</strong> to deliver push notifications to the app.</li>
                        <li><strong>Google Analytics</strong> on the website, with Google signals and ad personalisation turned off. It is never used in staff areas.</li>
                        <li><strong>Hosting providers</strong> that store our data on our behalf.</li>
                        <li><strong>Authorities</strong> when the law requires it, or to protect our customers, our business or the public from fraud or harm.</li>
                    </ul>
                    <p>These providers may process data outside Pakistan. They may only use it to provide their service to us.</p>
                </div>

                <div id="{{ $k = 'keep' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We keep your account and KYC information for as long as your account is open, and afterwards for as long as the law requires us to keep financial and identity records. We also keep it while any instalment is still owed or disputed.</p>
                    <p>One-time codes expire within minutes. Unfinished sign-ups are discarded after {{ config('atompay.signup.ttl_minutes') }} minutes. App sign-ins expire after {{ config('atompay.api.token_ttl_days') }} days.</p>
                </div>

                <div id="{{ $k = 'security' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <ul>
                        <li>All traffic to the website and app is encrypted with HTTPS.</li>
                        <li>CNIC photos, selfies and verification forms are kept in private storage. Only you and authorised staff can open them, and only after signing in.</li>
                        <li>Passwords are hashed, and sign-in and code requests are rate-limited.</li>
                        <li>You can see your active app sessions and sign out of any of them.</li>
                    </ul>
                    <p>No system is perfectly secure. If you think your account has been accessed without your permission, change your password and contact us straight away.</p>
                </div>

                <div id="{{ $k = 'rights' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <ul>
                        <li><strong>Access and correction:</strong> you can see your details in My {{ config('app.name') }}, and ask us to correct anything that is wrong.</li>
                        <li><strong>Notifications:</strong> you can turn off alert emails with the link in any alert email, and turn off push notifications in your phone's settings. We will still send messages you need for security or about money you owe.</li>
                        <li><strong>Deleting your account:</strong> you can delete your account yourself in the app or on our <a href="{{ route('account.delete.info') }}" class="underline">delete account page</a> once nothing is owed. Your identity details and documents are deleted, and the financial records we must keep by law are anonymised.</li>
                    </ul>
                </div>

                <div id="{{ $k = 'children' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>{{ config('app.name') }} is only for people aged {{ config('atompay.kyc.min_age') }} or over. We do not knowingly collect information from anyone younger.</p>
                </div>

                <div id="{{ $k = 'changes' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We may update this policy as the service changes. We will change the date at the top of this page, and tell you in the app or by email about any important change.</p>
                </div>

                <div id="{{ $k = 'contact' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>For privacy questions or requests, contact us:</p>
                    @if ($contacts)
                        <ul>
                            @foreach ($contacts as $label => $value)
                                <li><strong>{{ $label }}:</strong> {!! $value !!}</li>
                            @endforeach
                        </ul>
                    @else
                        <p>Through customer support on <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="underline">AtomShop.pk</a>.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
