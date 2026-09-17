{{-- Marketing chrome: sticky nav with section links, footer with spectrum strip. --}}
@props(['title', 'description' => '', 'schema' => [], 'noindex' => false, 'canonical' => null])
<x-layouts.base>
    <x-slot:seo>
        <x-seo :title="$title" :description="$description" :schema="$schema" :noindex="$noindex" :canonical="$canonical" />
    </x-slot:seo>

    <x-slot:header>
        @php
            $links = [
                [route('home').'#relation',   'What is AtomPay'],
                [route('home').'#how',        'How it works'],
                [route('home').'#calculator', 'Estimate a plan'],
                [route('home').'#assess',     'Get approved'],
                [route('faq'),                'FAQ'],
            ];
        @endphp
        <header class="sticky top-0 z-50 bg-paper/85 backdrop-blur-md border-b border-line2" x-data="{ open: false }" @keydown.escape.window="open = false">
            <div class="wrap flex items-center justify-between h-[72px] gap-4">
                <x-brand-mark id="nav" />
                <nav class="hidden lg:flex items-center gap-7 text-[14.5px]" aria-label="Primary">
                    @foreach ($links as [$href, $label])
                        <a href="{{ $href }}" class="text-muted hover:text-ink no-underline">{{ $label }}</a>
                    @endforeach
                </nav>
                <div class="flex items-center gap-2">
                    <a class="btn btn-primary btn-sm" href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">My AtomPay</a>
                    <x-menu-button class="lg:hidden" />
                </div>
            </div>
            <x-mobile-menu :links="$links" />
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
