<x-layouts.app title="Reset your password" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">Forgot password</span>
                <h1 class="text-[28px] mt-2">Reset your password</h1>
                <p class="text-muted text-[14.5px] mt-2">
                    Enter the email or mobile number on your AtomShop account. We'll send a one-time code
                    by <strong class="text-ink">email</strong>, or on <strong class="text-ink">WhatsApp</strong> if you enter a mobile number.
                </p>

                @error('login')
                    <x-alert type="error" class="mt-5">{{ $message }}</x-alert>
                @enderror

                <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="login" label="Email or mobile number" autocomplete="username" required autofocus />
                    <button type="submit" class="btn btn-primary w-full justify-center">Send code</button>
                </form>

                <p class="text-[14px] text-muted mt-6 text-center">
                    Remembered it? <a href="{{ route('login') }}" class="underline text-ink">Back to sign in</a>
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
