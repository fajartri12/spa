<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Ubah password portal orang tua secara mandiri lewat OTP WhatsApp.
 *
 * Wali kerap lupa password yang diberikan petugas. Dengan OTP yang dikirim ke
 * nomor HP terdaftar, wali dapat membuat password baru sendiri tanpa memanggil
 * admin sekolah. Respons selalu generik agar keberadaan akun tidak bocor.
 */
class PublicGuardianPasswordController extends Controller
{
    private const OTP_TTL_MINUTES = 5;

    private const OTP_MAX_ATTEMPTS = 5;

    public function showForgot(): View
    {
        return view('public.guardian.forgot-password');
    }

    public function sendOtp(Request $request, NotificationService $service): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
        ]);

        $phone = Guardian::normalizePhone($validated['phone']);
        $throttleKey = 'guardian-otp:'.$phone.'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            return back()->withErrors([
                'phone' => 'Terlalu banyak permintaan kode. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ])->withInput();
        }

        RateLimiter::hit($throttleKey, 60);

        $guardian = Guardian::query()->active()->where('phone', $phone)->first();

        if ($guardian !== null) {
            $otp = (string) random_int(100000, 999999);

            Cache::put($this->cacheKey($guardian), [
                'hash' => Hash::make($otp),
                'attempts' => 0,
            ], now()->addMinutes(self::OTP_TTL_MINUTES));

            $service->sendWhatsAppText(
                $guardian->phone,
                $this->otpMessage($guardian, $otp),
                null,
                false,
            );
        }

        return redirect()
            ->route('public.guardian.forgot.verify')
            ->with('status', 'Jika nomor terdaftar, kode OTP telah dikirim ke WhatsApp Anda.')
            ->with('reset_phone', $phone);
    }

    public function showReset(Request $request): View
    {
        return view('public.guardian.reset-password', [
            'phone' => (string) old('phone', session('reset_phone', '')),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'phone' => ['required', 'string', 'max:30'],
            'otp' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'otp.digits' => 'Kode OTP harus 6 angka.',
            'password.min' => 'Password baru minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi password tidak sama.',
        ]);

        $phone = Guardian::normalizePhone($validated['phone']);
        $guardian = Guardian::query()->active()->where('phone', $phone)->first();

        if ($guardian === null) {
            return $this->fail('Nomor HP atau kode OTP tidak sesuai.');
        }

        $key = $this->cacheKey($guardian);
        $payload = Cache::get($key);

        if (! is_array($payload) || ! isset($payload['hash'])) {
            return $this->fail('Kode OTP sudah kedaluwarsa. Minta kode baru.');
        }

        $attempts = (int) ($payload['attempts'] ?? 0) + 1;

        if ($attempts > self::OTP_MAX_ATTEMPTS) {
            Cache::forget($key);

            return $this->fail('Terlalu banyak percobaan. Minta kode OTP baru.');
        }

        if (! Hash::check($validated['otp'], (string) $payload['hash'])) {
            Cache::put($key, ['hash' => $payload['hash'], 'attempts' => $attempts], now()->addMinutes(self::OTP_TTL_MINUTES));

            return $this->fail('Kode OTP tidak sesuai. Sisa percobaan: '.(self::OTP_MAX_ATTEMPTS - $attempts).'.');
        }

        $guardian->forceFill([
            'password' => $validated['password'],
            'password_set_at' => now(),
        ])->save();

        Cache::forget($key);
        RateLimiter::clear('guardian-otp:'.$phone.'|'.$request->ip());

        return redirect()
            ->route('public.guardian.login')
            ->with('status', 'Password berhasil diperbarui. Silakan masuk dengan password baru.');
    }

    private function cacheKey(Guardian $guardian): string
    {
        return 'guardian-otp:'.$guardian->getKey();
    }

    private function otpMessage(Guardian $guardian, string $otp): string
    {
        return implode("\n", [
            'Assalamu\'alaikum Bapak/Ibu '.$guardian->name.'.',
            'Kode OTP untuk mengubah password portal orang tua Anda:',
            '',
            '*'.$otp.'*',
            '',
            'Kode berlaku '.self::OTP_TTL_MINUTES.' menit. Jangan bagikan kode ini kepada siapa pun.',
        ]);
    }

    private function fail(string $message): RedirectResponse
    {
        return back()
            ->withErrors(['otp' => $message])
            ->withInput(['phone' => request()->input('phone')]);
    }
}