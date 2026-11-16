<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Guardian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menjaga halaman portal orang tua.
 *
 * Akun yang dinonaktifkan atau dihapus harus langsung kehilangan akses, sama
 * seperti perlakuan pada portal siswa.
 */
class AuthPublicGuardian
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $guardianId = session('public_guardian_id');

        if (! $guardianId) {
            return redirect('/portal/orang-tua/login')->with('error', 'Silakan masuk terlebih dahulu.');
        }

        if (! Guardian::query()->active()->whereKey($guardianId)->exists()) {
            $request->session()->forget('public_guardian_id');
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/portal/orang-tua/login')
                ->with('error', 'Akun portal orang tua sudah tidak aktif. Hubungi admin sekolah.');
        }

        return $next($request);
    }
}
