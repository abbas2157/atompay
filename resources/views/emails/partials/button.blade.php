{{-- A bulletproof-ish CTA button: a padded link inside a table cell renders in Outlook too. --}}
<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px;">
    <tr>
        <td style="background:#050708;border-radius:999px;">
            <a href="{{ $url }}" style="display:inline-block;padding:14px 26px;color:#ffffff;text-decoration:none;font-weight:600;font-size:15px;">{{ $label }}</a>
        </td>
    </tr>
</table>
