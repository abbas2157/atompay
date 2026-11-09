{{--
    The "what gets deleted" text, shared by both designs. The markup inside is
    semantic and class-free on purpose, so the v1 view wraps it in its Tailwind
    prose container and the v2 view wraps it in .prose - one copy of the words.

    Keep the lists true to AccountDeletionService.
--}}
                <div>
                    <h3>How to delete it</h3>
                    <p><strong>In the {{ config('app.name') }} app:</strong> choose <strong>Delete account</strong> in your profile and enter your password.</p>
                    <p class="mt-3"><strong>On this website:</strong></p>
                    <ol>
                        <li>Sign in with the phone or email and password you use on Atomshop.pk.</li>
                        <li>Enter your password again and confirm.</li>
                    </ol>
                    <a href="{{ route('account.delete') }}" class="btn btn-primary mt-5">
                        {{ auth()->check() ? 'Continue to delete my account' : 'Sign in to delete my account' }} <span aria-hidden="true">&rarr;</span>
                    </a>
                    <p class="mt-4 text-[14px]">Forgot your password? <a href="{{ route('password.request') }}" class="underline text-ink">Reset it first</a>.</p>
                </div>

                <div>
                    <h3>Before you can delete</h3>
                    <p>Every installment plan must be fully repaid, and no order can be waiting for approval. If something is still owed, we will tell you when you try.</p>
                </div>

                <div>
                    <h3>What is deleted straight away</h3>
                    <ul>
                        <li>Your sign-ins on every phone and browser, and your push-notification tokens.</li>
                        <li>Your identity details: CNIC number, date of birth, address, and the CNIC photos and selfie.</li>
                        <li>Your notifications inbox, preferences, and any sign-up or password-reset codes.</li>
                        <li>Your income and employment answers, if you never placed an order.</li>
                        <li>Your name, email, mobile number and password on the Atomshop account. The account is closed, and the email and number can be used to sign up again.</li>
                    </ul>
                </div>

                <div>
                    <h3>What we keep, and why</h3>
                    <p>We must keep financial records by law. Your past orders, installment schedules and payments, and the credit decision behind any order (without your employer's name), are kept, linked only to an anonymous closed account. Nothing left in them names you.</p>
                </div>

                <div>
                    <h3>Need help?</h3>
                    <p>See our <a href="{{ route('privacy') }}" class="underline">privacy policy</a>, or contact us if you cannot sign in.</p>
                </div>
