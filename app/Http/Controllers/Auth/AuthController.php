<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Auth\Concerns\LogsFailedLogins;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    use LogsFailedLogins;

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

    public function register(RegisterRequest $request, AccountService $accounts): RedirectResponse
    {
        $user = $accounts->registerCustomer($request->validated());
        $accounts->sendWelcome($user);

        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterSignIn($request);
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
