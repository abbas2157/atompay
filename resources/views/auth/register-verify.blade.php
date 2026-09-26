{{-- Sign-up step 2: the code sent by email or on WhatsApp. $state comes from SignupService::describe(). --}}
<x-layouts.app title="Enter your code" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">Create your account</span>
                <h1 class="text-[28px] mt-2">Enter your code</h1>
                <p class="text-muted text-[14.5px] mt-2">
                    We sent a {{ config('atompay.password_reset.code_length') }}-digit code
                    {{ $state['channel'] === 'whatsapp' ? 'on WhatsApp to' : 'by email to' }}
                    <strong class="text-ink">{{ $state['destination'] }}</strong>. It expires in {{ config('atompay.signup.code_ttl_minutes') }} minutes.
                </p>

                @if (session('status'))
                    <x-alert class="mt-5">{{ session('status') }}</x-alert>
                @endif
                @error('signup')
                    <x-alert type="error" class="mt-5">{{ $message }} <a href="{{ route('register') }}" class="underline">Start again</a></x-alert>
                @enderror

                <form method="POST" action="{{ route('register.verify.perform') }}" class="mt-6 space-y-4">
                    @csrf
                    <x-input name="code" label="One-time code" inputmode="numeric" autocomplete="one-time-code"
                             maxlength="{{ config('atompay.password_reset.code_length') }}" pattern="[0-9]*" required autofocus />
                    <button type="submit" class="btn btn-primary w-full justify-center">Create account</button>
                </form>

                <form method="POST" action="{{ route('register.resend') }}" class="mt-5 text-center"
                      x-data="{ wait: {{ $state['resend_in'] }} }" x-init="let t = setInterval(() => { if (wait > 0) wait--; else clearInterval(t) }, 1000)">
                    @csrf
                    <button type="submit" class="text-[14px]" :class="wait > 0 ? 'text-muted' : 'underline text-ink'" :disabled="wait > 0">
                        <span x-show="wait > 0">Resend code in <span x-text="wait"></span>s</span>
                        <span x-show="wait === 0" @if ($state['resend_in'] > 0) x-cloak @endif>Didn't get it? Send a new code</span>
                    </button>
                </form>

                <p class="text-[14px] text-muted mt-6 text-center">
                    Wrong {{ $state['channel'] === 'whatsapp' ? 'number' : 'email' }}? <a href="{{ route('register') }}" class="underline text-ink">Change details</a>
                </p>
            </div>
        </div>
    </section>
</x-layouts.app>
