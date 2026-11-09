{{-- A bulletproof-ish CTA: a padded link inside a table cell renders in Outlook too.
     Royal blue, the v2 primary - the amber-to-coral gradient is not reliable as a
     button background in mail clients, so it stays on the rule at the top. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
    <tr>
        <td style="background:#2747C7;border-radius:999px;">
            <a href="{{ $url }}" style="display:inline-block;padding:14px 26px;color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;">{{ $label }}</a>
        </td>
    </tr>
</table>
