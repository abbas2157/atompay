{{-- The page an alert email's unsubscribe link lands on (a web page, not an email). --}}
<x-layouts.app :title="$enabled ? 'Alert emails are on' : 'Alert emails are off'" :noindex="true">
    <section class="py-[60px] sm:py-[84px]">
        <div class="wrap">
            <div class="card p-7 sm:p-9 max-w-[460px] mx-auto">
                <span class="eyebrow">Email settings</span>
                @if ($enabled)
                    <h1 class="text-[28px] mt-2">Alert emails are back on</h1>
                    <p class="text-muted text-[14.5px] mt-2">You'll get emails about your limit, your verification and upcoming instalments.</p>
                @else
                    <h1 class="text-[28px] mt-2">Alert emails are off</h1>
                    <p class="text-muted text-[14.5px] mt-2">
                        You won't get emails about your limit, verification or instalments any more. They'll still appear in
                        My AtomPay and the app. Security emails, like password reset codes, are always sent.
                    </p>
                @endif

                <form method="POST" action="{{ $toggleUrl }}" class="mt-6">
                    @csrf
                    <button type="submit" class="btn {{ $enabled ? 'btn-ghost' : 'btn-primary' }} w-full justify-center">
                        {{ $enabled ? 'Turn alert emails off' : 'Turn alert emails back on' }}
                    </button>
                </form>
            </div>
        </div>
    </section>
</x-layouts.app>
