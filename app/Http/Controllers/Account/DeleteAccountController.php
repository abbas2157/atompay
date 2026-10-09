<?php

namespace App\Http\Controllers\Account;

use App\Exceptions\Api\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Services\AccountDeletionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Account deletion on the website: the "delete account" link the Play
 * Console and App Store listings point at. Same service, same rules and
 * same password check as `POST /me/delete` in the app.
 */
class DeleteAccountController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletions) {}

    /** Public: what deletion does, and the way in. Reachable without the app. */
    public function info(): View
    {
        return view('pages.delete-account');
    }

    public function show(Request $request): View
    {
        try {
            $this->deletions->ensureDeletable($request->user());
            $blocked = null;
        } catch (BusinessRuleException $e) {
            $blocked = $e->getMessage();
        }

        return view('account.delete', ['blocked' => $blocked]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(
            ['password' => ['required', 'string'], 'confirm' => ['accepted']],
            ['password.required' => 'Enter your password to confirm.', 'confirm.accepted' => 'Tick the box to confirm.'],
        );

        $user = $request->user();

        if (! Hash::check((string) $request->input('password'), (string) $user->getAuthPassword())) {
            return back()->withErrors(['password' => "That password isn't right."]);
        }

        try {
            $this->deletions->delete($user, 'web', $request->ip());
        } catch (BusinessRuleException $e) {
            return back()->withErrors(['password' => $e->getMessage()]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('account.delete.info')
            ->with('status', 'Your account has been deleted. You have been signed out everywhere.');
    }
}
