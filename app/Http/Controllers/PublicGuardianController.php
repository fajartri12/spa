<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guardian;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Autentikasi portal orang tua/wali.
 *
 * Berbeda dari portal siswa yang memakai NISN, portal orang tua memakai nomor
 * HP karena wali tidak selalu hafal nomor induk anaknya.
 */
class PublicGuardianController extends Controller
{
    public const SESSION_KEY = 'public_guardian_id';

    public function login(): View
    {
        return view('public.guardian.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $phone = Guardian::normalizePhone($validated['phone']);
        $throttleKey = 'portal-guardian-login:'.$phone.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'phone' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $guardian = Guardian::query()
            ->active()
            ->where('phone', $phone)
            ->first();

        if (! $guardian || ! $this->passwordMatches($guardian, $validated['password'])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'phone' => 'Nomor HP atau password tidak sesuai.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        session([self::SESSION_KEY => $guardian->getKey()]);
        $request->session()->forget('url.intended');

        return redirect('/portal/orang-tua');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/portal/orang-tua/login');
    }

    /**
     * Akun tanpa password belum diaktivasi oleh petugas sekolah.
     */
    private function passwordMatches(Guardian $guardian, string $password): bool
    {
        if (! $guardian->hasPassword()) {
            return false;
        }

        return Hash::check($password, (string) $guardian->password);
    }
}
