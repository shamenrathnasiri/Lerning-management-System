<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data">
        @csrf

        <!-- Full Name -->
        <div>
            <x-input-label for="name" :value="__('Full Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Username -->
        <div class="mt-4">
            <x-input-label for="username" :value="__('Username')" />
            <x-text-input id="username" class="block mt-1 w-full" type="text" name="username" :value="old('username')" required autocomplete="username" placeholder="e.g. john_doe" />
            <p class="mt-1 text-xs text-gray-500">Letters, numbers, underscores, and hyphens only.</p>
            <x-input-error :messages="$errors->get('username')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>


        <!-- Profile Picture -->
        <div class="mt-4">
            <x-input-label for="avatar" :value="__('Profile Picture (Optional)')" />
            <div class="mt-1 flex items-center space-x-4">
                <div id="avatar-preview" class="h-12 w-12 rounded-full bg-gray-200 flex items-center justify-center overflow-hidden">
                    <svg class="h-8 w-8 text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M24 20.993V24H0v-2.996A14.977 14.977 0 0112.004 15c4.904 0 9.26 2.354 11.996 5.993zM16.002 8.999a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                </div>
                <input id="avatar" type="file" name="avatar" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100" onchange="previewAvatar(this)" />
            </div>
            <p class="mt-1 text-xs text-gray-500">JPG, PNG, or WebP. Max 2MB.</p>
            <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <!-- Terms & Conditions -->
        <div class="mt-4">
            <label for="terms" class="inline-flex items-start">
                <input id="terms" type="checkbox" name="terms" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 mt-0.5" {{ old('terms') ? 'checked' : '' }}>
                <span class="ms-2 text-sm text-gray-600">
                    I agree to the <a href="#" class="underline text-indigo-600 hover:text-indigo-800">Terms of Service</a>
                    and <a href="#" class="underline text-indigo-600 hover:text-indigo-800">Privacy Policy</a>.
                </span>
            </label>
            <x-input-error :messages="$errors->get('terms')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-6">
            <a class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Create Account') }}
            </x-primary-button>
        </div>

        <!-- Social Register Divider -->
        <div class="mt-6">
            <div class="relative">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-2 bg-white text-gray-500">Or register with</span>
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <a href="{{ route('social.redirect', 'google') }}" class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <svg class="h-5 w-5 mr-2" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                    </svg>
                    Google
                </a>
                <a href="{{ route('social.redirect', 'facebook') }}" class="flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <svg class="h-5 w-5 mr-2" fill="#1877F2" viewBox="0 0 24 24">
                        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                    </svg>
                    Facebook
                </a>
            </div>
        </div>
    </form>

    <script>
        function previewAvatar(input) {
            const preview = document.getElementById('avatar-preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" class="h-12 w-12 rounded-full object-cover">';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</x-guest-layout>
