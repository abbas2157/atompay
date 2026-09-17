<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        if (! Auth::attempt($request->credentials(), $request->boolean('remember'))) {
            // Support trail for "can't sign in" reports - identifier only, never the password.
            $credentials = $request->credentials();
            $field       = array_key_first($credentials);
            Log::info('AtomPay login failed', [
                'field'   => $field,
                'value'   => $credentials[$field],
                'user_id' => User::where($field, $credentials[$field])->value('id'),
                'ip'      => $request->ip(),
            ]);

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
     * Creates the same kind of record AtomShop checkout creates for a
     * walk-in buyer, so the account works on both sites.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $user = new User;
        $user->forceFill([
            'uuid'           => (string) Str::uuid(),
            'name'           => $request->validated('name'),
            'phone'          => $request->validated('phone'),
            'email'          => $request->validated('email'),
            'password'       => $request->validated('password'),
            'role'           => config('atompay.customer_role'),
            'status'         => 'active',
            'joined_through' => 'Website',
        ])->save();

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
