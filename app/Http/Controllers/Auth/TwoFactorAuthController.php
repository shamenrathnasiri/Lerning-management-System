<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TwoFactorAuthController extends Controller
{
    /**
     * Show the 2FA setup page (for enabling 2FA).
     */
    public function setup(Request $request): View
    {
        $user = $request->user();

        // Generate a new secret if not already set
        if (!$user->two_factor_secret) {
            $secret = $this->generateSecret();
            $user->update(['two_factor_secret' => Crypt::encryptString($secret)]);
        } else {
            $secret = Crypt::decryptString($user->two_factor_secret);
        }

        $qrCodeUrl = $this->generateQrCodeUrl($user, $secret);

        return view('auth.two-factor.setup', [
            'secret'    => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    /**
     * Enable 2FA after verifying the initial code.
     */
    public function enable(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $secret = Crypt::decryptString($user->two_factor_secret);

        if (!$this->verifyCode($secret, $request->input('code'))) {
            return back()->withErrors(['code' => 'The verification code is invalid.']);
        }

        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();

        $user->update([
            'two_factor_enabled'       => true,
            'two_factor_confirmed_at'  => now(),
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'Two-factor authentication has been enabled.')
            ->with('recovery_codes', $recoveryCodes);
    }

    /**
     * Show recovery codes.
     */
    public function recoveryCodes(Request $request): View
    {
        $user = $request->user();
        $codes = [];

        if ($user->two_factor_recovery_codes) {
            $codes = json_decode(Crypt::decryptString($user->two_factor_recovery_codes), true);
        }

        $freshCodes = session('recovery_codes', []);

        return view('auth.two-factor.recovery-codes', [
            'recoveryCodes' => !empty($freshCodes) ? $freshCodes : $codes,
            'isFresh'       => !empty($freshCodes),
        ]);
    }

    /**
     * Regenerate recovery codes.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $recoveryCodes = $this->generateRecoveryCodes();

        $request->user()->update([
            'two_factor_recovery_codes' => Crypt::encryptString(json_encode($recoveryCodes)),
        ]);

        return redirect()->route('two-factor.recovery-codes')
            ->with('status', 'Recovery codes have been regenerated.')
            ->with('recovery_codes', $recoveryCodes);
    }

    /**
     * Show the 2FA challenge page (during login).
     */
    public function challenge(): View|RedirectResponse
    {
        if (!session()->has('2fa:user:id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor.challenge');
    }

    /**
     * Verify the 2FA code during login.
     */
    public function verify(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = session('2fa:user:id');

        if (!$userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);
        $secret = Crypt::decryptString($user->two_factor_secret);
        $code = $request->input('code');

        // Try TOTP code first
        if (strlen($code) === 6 && $this->verifyCode($secret, $code)) {
            return $this->completeLogin($request, $user);
        }

        // Try recovery code
        if (strlen($code) > 6) {
            $recoveryCodes = json_decode(
                Crypt::decryptString($user->two_factor_recovery_codes),
                true
            );

            if (in_array($code, $recoveryCodes)) {
                // Remove used recovery code
                $remainingCodes = array_values(array_diff($recoveryCodes, [$code]));
                $user->update([
                    'two_factor_recovery_codes' => Crypt::encryptString(json_encode($remainingCodes)),
                ]);

                return $this->completeLogin($request, $user);
            }
        }

        return back()->withErrors(['code' => 'The verification code is invalid.']);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $request->user()->update([
            'two_factor_enabled'        => false,
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'Two-factor authentication has been disabled.');
    }

    /**
     * Complete the login process after 2FA verification.
     */
    private function completeLogin(Request $request, User $user): RedirectResponse
    {
        session()->forget('2fa:user:id');
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Generate a random 2FA secret (base32 encoded).
     */
    private function generateSecret(): string
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    /**
     * Generate the QR code provisioning URL.
     */
    private function generateQrCodeUrl(User $user, string $secret): string
    {
        $appName = urlencode(config('app.name', 'LMS'));
        $email = urlencode($user->email);

        return "otpauth://totp/{$appName}:{$email}?secret={$secret}&issuer={$appName}&digits=6&period=30";
    }

    /**
     * Verify a TOTP code against the secret.
     *
     * Uses a time-based hash with a ±1 window for clock drift tolerance.
     */
    private function verifyCode(string $secret, string $code): bool
    {
        $timeSlice = floor(time() / 30);

        // Check current and adjacent time slices (±1 for clock drift)
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTP($secret, $timeSlice + $i);

            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate a TOTP code for the given time slice.
     */
    private function generateTOTP(string $secret, int $timeSlice): string
    {
        // Decode base32 secret
        $secretKey = $this->base32Decode($secret);

        // Pack time into binary (big-endian 64-bit)
        $time = pack('N*', 0, $timeSlice);

        // Generate HMAC-SHA1
        $hmac = hash_hmac('sha1', $time, $secretKey, true);

        // Dynamic truncation
        $offset = ord(substr($hmac, -1)) & 0x0f;
        $code = (
            ((ord($hmac[$offset]) & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) << 8) |
            (ord($hmac[$offset + 3]) & 0xff)
        ) % pow(10, 6);

        return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Decode a base32-encoded string.
     */
    private function base32Decode(string $input): string
    {
        $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0; $i < strlen($input); $i++) {
            $val = strpos($map, $input[$i]);
            if ($val === false) continue;

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xff);
            }
        }

        return $output;
    }

    /**
     * Generate a set of recovery codes.
     */
    private function generateRecoveryCodes(int $count = 8): array
    {
        return Collection::times($count, function () {
            return Str::random(5) . '-' . Str::random(5);
        })->toArray();
    }
}
