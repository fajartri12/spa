<?php

namespace App\Http\Middleware;

use App\Models\AppSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FeatureStudentSavings
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (AppSetting::get('feature_student_savings_enabled', '0') !== '1') {
            abort(403, 'Fitur Tabungan Siswa sedang dinonaktifkan.');
        }

        return $next($request);
    }
}
