<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    /**
     * Supported social providers.
     */
    private array $supportedProviders = ['google', 'facebook'];

    /**
     * Redirect the user to the provider's authentication page.
     */
    public function redirect(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the social provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')->withErrors([
                'social' => "Unable to authenticate with {$provider}. Please try again.",
            ]);
        }

        // Find existing user by provider ID or email
        $user = $this->findOrCreateUser($socialUser, $provider);

        // Log in the user
        Auth::login($user, true);

        // Check if 2FA is enabled
        if ($user->two_factor_enabled && $user->two_factor_confirmed_at) {
            session()->put('2fa:user:id', $user->id);
            Auth::logout();

            return redirect()->route('two-factor.challenge');
        }

        return redirect()->intended($this->redirectPath($user));
    }

    /**
     * Find an existing user or create a new one from social data.
     */
    private function findOrCreateUser($socialUser, string $provider): User
    {
        $providerIdField = "{$provider}_id";

        // 1. Check if user already linked by provider ID
        $user = User::where($providerIdField, $socialUser->getId())->first();

        if ($user) {
            // Update social avatar if changed
            $user->update(['social_avatar' => $socialUser->getAvatar()]);
            return $user;
        }

        // 2. Check if user exists by email
        $user = User::where('email', $socialUser->getEmail())->first();

        if ($user) {
            // Link the social account to existing user
            $user->update([
                $providerIdField => $socialUser->getId(),
                'social_avatar'  => $socialUser->getAvatar(),
            ]);
            return $user;
        }

        // 3. Create a new user
        $user = User::create([
            'name'              => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
            'username'          => $this->generateUniqueUsername($socialUser),
            'email'             => $socialUser->getEmail(),
            'password'          => Hash::make(Str::random(32)),
            $providerIdField    => $socialUser->getId(),
            'social_avatar'     => $socialUser->getAvatar(),
            'email_verified_at' => now(), // Social emails are pre-verified
            'terms_accepted_at' => now(),
        ]);

        // Assign default student role
        $user->assignRole('student');

        // Fire registered event
        event(new Registered($user));

        return $user;
    }

    /**
     * Generate a unique username from social profile data.
     */
    private function generateUniqueUsername($socialUser): string
    {
        $name = $socialUser->getNickname() ?? $socialUser->getName() ?? 'user';
        $base = Str::slug($name);

        // Ensure uniqueness
        $username = $base;
        $counter = 1;

        while (User::where('username', $username)->exists()) {
            $username = $base . '-' . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Validate the provider is supported.
     */
    private function validateProvider(string $provider): void
    {
        if (!in_array($provider, $this->supportedProviders)) {
            abort(404, "Social provider '{$provider}' is not supported.");
        }
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
