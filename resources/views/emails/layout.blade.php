{{--
    Shell for every AtomPay email. Inline styles only - mail clients strip
    <style> blocks and ignore external CSS - and no images, so nothing is
    blocked or needs hosting. Palette mirrors resources/css/app.css.

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
<body style="margin:0;padding:0;background:#FBFAF7;font-family:Inter,Segoe UI,Helvetica,Arial,sans-serif;color:#14151A;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">@yield('preheader')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#FBFAF7;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">
                    {{-- Brand --}}
                    <tr>
                        <td style="padding:0 4px 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0"><tr>
                                <td style="width:34px;height:34px;border-radius:17px;background:#050708;color:#ffffff;font-weight:800;font-size:17px;text-align:center;vertical-align:middle;">A</td>
                                <td style="padding-left:10px;font-weight:800;font-size:18px;letter-spacing:-0.3px;">AtomPay</td>
                            </tr></table>
                        </td>
                    </tr>
                    {{-- Spectrum rule --}}
                    <tr><td style="height:4px;border-radius:4px 4px 0 0;background:#62459B;background-image:linear-gradient(90deg,#FAA53A,#F05465,#D45771,#62459B,#3D5DAB);"></td></tr>
                    {{-- Card --}}
                    <tr>
                        <td style="background:#ffffff;border:1px solid rgba(20,21,26,0.10);border-top:0;border-radius:0 0 20px 20px;padding:32px 28px;font-size:16px;line-height:1.55;">
                            @yield('content')
                        </td>
                    </tr>
                    {{-- Footer --}}
                    <tr>
                        <td style="padding:20px 8px 0;font-size:12.5px;line-height:1.6;color:#6C6C74;">
                            @yield('footer')
                            AtomPay is the instalment partner of <a href="{{ config('atompay.shop_url') }}" style="color:#6C6C74;">AtomShop.pk</a>.
                            AtomPay will never ask for your password or a one-time code by phone, email or WhatsApp.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
