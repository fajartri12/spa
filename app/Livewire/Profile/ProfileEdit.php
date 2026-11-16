<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Profil Saya')]
class ProfileEdit extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $username = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $user = $this->user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->phone = (string) $user->phone;
        $this->username = (string) $user->username;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        $user = $this->user();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['nullable', 'string', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-\s()]+$/'],
        ];
    }

    public function updateProfile(ActivityLogger $logger): void
    {
        $data = $this->validate();

        $user = $this->user();
        $user->fill($data)->save();

        $logger->log('profile.updated', 'Memperbarui profil pengguna', $user);

        $this->dispatch('toast', type: 'success', message: 'Profil berhasil diperbarui.');
    }

    public function updatePassword(ActivityLogger $logger): void
    {
        $this->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($this->current_password, $this->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Kata sandi saat ini tidak sesuai.',
            ]);
        }

        $user = $this->user();
        $user->update(['password' => $this->password]);

        $logger->log('profile.password-updated', 'Mengubah kata sandi', $user);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('toast', type: 'success', message: 'Kata sandi berhasil diubah.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    public function render(): View
    {
        return view('livewire.profile.profile-edit', [
            'user' => $this->user(),
        ]);
    }
}
