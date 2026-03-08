<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Check if user account is soft-deleted (banned)
        if ($user->trashed()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Your account has been suspended. Please contact support.',
            ]);
        }

        // Check if 2FA is enabled and requires verification
        if ($user->two_factor_enabled && $user->two_factor_confirmed_at) {
            // Store user ID in session and redirect to 2FA challenge
            $request->session()->put('2fa:user:id', $user->id);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        // Redirect based on role
        return redirect()->intended($this->redirectPath($user));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Determine the redirect path based on user role.
     */
    private function redirectPath($user): string
    {
        if ($user->isAdmin()) {
            return route('admin.dashboard', absolute: false);
        }

        if ($user->isInstructor()) {
            return route('instructor.dashboard', absolute: false);
        }

        return route('dashboard', absolute: false);
    }
}
