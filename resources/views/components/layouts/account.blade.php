{{-- Signed-in chrome: compact nav with the user chip and sign-out. Never indexed. --}}
@props(['title', 'wide' => false])
@php
    $user  = auth()->user();
    $links = [];
    if ($user->isCustomer()) {
        $links[] = [route('account.dashboard'),   'Dashboard',   request()->routeIs('account.dashboard')];
        $links[] = [route('account.application'), 'Application', request()->routeIs('account.application*')];
    }
    if ($user->isStaff()) {
        $links[] = [route('staff.assessments.index'), 'Review queue', request()->routeIs('staff.*')];
    }
@endphp
<x-layouts.base>
    <x-slot:seo>
        <x-seo :title="$title" :noindex="true" />
    </x-slot:seo>

    <x-slot:header>
        <header class="sticky top-0 z-50 bg-paper/90 backdrop-blur-md border-b border-line2" x-data="{ open: false }" @keydown.escape.window="open = false">
            <div class="wrap flex items-center justify-between h-[68px] gap-4">
                <div class="flex items-center gap-6">
                    <x-brand-mark id="acc" :tagline="false" />
                    <nav class="hidden sm:flex items-center gap-5 text-[14px]" aria-label="Account">
                        @foreach ($links as [$href, $label, $active])
                            <a href="{{ $href }}" class="no-underline hover:text-ink {{ $active ? 'text-ink font-semibold' : 'text-muted' }}">{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>
                <div class="flex items-center gap-3 text-[14px]">
                    <span class="flex items-center gap-2.5">
                        <span class="spectrum w-8 h-8 rounded-full shrink-0" aria-hidden="true"></span>
                        <span class="hidden sm:inline">{{ $user->shortName() }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}" class="hidden sm:block">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                    </form>
                    <x-menu-button class="sm:hidden" />
                </div>
            </div>
            <x-mobile-menu :links="collect($links)->map(fn ($l) => [$l[0], $l[1]])->all()" breakpoint="sm">
                <form method="POST" action="{{ route('logout') }}" class="py-3 flex items-center justify-between">
                    @csrf
                    <span class="text-[14px] text-muted">{{ $user->shortName() }}</span>
                    <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                </form>
            </x-mobile-menu>
        </header>
    </x-slot:header>

    <div class="wrap pt-10 pb-[72px] {{ $wide ? 'max-w-[1280px]' : '' }}">
        @if (session('status'))
            <x-alert class="mb-6">{{ session('status') }}</x-alert>
        @endif
        {{ $slot }}
    </div>
</x-layouts.base>
