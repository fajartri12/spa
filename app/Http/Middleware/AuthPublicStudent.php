<?php

namespace App\Http\Middleware;

use App\Enums\StudentStatus;
use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Symfony\Component\HttpFoundation\Response;

class AuthPublicStudent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $studentId = session('public_student_id');

        if (! $studentId) {
            return Redirect::to('/portal/login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if (! Student::query()->whereKey($studentId)->where('status', StudentStatus::Active->value)->exists()) {
            $request->session()->forget('public_student_id');
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Redirect::to('/portal/login')->with('error', 'Akun portal sudah tidak aktif. Hubungi admin sekolah.');
        }

        return $next($request);
    }
}
