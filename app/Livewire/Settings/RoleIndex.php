<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.app')]
#[Title('Role & Hak Akses')]
class RoleIndex extends Component
{
    public ?int $editingId = null;

    public string $roleName = '';

    /**
     * @var array<int, string>
     */
    public array $selected = [];

    public function edit(int $id): void
    {
        $this->authorize('roles.update');

        $role = Role::query()->findOrFail($id);

        if ($this->isOwnRole($role)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat mengubah hak akses role yang sedang Anda gunakan.');

            return;
        }

        if ($role->name === RoleName::SuperAdministrator->value
            && auth()->user()?->hasRole(RoleName::SuperAdministrator->value) !== true
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Super Administrator yang dapat mengubah role Super Administrator.');

            return;
        }

        $this->editingId = $role->id;
        $this->roleName = $role->name;
        $this->selected = $role->permissions()->pluck('name')->all();
    }

    /**
     * Role milik aktor sendiri dikunci untuk aktor non-Super Administrator,
     * agar pemegang `roles.update` tidak dapat menaikkan hak aksesnya sendiri.
     */
    private function isOwnRole(Role $role): bool
    {
        $actor = auth()->user();

        if ($actor === null || $actor->hasRole(RoleName::SuperAdministrator->value)) {
            return false;
        }

        return $actor->hasRole($role->name);
    }

    public function toggle(string $permission): void
    {
        $this->authorize('roles.update');

        $this->selected = in_array($permission, $this->selected, true)
            ? array_values(array_diff($this->selected, [$permission]))
            : [...$this->selected, $permission];
    }

    public function toggleGroup(string $prefix): void
    {
        $this->authorize('roles.update');

        $group = PermissionName::grouped()[$prefix] ?? [];
        $missing = array_values(array_diff($group, $this->selected));

        $this->selected = $missing === []
            ? array_values(array_diff($this->selected, $group))
            : array_values(array_unique([...$this->selected, ...$missing]));
    }

    public function selectAll(): void
    {
        $this->authorize('roles.update');

        $this->selected = PermissionName::values();
    }

    public function clearAll(): void
    {
        $this->authorize('roles.update');

        $this->selected = [];
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('roles.update');

        if ($this->editingId === null) {
            return;
        }

        $this->validate([
            'selected' => ['array'],
            'selected.*' => ['string', Rule::exists('permissions', 'name')],
        ]);

        $role = Role::query()->findOrFail($this->editingId);
        $actor = auth()->user();

        if ($this->isOwnRole($role)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat mengubah hak akses role Anda sendiri.');

            return;
        }

        if (! $this->actorMayGrant($actor, $this->selected)) {
            $this->dispatch('toast', type: 'error', message: 'Anda tidak dapat memberikan hak akses yang tidak Anda miliki.');

            return;
        }

        if ($role->name === RoleName::SuperAdministrator->value
            && auth()->user()?->hasRole(RoleName::SuperAdministrator->value) !== true
        ) {
            $this->dispatch('toast', type: 'error', message: 'Hanya Super Administrator yang dapat mengubah role Super Administrator.');

            return;
        }

        if ($role->name === RoleName::SuperAdministrator->value
            && count($this->selected) < count(PermissionName::values())
        ) {
            $this->dispatch('toast', type: 'error', message: 'Role Super Administrator harus memiliki seluruh hak akses.');

            return;
        }

        $role->syncPermissions($this->selected);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $logger->log('roles.updated', "Memperbarui hak akses role {$role->name}", $role);

        $this->dispatch('toast', type: 'success', message: "Hak akses role {$role->name} berhasil disimpan.");
    }

    /**
     * Aktor hanya boleh mendelegasikan permission yang ia miliki sendiri;
     * Super Administrator dikecualikan.
     *
     * @param  array<int, string>  $permissions
     */
    private function actorMayGrant(?User $actor, array $permissions): bool
    {
        if ($actor === null) {
            return false;
        }

        if ($actor->hasRole(RoleName::SuperAdministrator->value)) {
            return true;
        }

        return array_diff($permissions, $actor->getAllPermissions()->pluck('name')->all()) === [];
    }

    public function render(): View
    {
        $this->authorize('roles.view');

        $roles = Role::query()
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->get();

        return view('livewire.settings.role-index', [
            'roles' => $roles,
            'editableRoleNames' => $this->editableRoleNames(),
            'labels' => collect(RoleName::cases())->mapWithKeys(fn (RoleName $case): array => [$case->value => $case->label()])->all(),
            'groups' => $this->groupedPermissions(),
            'roleLabels' => collect(RoleName::cases())->mapWithKeys(fn (RoleName $case): array => [$case->value => $case->label()])->all(),
            'totalPermissions' => count(PermissionName::values()),
            'userCount' => User::query()->count(),
        ]);
    }

    /**
     * Role yang boleh dibuka di editor oleh aktor saat ini.
     *
     * @return array<int, string>
     */
    private function editableRoleNames(): array
    {
        return Role::query()
            ->orderBy('name')
            ->get()
            ->reject(fn (Role $role): bool => $this->isOwnRole($role))
            ->pluck('name')
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{label: string, permissions: array<int, array{name: string, action: string}>}>
     */
    private function groupedPermissions(): array
    {
        $labels = [
            'dashboard' => 'Dashboard',
            'users' => 'Pengguna',
            'roles' => 'Role & Hak Akses',
            'units' => 'Unit Pendidikan',
            'academic-years' => 'Tahun Ajaran',
            'school-classes' => 'Kelas',
            'students' => 'Siswa',
            'staff' => 'Petugas',
            'accounts' => 'Akun Keuangan',
            'payment-categories' => 'Kategori Pembayaran',
            'bank-accounts' => 'Rekening Bank',
            'bills' => 'Tagihan',
            'payments' => 'Pembayaran',
            'reports' => 'Laporan',
            'admissions' => 'PPDB',
            'settings' => 'Pengaturan',
            'backup' => 'Backup',
            'activity-logs' => 'Log Aktivitas',
        ];

        $groups = [];

        foreach (PermissionName::grouped() as $prefix => $names) {            $groups[$prefix] = [
                'label' => $labels[$prefix] ?? ucfirst($prefix),
                'permissions' => array_map(
                    static fn (string $name): array => [
                        'name' => $name,
                        'action' => ucfirst(explode('.', $name, 2)[1]),
                    ],
                    $names,
                ),
            ];
        }

        return $groups;
    }
}
