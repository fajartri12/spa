<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Enums\RoleName;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.auth')]
class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::check()) {
            $this->redirectRoute('dashboard');
        }
    }

    /**
     * Isi formulir dengan kredensial akun demo terpilih.
     */
    public function useDemoAccount(string $email): void
    {
        $account = collect($this->demoAccounts())->firstWhere('email', $email);

        if ($account === null) {
            return;
        }

        $this->resetValidation();
        $this->email = $account->email;
        $this->password = $account->password;
    }

    /**
     * Daftar akun demo yang aman ditampilkan pada environment saat ini.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public function demoAccounts(): \Illuminate\Support\Collection
    {
        if (app()->isProduction()) {
            return collect();
        }

        return collect(config('demo.accounts', []))
            ->filter(fn (array $account): bool => app()->environment($account['can'] ?? []))
            ->map(fn (array $account): object => (object) [
                'role' => $account['role'] instanceof RoleName
                    ? $account['role']->label()
                    : (string) $account['role'],
                'email' => (string) $account['email'],
                'password' => (string) $account['password'],
            ])
            ->values();
    }

    public function login(ActivityLogger $logger): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $credentials = [
            'email' => $this->email,
            'password' => $this->password,
            'status' => 'active',
        ];

        if (! Auth::attempt($credentials, $this->remember)) {
            RateLimiter::hit($this->throttleKey(), 60);

            $this->password = '';

            throw ValidationException::withMessages([
                'email' => 'Email atau kata sandi tidak sesuai, atau akun tidak aktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        session()->regenerate();

        $logger->login(Auth::user());

        $this->redirectIntended(route('dashboard'));
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => "Terlalu banyak percobaan masuk. Coba lagi dalam {$seconds} detik.",
        ]);
    }

    private function throttleKey(): string
    {
        return mb_strtolower($this->email) . '|' . request()->ip();
    }

    public function render(): View
    {
        return view('livewire.auth.login', [
            'demoAccounts' => $this->demoAccounts(),
        ]);
    }
}
