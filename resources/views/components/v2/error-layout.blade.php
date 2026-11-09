{{--
    The shell every v2 error page shares.

    Deliberately self-contained, exactly like the v1 one: inline CSS, no
    stylesheet link, no web font, no script, no auth lookup, no database query,
    no shared view data. An error page is often rendered *because* one of those
    is broken - a 500 that linked site.css would show an unstyled page the one
    time styling matters most, and a 503 is served with the database down.

    The palette below mirrors public/web/v2/css/site.css; keep them in step by
    hand. Plus Jakarta Sans is named first and falls back to the system stack,
    so the page is right whether or not the font happens to be cached.

    <x-v2.error-layout code="404" title="Page not found">...</x-v2.error-layout>
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
    <meta name="theme-color" content="#14213D">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <style>
        :root{
            --ink:#14213D; --primary:#2747C7; --primary-dark:#1D3AA6; --primary-soft:#EEF2FD;
            --amber:#F7A928; --surface:#F5F7FB; --line:#E3E8F1; --line-2:#DCE2EC;
            --body:#4F5B76; --muted:#69738C; --rose:#B0283F;
            --apay-coral:#F05465; --apay-amber:#FAA53A;
            --sans:'Plus Jakarta Sans',system-ui,-apple-system,'Segoe UI',sans-serif;
        }
        *{box-sizing:border-box}
        body{
            margin:0;min-height:100vh;display:flex;flex-direction:column;
            background:#fff;color:var(--ink);font-family:var(--sans);font-size:17px;line-height:1.6;
            -webkit-font-smoothing:antialiased;
        }
        a{color:inherit;text-decoration:none}
        .wrap{width:100%;max-width:720px;margin:0 auto;padding:0 24px}
        header{padding:22px 0;border-bottom:1px solid var(--line)}
        .brand{display:inline-flex;align-items:center;gap:10px}
        .brand-mark{width:40px;height:40px;flex:none;border-radius:11px;background:var(--ink);
            display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:18px}
        .brand strong{display:block;font-size:18px;font-weight:800;letter-spacing:.2px;line-height:1.1}
        .brand small{display:block;font-size:9.5px;font-weight:700;letter-spacing:2.4px;color:var(--muted);margin-top:2px}
        main{flex:1;display:flex;align-items:center;padding:40px 0 56px}
        .eyebrow{font-size:12px;font-weight:800;letter-spacing:1.6px;text-transform:uppercase;color:var(--primary);margin:0 0 12px}
        .code{font-weight:800;letter-spacing:-4px;font-size:clamp(64px,16vw,112px);line-height:.9;margin:0 0 10px;
            background:linear-gradient(135deg,var(--apay-amber),var(--apay-coral));
            -webkit-background-clip:text;background-clip:text;color:transparent}
        h1{font-size:clamp(26px,5vw,38px);font-weight:800;letter-spacing:-1px;line-height:1.1;margin:0 0 14px}
        p.lede{color:var(--body);font-size:17px;margin:0 0 10px;max-width:52ch}
        .hint{font-size:14px;color:var(--muted);margin-top:24px}
        .hint a{font-weight:700;color:var(--primary)}
        .actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:48px;padding:0 22px;
            border-radius:999px;font-weight:700;font-size:15px;border:1px solid transparent;cursor:pointer}
        /* .btn-primary / .btn-ghost are aliases: the error pages' own markup is
           shared with the v1 layout, which names them that way. Only the shell
           differs between designs, never the words. */
        .btn-p,.btn-primary{background:var(--primary);color:#fff}
        .btn-p:hover,.btn-primary:hover{background:var(--primary-dark)}
        .btn-g,.btn-ghost{background:#fff;color:var(--ink);border-color:var(--line-2)}
        .btn-g:hover,.btn-ghost:hover{border-color:var(--primary);background:var(--primary-soft);color:var(--primary)}
        footer{padding:0 0 30px}
        .rule{height:4px;border-radius:4px;background:linear-gradient(135deg,var(--apay-amber),var(--apay-coral))}
        .fine{font-size:12.5px;color:var(--muted);text-align:center;margin:14px 0 0}
        :focus-visible{outline:2px solid var(--primary);outline-offset:3px;border-radius:4px}
        @media (prefers-reduced-motion:reduce){*{transition:none!important}}
    </style>
</head>
<body>
    <header>
        <div class="wrap">
            <a class="brand" href="/">
                <span class="brand-mark" aria-hidden="true">A</span>
                <span><strong>ATOMPAY</strong><small>PAY IN STEPS</small></span>
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
            <div class="rule"></div>
            <p class="fine">&copy; {{ date('Y') }} {{ config('app.name') }} &mdash; the installment limit for Atomshop.pk purchases.</p>
        </div>
    </footer>
</body>
</html>
