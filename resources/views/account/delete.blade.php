<x-layouts.account title="Delete account">
    <div class="max-w-[560px]">
        <span class="eyebrow">My AtomPay</span>
        <h1 class="mt-2 text-[clamp(26px,3.5vw,34px)]">Delete your account</h1>
        <p class="text-muted mt-2 text-[15px]">
            This closes your {{ config('app.name') }} and AtomShop.pk account for good and deletes your identity details and documents.
            Past orders and payments are kept, without your name. <a href="{{ route('account.delete.info') }}" class="underline text-ink">What is deleted</a>
        </p>

        @if ($blocked)
            <x-alert type="warn" class="mt-6">{{ $blocked }}</x-alert>
            <a href="{{ route('account.dashboard') }}" class="btn btn-ghost mt-5">Back to dashboard</a>
        @else
            <form method="POST" action="{{ route('account.delete.perform') }}" class="card p-6 mt-6 space-y-4">
                @csrf
                <x-input name="password" label="Your password" type="password" autocomplete="current-password" required autofocus />
                <label class="flex items-start gap-2 text-[14px] text-muted">
                    <input type="checkbox" name="confirm" value="1" class="accent-nucleus mt-1" required>
                    <span>I understand this can&rsquo;t be undone.</span>
                </label>
                @error('confirm')
                    <p class="text-coral text-[13px]">{{ $message }}</p>
                @enderror
                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="btn btn-primary">Delete my account</button>
                    <a href="{{ route('account.dashboard') }}" class="btn btn-ghost">Cancel</a>
                </div>
            </form>
        @endif
    </div>
</x-layouts.account>
