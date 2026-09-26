<x-layouts.app title="Sign in to My AtomPay" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">My AtomPay</span>
                <h1 class="text-[28px] mt-2">Sign in with your AtomShop account</h1>
                <p class="text-muted text-[14.5px] mt-2">Use the same phone or email and password you use on AtomShop.pk.</p>

                @error('login')
                    <x-alert type="error" class="mt-5">{{ $message }}</x-alert>
                @enderror

                <form method="POST" action="{{ route('login.perform') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="login" label="Phone or email" autocomplete="username" required autofocus />
                    <x-input name="password" label="Password" type="password" autocomplete="current-password" required />
                    <div class="flex items-center justify-between gap-3">
                        <label class="flex items-center gap-2 text-[14px] text-muted">
                            <input type="checkbox" name="remember" value="1" class="accent-nucleus"> Keep me signed in
                        </label>
                        <a href="{{ route('password.request') }}" class="text-[14px] underline text-ink">Forgot password?</a>
                    </div>
                    <button type="submit" class="btn btn-primary w-full justify-center">Sign in</button>
                </form>

                <p class="text-[14px] text-muted mt-6 text-center">
                    New to AtomShop? <a href="{{ route('register') }}" class="underline text-ink">Create an account</a>
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
