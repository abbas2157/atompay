{{--
    The privacy policy's text itself, shared by both designs. The markup inside
    is semantic and class-free on purpose - <h3>, <p>, <ul>, <strong> - so the
    v1 view wraps it in its Tailwind prose container and the v2 view wraps it in
    .prose, and the legal text exists exactly once.

    If a new field, document, provider or retention rule is added, update the
    matching section here and the "Last updated" date in BOTH views.
    $sections and $contacts come from the including view.

    Facts only the business can confirm appear once set, and never as a
    guess: the company name, address and data hosting come from
    CompanySettings (Atomshop's admin panel, falling back to .env); record
    retention and credit bureau from config('atompay.privacy').
    `atompay:deploy-check` lists the missing ones.

    Atomshop sells on instalments itself (merchant credit). There is no
    separate lender and no licence - do not add one here.
--}}
@php
    $company = app(\App\Services\CompanySettings::class)->all();
    $privacy = config('atompay.privacy');
    $retentionYears = (int) ($privacy['record_retention_years'] ?? 0);
@endphp

                <div id="{{ $k = 'who' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>{{ config('app.name') }} is the installment payment option for purchases made on
                        <a href="{{ $shopLink }}" target="_blank" rel="noopener" class="underline">Atomshop.pk</a> in Pakistan.
                        It is run by Atomshop, and your {{ config('app.name') }} account is your Atomshop account.
                        In this policy, "we" and "us" mean {{ config('app.name') }} and Atomshop.</p>
                    @if ($company['company_name'])
                        <p>{{ config('app.name') }} is provided by <strong>{{ $company['company_name'] }}</strong>{{ $company['registered_address'] ? ', '.$company['registered_address'] : '' }}, the company that runs Atomshop. It sells to you on instalments itself - there is no bank or other lender in between - and it is responsible for your personal information.</p>
                    @endif
                </div>

                <div id="{{ $k = 'collect' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We only ask for what we need to open your account, decide your limit, and run your plan.</p>
                    <ul>
                        <li><strong>Account details:</strong> your name, mobile number, email address and password (stored only as a one-way hash).</li>
                        <li><strong>Identity (KYC):</strong> your CNIC number, date of birth, photos of the front and back of your CNIC, a selfie to match against it, and your residential address, city and area.</li>
                        <li><strong>Financial details:</strong> employment status, employer, source of income, monthly income and expenses, existing installments, and the credit history you tell us about.</li>
                        <li><strong>Purchase-limit assessment:</strong> the risk score, approved limit, maximum installment and the reasons for our decision.</li>
                        <li><strong>Purchase and payment history:</strong> your Atomshop orders, installment plans, due dates and whether payments were on time.</li>
                        <li><strong>Verification records:</strong> notes and signed forms from our staff when they check your address or identity.</li>
                        <li><strong>Plan estimates:</strong> the price, down payment and term you enter into the calculator.</li>
                        <li><strong>App and device data:</strong> the device name (shown in your list of signed-in devices), platform and app version, and a push-notification token so we can send you reminders.</li>
                        <li><strong>Ask Atom:</strong> the questions you type or speak to Ask Atom, the in-app assistant. When you speak, your phone turns your voice into text and the app sends us only the text, never the recording. Questions are answered and not stored.</li>
                        <li><strong>Complaints:</strong> what you tell us when you raise a complaint, and our replies.</li>
                        <li><strong>Technical data:</strong> IP address, browser type and pages visited, used for security and, on the website, anonymous usage statistics.</li>
                    </ul>
                    <p>We do not access your contacts, call logs, SMS messages, photo gallery or location. The app only uses the camera when you choose to photograph your CNIC or take a selfie.</p>
                </div>

                <div id="{{ $k = 'use' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>Each use below names the reason the law allows it (its legal basis).</p>
                    <ul>
                        <li><strong>Identity verification:</strong> to create your account, confirm your mobile number and email with one-time codes, and verify your identity and address. <em>Basis: to enter into our agreement with you, and our legal duty to know our customers.</em></li>
                        <li><strong>Credit assessment:</strong> to decide whether you can afford an installment plan and set your limit, from your declared income and obligations and your repayment history with Atomshop. Every decision is reviewed by our staff. <em>Basis: to enter into our agreement with you, and our legal duty to lend responsibly.</em></li>
                        <li><strong>Managing your installment plans:</strong> to confirm orders, record payments and keep your schedule. <em>Basis: to perform our agreement with you.</em></li>
                        <li><strong>Notifications:</strong> due-date reminders, sign-in codes, password changes and decisions on your application, by push, email or WhatsApp. <em>Basis: to perform our agreement with you; push notifications only with your permission on your phone.</em></li>
                        <li><strong>Answering your questions:</strong> Ask Atom answers from your own limit and plans, and complaints are handled by our staff. <em>Basis: to perform our agreement with you.</em></li>
                        <li><strong>Fraud prevention and security:</strong> to keep accounts safe and investigate misuse. <em>Basis: our legitimate interest in protecting customers and the service, and our legal duties.</em></li>
                        <li><strong>Legal compliance:</strong> to keep the records financial and anti-money-laundering rules require, and to answer regulators. <em>Basis: our legal obligations.</em></li>
                        <li><strong>Improving the website:</strong> anonymous usage statistics. <em>Basis: your consent, given in the cookie banner.</em></li>
                    </ul>
                    <p><strong>We do not sell your personal information, and we do not use it for advertising or tracking.</strong> The app shows no ads and does not track you across other companies' apps or websites.</p>
                </div>

                <div id="{{ $k = 'share' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>We share information only where it is needed to provide the service:</p>
                    <ul>
                        <li><strong>Atomshop.pk</strong>, where you shop: your {{ config('app.name') }} account is your Atomshop account, and Atomshop delivers and records the orders you pay for in installments.</li>
                        <li><strong>Atomshop staff</strong> who review applications, verify customers and handle repayments and recovery, with access limited by role.</li>
                        @if ($privacy['credit_bureau'])
                            <li><strong>{{ $privacy['credit_bureau'] }}</strong>, the credit bureau we check your credit history with and report repayments to.</li>
                        @endif
                        <li><strong>Meta (WhatsApp Business)</strong> to deliver one-time codes and messages to your mobile number.</li>
                        <li><strong>Email providers</strong> to deliver codes and account emails.</li>
                        <li><strong>Google Firebase Cloud Messaging</strong> to deliver push notifications to the app.</li>
                        <li><strong>Google Analytics</strong> on the website, with Google signals and ad personalisation turned off. It is never used in staff areas.</li>
                        <li><strong>Hosting providers</strong> that store our data on our behalf.</li>
                        <li><strong>Regulators and authorities</strong>, such as the Securities and Exchange Commission of Pakistan, when they ask or the law requires it, or to protect our customers, our business or the public from fraud or harm.</li>
                    </ul>
                    <p>These providers may process data outside Pakistan. They may only use it to provide their service to us.</p>
                </div>

                <div id="{{ $k = 'where' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    @if ($company['data_hosting'])
                        <p>Your account, KYC documents and plan records are stored on our servers in {{ $company['data_hosting'] }}.</p>
                    @endif
                    <p>CNIC photos, selfies and verification forms are kept in private storage that is not reachable from the internet. The providers above may process the data they need for their service, such as a message to deliver, where they operate.</p>
                </div>

                <div id="{{ $k = 'keep' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <ul>
                        <li><strong>Account, identity and KYC documents:</strong> while your account is open. When you delete your account, your CNIC photos, selfie and KYC profile are deleted straight away.</li>
                        <li><strong>Income and employment answers:</strong> while your account is open. On deletion they are deleted, unless they were the basis for an order, in which case the decision is kept without your employer's name.</li>
                        <li><strong>Orders, installment schedules, payments, complaints and the credit decision behind an order:</strong> {{ $retentionYears ? $retentionYears.' years after the plan is repaid or the account is closed, as financial and anti-money-laundering rules require.' : 'for as long as financial and anti-money-laundering rules require.' }} After deletion they are kept only against an anonymised account with no name, email or phone number.</li>
                        <li><strong>Notifications and preferences:</strong> while your account is open; deleted with it.</li>
                        <li><strong>Ask Atom questions</strong> are used to answer you and are not stored.</li>
                        <li><strong>Records of deletion:</strong> the date and account number of a deletion, with no personal details, so we can show it was done.</li>
                        <li><strong>One-time codes</strong> expire within minutes. Unfinished sign-ups are discarded after {{ config('atompay.signup.ttl_minutes') }} minutes. App sign-ins expire after {{ config('atompay.api.token_ttl_days') }} days.</li>
                    </ul>
                    <p>We also keep information while an installment is still owed or disputed.</p>
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
                    </ul>
                </div>

                <div id="{{ $k = 'delete' }}" class="scroll-mt-24">
                    <h3>{{ $sections[$k] }}</h3>
                    <p>You can delete your account yourself at any time once nothing is owed:</p>
                    <ul>
                        <li><strong>In the app:</strong> Account → Settings → Delete account, then confirm with your password.</li>
                        <li><strong>On the web, without the app:</strong> on our <a href="{{ route('account.delete.info') }}" class="underline">delete account page</a>.</li>
                    </ul>
                    <p>Deleting signs you out everywhere and deletes your identity details and documents. Your Atomshop account is closed too, and the financial records we must keep by law are anonymised, as described under "{{ $sections['keep'] }}". If an installment is still owed or an order is awaiting approval, we will tell you, and you can delete once it is settled.</p>
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
                        <p>Through customer support on <a href="{{ $shopLink }}" target="_blank" rel="noopener" class="underline">Atomshop.pk</a>.</p>
                    @endif
                </div>
