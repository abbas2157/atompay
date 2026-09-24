<x-layouts.app title="Create your AtomShop account" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">One account, both sites</span>
                <h1 class="text-[28px] mt-2">Create your account</h1>
                <p class="text-muted text-[14.5px] mt-2">This account works on AtomShop.pk and here on AtomPay.</p>

                <form method="POST" action="{{ route('register.perform') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="name" label="Full name" autocomplete="name" required autofocus />
                    <x-input name="phone" label="Mobile number" type="tel" inputmode="tel" autocomplete="tel"
                             placeholder="0300 1234567" maxlength="12"
                             pattern="(\+?92|0)?[\s-]?3[0-9]{2}[\s-]?[0-9]{7}" required x-pk-format="mobile" />
                    <x-input name="email" label="Email" type="email" autocomplete="email" required />
                    <x-input name="password" label="Password" type="password" autocomplete="new-password" required />
                    <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
                    <button type="submit" class="btn btn-primary w-full justify-center">Create account</button>
                </form>

                <p class="text-[14px] text-muted mt-6 text-center">
                    Already have one? <a href="{{ route('login') }}" class="underline text-ink">Sign in</a>
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
