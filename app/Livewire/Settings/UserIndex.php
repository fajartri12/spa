<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\User;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\Models\Role;

#[Layout('layouts.app')]
#[Title('Pengguna')]
class UserIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $username = '';

    public string $phone = '';

    public string $status = 'active';

    public string $password = '';

    public string $role = '';

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'roleFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('users.create');

        $this->resetForm();
        $this->status = UserStatus::Active->value;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('users.update');

        $user = User::query()->findOrFail($id);

        if (! $this->actorMayManage($user)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengelola akun ber-role istimewa.');

            return;
        }

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->username = (string) $user->username;
        $this->phone = (string) $user->phone;
        $this->status = $user->status->value;
        $this->password = '';
        $this->role = (string) $user->getRoleNames()->first();

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'users.update' : 'users.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'email',
                'max:191',
                Rule::unique('users', 'email')->ignore($this->editingId)->withoutTrashed(),
            ],
            'username' => [
                'nullable',
                'string',
                'max:60',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($this->editingId)->withoutTrashed(),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::enum(UserStatus::class)],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
            'password' => [
                $isEditing ? 'nullable' : 'required',
                'string',
                Password::min(8),
            ],
        ]);

        unset($data['password'], $data['role']);

        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        $actor = auth()->user();
        $actorIsSuper = $actor !== null && $actor->hasRole(RoleName::SuperAdministrator->value);

        if (in_array($this->role, array_map(static fn (RoleName $role): string => $role->value, RoleName::privileged()), true)
            && ! $actorIsSuper
        ) {
            $this->addError('role', 'Hanya Super Administrator yang dapat memberikan role istimewa.');

            return;
        }

        if ($isEditing) {
            $user = User::query()->findOrFail($this->editingId);

            if (! $this->actorMayManage($user)) {
                $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak mengelola akun ber-role istimewa.');

                return;
            }

            if ($user->id === auth()->id() && $this->role !== (string) $user->getRoleNames()->first()) {
                $this->addError('role', 'Anda tidak dapat mengubah role akun yang sedang Anda gunakan.');

                return;
            }

            if ($user->id === auth()->id() && $this->status !== UserStatus::Active->value) {
                $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat menonaktifkan akun yang sedang Anda gunakan.');

                return;
            }

            $user->update($data);
            $user->syncRoles([$this->role]);
            $logger->log('users.updated', "Memperbarui pengguna {$user->name}", $user);
            $message = 'Data pengguna berhasil diperbarui.';
        } else {
            $user = User::query()->create($data);
            $user->syncRoles([$this->role]);
            $logger->log('users.created', "Menambahkan pengguna {$user->name}", $user);
            $message = 'Pengguna baru berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('users.delete');

        $target = User::query()->findOrFail($id);

        if (! $this->actorMayManage($target)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak menghapus akun ber-role istimewa.');

            return;
        }

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('users.delete');

        if ($this->deletingId === null) {
            return;
        }

        if ($this->deletingId === auth()->id()) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat menghapus akun yang sedang Anda gunakan.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $user = User::query()->findOrFail($this->deletingId);

        if (! $this->actorMayManage($user)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak berhak menghapus akun ber-role istimewa.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $userName = $user->name;
        $user->delete();
        $logger->log('users.deleted', "Menghapus pengguna {$userName}", $user);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Pengguna {$userName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->email = '';
        $this->username = '';
        $this->phone = '';
        $this->status = UserStatus::Active->value;
        $this->password = '';
        $this->role = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('users.view');

        $users = User::query()
            ->with('roles:id,name')
            ->search($this->search)
            ->when($this->roleFilter !== '', fn ($query) => $query->role($this->roleFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->orderBy('name')
            ->paginate(Pagination::perPage());

        return view('livewire.settings.user-index', [
            'users' => $users,
            'roles' => $this->roleOptions(),
            'roleLabels' => collect(RoleName::cases())->mapWithKeys(fn (RoleName $case): array => [$case->value => $case->label()])->all(),
            'statuses' => collect(UserStatus::cases())->mapWithKeys(fn (UserStatus $case): array => [$case->value => $case->label()])->all(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function roleOptions(): array
    {
        $labels = collect(RoleName::cases())->mapWithKeys(fn (RoleName $case): array => [$case->value => $case->label()]);

        $actor = auth()->user();
        $actorIsSuper = $actor !== null && $actor->hasRole(RoleName::SuperAdministrator->value);

        $privileged = array_map(static fn (RoleName $role): string => $role->value, RoleName::privileged());

        return Role::query()
            ->when(! $actorIsSuper, fn ($query) => $query->whereNotIn('name', $privileged))
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => $labels[$name] ?? $name])
            ->all();
    }

    /**
     * Akun ber-role istimewa hanya boleh dikelola oleh Super Administrator.
     */
    private function actorMayManage(User $target): bool
    {
        $privileged = array_map(static fn (RoleName $role): string => $role->value, RoleName::privileged());

        if (! $target->hasAnyRole($privileged)) {
            return true;
        }

        return auth()->user()?->hasRole(RoleName::SuperAdministrator->value) === true;
    }
}
