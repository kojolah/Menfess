<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors([
                'oauth' => 'Login Google belum dikonfigurasi. Pengelola perlu menambahkan OAuth Client ID dan Client Secret.',
            ]);
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $googleId = (string) $googleUser->getId();
            $email = mb_strtolower(trim((string) $googleUser->getEmail()));
            $verified = filter_var($googleUser->user['email_verified'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($googleId === '' || $email === '') {
                return redirect()->route('login')->withErrors(['oauth' => 'Google tidak memberikan identitas akun yang lengkap.']);
            }

            if (! $verified) {
                return redirect()->route('login')->withErrors(['oauth' => 'Akun Google harus memiliki email yang sudah terverifikasi.']);
            }

            $user = User::where('google_id', $googleId)->first();

            if (! $user) {
                if (User::where('email', $email)->exists()) {
                    return redirect()->route('login')->withErrors(['oauth' => 'Email ini sudah terdaftar dengan metode lain. Hubungi admin untuk verifikasi akun.']);
                }

                $user = new User;
                $user->forceFill([
                    'name' => $googleUser->getName() ?: 'Mahasiswa',
                    'email' => $email,
                    'google_id' => $googleId,
                    'email_verified_at' => now(),
                    'password' => null,
                    'role' => UserRole::User->value,
                    'status' => 'active',
                ])->save();
            }

            if ($user->status !== 'active') {
                return redirect()->route('login')->withErrors(['oauth' => 'Akun ini sedang dinonaktifkan. Hubungi pengelola kampus.']);
            }

            Auth::login($user);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (Throwable $exception) {
            Log::warning('Google OAuth callback failed.', ['exception' => $exception::class]);

            return redirect()->route('login')->withErrors(['oauth' => 'Login Google gagal. Silakan coba kembali.']);
        }
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
