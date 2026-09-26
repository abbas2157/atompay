<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\URL;

/**
 * Unsubscribe from alert emails without signing in. Every URL is signed
 * (CustomerAlertMail builds it), so nobody can switch off someone else's
 * alerts by guessing an id.
 *
 *   GET  off  - the link in the email footer: turns alerts off, shows a page
 *   POST off  - RFC 8058 one-click (Gmail's "Unsubscribe" button); CSRF-exempt
 *   POST on   - "turn them back on" from that page
 */
class EmailAlertsController extends Controller
{
    public function off(Request $request, User $user): View|Response
    {
        $this->set($user, false);

        if ($request->isMethod('post')) {
            return response()->noContent();
        }

        return view('emails.alerts-updated', [
            'enabled' => false,
            'toggleUrl' => URL::signedRoute('email.alerts.on', ['user' => $user->id]),
        ]);
    }

    public function on(User $user): View
    {
        $this->set($user, true);

        return view('emails.alerts-updated', [
            'enabled' => true,
            'toggleUrl' => URL::signedRoute('email.alerts.off', ['user' => $user->id]),
        ]);
    }

    private function set(User $user, bool $enabled): void
    {
        UserPreference::updateOrCreate(['user_id' => $user->id], ['email_alerts' => $enabled]);
    }
}
