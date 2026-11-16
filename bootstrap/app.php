<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Webhook payment gateway tidak membawa token CSRF; keamanannya
        // bertumpu pada verifikasi tanda tangan penyedia.
        $middleware->validateCsrfTokens(except: [
            'webhook/pembayaran/*',
        ]);

        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'auth.public.student' => \App\Http\Middleware\AuthPublicStudent::class,
            'auth.public.guardian' => \App\Http\Middleware\AuthPublicGuardian::class,
            'feature.student-savings' => \App\Http\Middleware\FeatureStudentSavings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
