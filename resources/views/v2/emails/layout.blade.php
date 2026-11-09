{{--
    Shell for every AtomPay email in the v2 design. Inline styles only - mail
    clients strip <style> blocks and ignore external CSS - and no images, so
    nothing is blocked and nothing needs hosting. That rules out the PNG logo,
    so the mark is drawn as a navy rounded cell with an "A", and the brand's
    amber-to-coral appears as the rule under it.

    Palette mirrors public/web/v2/css/site.css; keep them in step by hand.
    Money is written "Rs." here, as everywhere in v2.

    Which shell a mail uses is decided by design_view('emails.layout') in each
    mail view, and emails render with no request in front of them, so they
    follow the site's live design rather than anybody's ?design= preview.

    Sections: preheader (inbox preview line), content, footer.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background:#F5F7FB;font-family:'Plus Jakarta Sans',Segoe UI,Helvetica,Arial,sans-serif;color:#14213D;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F7FB;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    {{-- Brand --}}
                    <tr>
                        <td style="padding:0 4px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                <td style="width:38px;height:38px;border-radius:11px;background:#14213D;color:#ffffff;font-weight:800;font-size:18px;text-align:center;vertical-align:middle;">A</td>
                                <td style="padding-left:10px;">
                                    <div style="font-weight:800;font-size:18px;letter-spacing:.2px;line-height:1.1;">ATOMPAY</div>
                                    <div style="font-size:9.5px;font-weight:700;letter-spacing:2.4px;color:#69738C;padding-top:2px;">PAY IN STEPS</div>
                                </td>
                            </tr></table>
                        </td>
                    </tr>
                    {{-- The brand rule: amber to coral, as on the site --}}
                    <tr><td style="height:4px;border-radius:4px 4px 0 0;background:#F0545F;background-image:linear-gradient(135deg,#FAA53A,#F05465);"></td></tr>
                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff;border:1px solid #E3E8F1;border-top:0;border-radius:0 0 20px 20px;padding:32px 28px;font-size:16px;line-height:1.6;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 8px 0;font-size:12.5px;line-height:1.6;color:#69738C;">
                            @yield('footer')
                            AtomPay is the installment limit for <a href="{{ config('atompay.shop_url') }}" style="color:#69738C;">Atomshop.pk</a> purchases &mdash;
                            3 months, no advance, 0% markup, on selected listed products.
                            <br><br>
                            AtomPay will never ask for your password or a one-time code by phone, email or WhatsApp.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
