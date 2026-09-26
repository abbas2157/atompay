<x-layouts.app title="Choose a new password" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">Forgot password</span>
                <h1 class="text-[28px] mt-2">Choose a new password</h1>
                <p class="text-muted text-[14.5px] mt-2">It will work on AtomPay and AtomShop.pk. You'll be signed out of the AtomPay app on every phone.</p>

                @error('reset_token')
                    <x-alert type="error" class="mt-5">{{ $message }} <a href="{{ route('password.request') }}" class="underline">Start again</a></x-alert>
                @enderror

                <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="password" label="New password (at least 8 characters)" type="password" autocomplete="new-password" required autofocus />
                    <x-input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
                    <button type="submit" class="btn btn-primary w-full justify-center">Save new password</button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
