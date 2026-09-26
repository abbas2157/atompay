<x-layouts.app title="Create your AtomShop account" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">One account, both sites</span>
                <h1 class="text-[28px] mt-2">Create your account</h1>
                <p class="text-muted text-[14.5px] mt-2">
                    This account works on AtomShop.pk and here on AtomPay. Sign up with your
                    <strong class="text-ink">email</strong> (we'll email you a code) or your
                    <strong class="text-ink">mobile number</strong> (we'll send the code on WhatsApp).
                </p>

                @error('signup')
                    <x-alert type="error" class="mt-5">{{ $message }}</x-alert>
                @enderror

                <form method="POST" action="{{ route('register.perform') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="name" label="Full name" autocomplete="name" required autofocus />
                    <x-input name="login" label="Email or mobile number" autocomplete="username"
                             placeholder="you@example.com or 0300 1234567" required />
                    <x-input name="password" label="Password (at least 8 characters)" type="password" autocomplete="new-password" required />
                    <x-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" required />
                    <button type="submit" class="btn btn-primary w-full justify-center">Continue</button>
                </form>

                <p class="text-[14px] text-muted mt-6 text-center">
                    Already have one? <a href="{{ route('login') }}" class="underline text-ink">Sign in</a>
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
