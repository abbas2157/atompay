<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\LogsFailedLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AccountService;
use App\Services\SignupService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use LogsFailedLogins;

    /** Session key holding the pending sign-up's public id between the two steps. */
    private const SIGNUP = 'signup_id';

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            $this->logFailedLogin($request, $request->credentials());

            return back()->withInput($request->only('login'))
                ->withErrors(['login' => 'Those details do not match an AtomShop account.']);
        }

        $request->session()->regenerate();

        return $this->afterSignIn($request);
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    /**
     * Step 1: details are checked and a code sent - by email if an email was
     * given, on WhatsApp if a mobile was. No account exists until it comes back.
     */
    public function register(RegisterRequest $request, SignupService $signups): RedirectResponse
    {
        $signup = $signups->start($request->signup(), $request->ip());
        $request->session()->put(self::SIGNUP, $signup->public_id);

        return redirect()->route('register.verify');
    }

    public function showVerifySignup(Request $request, SignupService $signups): View|RedirectResponse
    {
        $signup = $signups->find($request->session()->get(self::SIGNUP));

        if (! $signup?->isOpen()) {
            return redirect()->route('register')->withErrors(['signup' => 'Your sign-up expired. Please fill in your details again.']);
        }

        return view('auth.register-verify', ['state' => $signups->describe($signup)]);
    }

    /** Step 2: right code -> account created, welcomed and signed in. */
    public function verifySignup(Request $request, SignupService $signups, AccountService $accounts): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:12']]);

        $user = $signups->complete($signups->find($request->session()->get(self::SIGNUP)), $request->input('code'));

        $request->session()->forget(self::SIGNUP);
        $accounts->sendWelcome($user);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterSignIn($request);
    }

    public function resendSignupCode(Request $request, SignupService $signups): RedirectResponse
    {
        $signup = $signups->resend($signups->find($request->session()->get(self::SIGNUP)));

        return redirect()->route('register.verify')->with('status', $signup->channel === 'email'
            ? 'We sent a new code to your email.'
            : 'We sent a new code to your WhatsApp.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * Staff land on the review queue; a customer who estimated a limit
     * before signing in is taken straight into the application.
     */
    private function afterSignIn(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->isStaff() && ! $user->isCustomer()) {
            return redirect()->route('staff.assessments.index');
        }

        if ($request->session()->has('atompay.estimate')) {
            return redirect()->route('account.application');
        }

        return redirect()->intended(route('account.dashboard'));
    }
}
