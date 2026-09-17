{{-- Signed-in chrome: compact nav with the user chip and sign-out. Never indexed. --}}
@props(['title', 'wide' => false])
@php $user = auth()->user(); @endphp
<x-layouts.base>
    <x-slot:seo>
        <x-seo :title="$title" :noindex="true" />
    </x-slot:seo>

    <x-slot:header>
        <header class="sticky top-0 z-50 bg-paper/90 backdrop-blur-md border-b border-line2">
            <div class="wrap flex items-center justify-between h-[68px] gap-4">
                <div class="flex items-center gap-6">
                    <x-brand-mark id="acc" :tagline="false" />
                    <nav class="hidden sm:flex items-center gap-5 text-[14px]" aria-label="Account">
                        @if ($user->isCustomer())
                            <a href="{{ route('account.dashboard') }}" class="text-muted hover:text-ink no-underline {{ request()->routeIs('account.dashboard') ? 'text-ink font-semibold' : '' }}">Dashboard</a>
                            <a href="{{ route('account.application') }}" class="text-muted hover:text-ink no-underline {{ request()->routeIs('account.application*') ? 'text-ink font-semibold' : '' }}">Application</a>
                        @endif
                        @if ($user->isStaff())
                            <a href="{{ route('staff.assessments.index') }}" class="text-muted hover:text-ink no-underline {{ request()->routeIs('staff.*') ? 'text-ink font-semibold' : '' }}">Review queue</a>
                        @endif
                    </nav>
                </div>
                <div class="flex items-center gap-4 text-[14px]">
                    <span class="flex items-center gap-2.5">
                        <span class="spectrum w-8 h-8 rounded-full shrink-0" aria-hidden="true"></span>
                        <span>{{ $user->shortName() }}</span>
                    </span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">Sign out</button>
                    </form>
                </div>
            </div>
        </header>
    </x-slot:header>

    <div class="wrap pt-10 pb-[72px] {{ $wide ? 'max-w-[1280px]' : '' }}">
        @if (session('status'))
            <x-alert class="mb-6">{{ session('status') }}</x-alert>
        @endif
        {{ $slot }}
    </div>
</x-layouts.base>
