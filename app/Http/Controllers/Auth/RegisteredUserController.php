<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:50', 'unique:users,username', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'avatar'   => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'terms'    => ['required', 'accepted'],
        ], [
            'username.regex'  => 'Username can only contain letters, numbers, underscores, and hyphens.',
            'terms.accepted'  => 'You must accept the Terms of Service and Privacy Policy.',
        ]);

        // Handle avatar upload
        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        // Create user
        $user = User::create([
            'name'              => $validated['name'],
            'username'          => Str::lower($validated['username']),
            'email'             => $validated['email'],
            'password'          => Hash::make($validated['password']),
            'avatar'            => $avatarPath,
            'terms_accepted_at' => now(),
        ]);

        // Always assign student role (instructors are promoted by admins)
        $user->assignRole('student');

        // Fire registered event (triggers email verification)
        event(new Registered($user));

        // Redirect to login page with success message (do NOT auto-login)
        return redirect()->route('login')
            ->with('status', 'Registration successful! Please log in to continue.');
    }
}
