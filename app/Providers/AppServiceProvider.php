<?php

namespace App\Providers;

use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->guardProductionConfiguration();

        // Akun nonaktif tidak boleh lolos pemeriksaan Gate mana pun. Penjagaan
        // utama ada di `User::hasPermissionTo()` (dipakai spatie sebagai sumber
        // keputusan Gate), ditambah `Gate::before` untuk ability berbasis policy.
        Gate::before(static function ($user): ?bool {
            return $user instanceof \App\Models\User && ! $user->isActive() ? false : null;
        });

        Livewire::addPersistentMiddleware([
            EnsureUserIsActive::class,
        ]);
    }

    private function guardProductionConfiguration(): void
    {
        if (! $this->app->environment('production')) {
            return;
        }

        if (config('app.debug')) {
            throw new RuntimeException('APP_DEBUG harus false saat APP_ENV=production.');
        }

        config(['session.secure' => true]);
    }
}
