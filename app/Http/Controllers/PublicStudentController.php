<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\StudentStatus;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PublicStudentController extends Controller
{
    public const SESSION_KEY = 'public_student_id';

    public function login(): View
    {
        return view('public.student.login');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'nisn' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = 'portal-login:'.strtolower($validated['nisn']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'nisn' => 'Terlalu banyak percobaan masuk. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $student = Student::query()
            ->where('status', StudentStatus::Active->value)
            ->where(fn ($query) => $query->where('nisn', $validated['nisn'])->orWhere('nis', $validated['nisn']))
            ->first();

        if (! $student || ! $this->passwordMatches($student, $validated['password'])) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'nisn' => 'Nomor induk atau password tidak sesuai.',
            ]);
        }

        RateLimiter::clear($throttleKey);

        $request->session()->regenerate();
        session([self::SESSION_KEY => $student->id]);

        return redirect()->intended('/portal/siswa');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/portal/login');
    }

    /**
     * Password portal harus milik siswa itu sendiri; tidak ada password bersama.
     *
     * Akun tanpa password belum diaktivasi dan harus diterbitkan lewat
     * `php artisan students:issue-portal-passwords`.
     */
    private function passwordMatches(Student $student, string $password): bool
    {
        if (! $student->hasPortalPassword()) {
            return false;
        }

        return Hash::check($password, (string) $student->portal_password);
    }
}
