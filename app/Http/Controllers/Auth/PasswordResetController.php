<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Forgot password on the website: email or mobile -> one-time code (email or
 * WhatsApp) -> new password -> signed in. The same PasswordResetService as
 * the app; the request id and reset token ride in the session, never the URL.
 */
class PasswordResetController extends Controller
{
    private const SESSION = 'password_reset';

    public function __construct(private readonly PasswordResetService $resets) {}

    public function showRequest(): View
    {
        return view('auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate(['login' => ['required', 'string', 'max:255']]);

        $reset = $this->resets->request($request->input('login'), $request->ip());

        $request->session()->put(self::SESSION, ['request_id' => $reset->public_id, 'login' => $request->input('login')]);

        return redirect()->route('password.verify')->with('status', $this->sentMessage($reset->channel, $reset->destination));
    }

    public function showVerify(Request $request): View|RedirectResponse
    {
        $state = $request->session()->get(self::SESSION);
        $reset = $state ? $this->resets->findRequest($state['request_id']) : null;

        if (! $reset) {
            return redirect()->route('password.request');
        }

        return view('auth.verify-code', [
            'reset' => $reset,
            'resendIn' => $this->resets->resendIn($reset),
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);
        $state = $request->session()->get(self::SESSION) ?? abort(419);

        $token = $this->resets->verify($this->resets->findRequest($state['request_id']), $request->input('code'));

        $request->session()->put(self::SESSION.'.token', $token);

        return redirect()->route('password.reset');
    }

    public function resend(Request $request): RedirectResponse
    {
        $state = $request->session()->get(self::SESSION) ?? abort(419);

        $reset = $this->resets->request($state['login'], $request->ip());
        $request->session()->put(self::SESSION.'.request_id', $reset->public_id);

        return redirect()->route('password.verify')->with('status', $this->sentMessage($reset->channel, $reset->destination));
    }

    public function showReset(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has(self::SESSION.'.token')) {
            return redirect()->route('password.request');
        }

        return view('auth.reset-password');
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', 'min:8', 'confirmed']]);
        $token = $request->session()->get(self::SESSION.'.token') ?? abort(419);

        $user = $this->resets->reset($token, $request->input('password'));

        $request->session()->forget(self::SESSION);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard')->with('status', 'Your password has been changed. It works on AtomShop.pk too.');
    }

    private function sentMessage(string $channel, string $destination): string
    {
        return $channel === 'whatsapp'
            ? "If {$destination} belongs to an AtomShop account, we've sent a code to it on WhatsApp."
            : "If {$destination} belongs to an AtomShop account, we've emailed a code to it.";
    }
}
