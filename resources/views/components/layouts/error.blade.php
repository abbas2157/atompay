{{--
    The shell every error page shares.

    Deliberately self-contained: inline CSS, no @vite, no Alpine, no auth
    lookup, no database query, no shared view data. An error page is often
    rendered *because* one of those is broken - if this file called @vite and
    the manifest were missing, rendering the 500 page would throw the same
    exception again and the visitor would see a raw stack trace. The palette
    below mirrors resources/css/app.css; keep them in step by hand.

    <x-layouts.error code="404" title="Page not found">...</x-layouts.error>
--}}
@props([
    'code',
    'title',
    'eyebrow' => 'Error',
    'noindex' => true,
])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} &mdash; {{ config('app.name') }}</title>
    @if ($noindex)
        <meta name="robots" content="noindex, nofollow">
    @endif
    <meta name="theme-color" content="#FBFAF7">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root {
            --amber: #FAA53A; --coral: #F05465; --rose: #D45771;
            --violet: #62459B; --royal: #3D5DAB;
            --paper: #FBFAF7; --ink: #14151A; --muted: #6C6C74;
            --line: rgba(20, 21, 26, .10);
            --sans: 'Inter', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            --mono: ui-monospace, 'JetBrains Mono', SFMono-Regular, Menlo, monospace;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; flex-direction: column;
            background: var(--paper); color: var(--ink);
            font-family: var(--sans); font-size: 17px; line-height: 1.6;
            -webkit-font-smoothing: antialiased;
        }
        a { color: inherit; }
        .wrap { width: 100%; max-width: 640px; margin: 0 auto; padding: 0 20px; }
        header { padding: 28px 0; }
        .brand { display: inline-flex; align-items: center; gap: 10px; text-decoration: none; font-family: var(--mono); font-size: 13px; font-weight: 700; letter-spacing: .04em; }
        main { flex: 1; display: flex; align-items: center; padding: 24px 0 56px; }
        .eyebrow {
            font-family: var(--mono); font-size: 12px; font-weight: 600;
            letter-spacing: .18em; text-transform: uppercase; color: var(--muted);
            margin: 0 0 14px;
        }
        .code {
            font-family: var(--sans); font-weight: 800; letter-spacing: -.04em;
            font-size: clamp(64px, 16vw, 116px); line-height: .9; margin: 0 0 6px;
            background: linear-gradient(90deg, var(--amber), var(--coral), var(--rose), var(--violet), var(--royal));
            -webkit-background-clip: text; background-clip: text; color: transparent;
        }
        h1 { font-size: clamp(25px, 5vw, 34px); font-weight: 700; letter-spacing: -.02em; line-height: 1.12; margin: 0 0 14px; }
        p.lede { color: var(--muted); font-size: 16.5px; margin: 0 0 10px; max-width: 46ch; }
        .hint { font-family: var(--mono); font-size: 12.5px; color: var(--muted); margin-top: 22px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 30px; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; border-radius: 999px;
            font-family: var(--mono); font-size: 12.5px; font-weight: 600; letter-spacing: .05em;
            padding: 13px 22px; text-decoration: none; border: 1px solid transparent;
            cursor: pointer; transition: background .15s, border-color .15s;
        }
        .btn-primary { background: #050708; color: #fff; }
        .btn-primary:hover { background: rgba(0, 0, 0, .8); }
        .btn-ghost { border-color: var(--line); color: var(--ink); background: transparent; }
        .btn-ghost:hover { border-color: rgba(20, 21, 26, .4); }
        footer { padding: 0 0 30px; }
        .spectrum { height: 4px; border-radius: 4px; background: linear-gradient(90deg, var(--amber), var(--coral), var(--rose), var(--violet), var(--royal)); }
        .fine { font-family: var(--mono); font-size: 11px; color: var(--muted); text-align: center; margin: 14px 0 0; }
        :focus-visible { outline: 2px solid var(--violet); outline-offset: 3px; border-radius: 4px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <a class="brand" href="/">
                <svg width="30" height="30" viewBox="0 0 44 44" aria-hidden="true">
                    <circle cx="22" cy="22" r="20" fill="#050708"/>
                    <ellipse cx="22" cy="22" rx="17" ry="7" fill="none" stroke="#FAA53A" stroke-width="1.6" transform="rotate(28 22 22)"/>
                    <ellipse cx="22" cy="22" rx="17" ry="7" fill="none" stroke="#D45771" stroke-width="1.6" transform="rotate(92 22 22)"/>
                    <ellipse cx="22" cy="22" rx="17" ry="7" fill="none" stroke="#3D5DAB" stroke-width="1.6" transform="rotate(156 22 22)"/>
                    <circle cx="22" cy="22" r="3.4" fill="#FAA53A"/>
                </svg>
                {{ config('app.name') }}
            </a>
        </div>
    </header>

    <main>
        <div class="wrap">
            <p class="eyebrow">{{ $eyebrow }} &middot; {{ $code }}</p>
            <p class="code">{{ $code }}</p>
            <h1>{{ $title }}</h1>
            {{ $slot }}
        </div>
    </main>

    <footer>
        <div class="wrap">
            <div class="spectrum"></div>
            <p class="fine">&copy; {{ date('Y') }} {{ config('app.name') }} &mdash; instalment payments for AtomShop.pk purchases.</p>
        </div>
    </footer>
</body>
</html>
