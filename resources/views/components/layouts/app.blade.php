{{-- Marketing chrome: sticky nav with section links, footer with spectrum strip. --}}
@props(['title', 'description' => '', 'schema' => [], 'noindex' => false, 'canonical' => null])
<x-layouts.base>
    <x-slot:seo>
        <x-seo :title="$title" :description="$description" :schema="$schema" :noindex="$noindex" :canonical="$canonical" />
    </x-slot:seo>

    <x-slot:header>
        <header class="sticky top-0 z-50 bg-paper/85 backdrop-blur-md border-b border-line2">
            <div class="wrap flex items-center justify-between h-[72px] gap-4">
                <x-brand-mark id="nav" />
                <nav class="hidden lg:flex items-center gap-7 text-[14.5px]" aria-label="Primary">
                    <a href="{{ route('home') }}#relation" class="text-muted hover:text-ink no-underline">What is AtomPay</a>
                    <a href="{{ route('home') }}#how" class="text-muted hover:text-ink no-underline">How it works</a>
                    <a href="{{ route('home') }}#calculator" class="text-muted hover:text-ink no-underline">Estimate a plan</a>
                    <a href="{{ route('home') }}#assess" class="text-muted hover:text-ink no-underline">Get approved</a>
                    <a href="{{ route('faq') }}" class="text-muted hover:text-ink no-underline">FAQ</a>
                </nav>
                <a class="btn btn-primary btn-sm" href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">My AtomPay</a>
            </div>
        </header>
    </x-slot:header>

    {{ $slot }}

    <x-slot:footer>
        <footer class="pt-12 pb-10 border-t border-line mt-5">
            <div class="wrap flex justify-between items-center gap-5 flex-wrap">
                <x-brand-mark id="foot" />
                <nav class="flex gap-6 font-mono text-[11.5px] tracking-[.05em]" aria-label="Footer">
                    <a href="{{ route('home') }}#relation" class="text-muted hover:text-ink no-underline">What is AtomPay</a>
                    <a href="{{ route('home') }}#how" class="text-muted hover:text-ink no-underline">How it works</a>
                    <a href="{{ route('faq') }}" class="text-muted hover:text-ink no-underline">FAQ</a>
                    <a href="{{ $shopUrl }}" target="_blank" rel="noopener" class="text-muted hover:text-ink no-underline">AtomShop.pk</a>
                </nav>
            </div>
            <div class="spectrum h-1 rounded mx-auto mt-8 max-w-[1140px]"></div>
            <p class="font-mono text-[11px] text-muted text-center mt-4">&copy; {{ date('Y') }} {{ config('app.name') }} &mdash; instalment payments for AtomShop.pk purchases, Pakistan.</p>
        </footer>
    </x-slot:footer>
</x-layouts.base>
