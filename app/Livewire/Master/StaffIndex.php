<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\Gender;
use App\Enums\StaffPosition;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Petugas')]
class StaffIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $positionFilter = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $user_id = null;

    public ?int $unit_id = null;

    public string $nip = '';

    public string $name = '';

    public string $gender = 'male';

    public string $position = 'guru';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public bool $is_active = true;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'positionFilter' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPositionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('staff.create');

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('staff.update');

        $staff = StaffProfile::query()->findOrFail($id);

        $this->editingId = $staff->id;
        $this->user_id = $staff->user_id !== null ? (int) $staff->user_id : null;
        $this->unit_id = $staff->unit_id !== null ? (int) $staff->unit_id : null;
        $this->nip = (string) $staff->nip;
        $this->name = $staff->name;
        $this->gender = $staff->gender?->value ?? Gender::Male->value;
        $this->position = $staff->position?->value ?? StaffPosition::Teacher->value;
        $this->phone = (string) $staff->phone;
        $this->email = (string) $staff->email;
        $this->address = (string) $staff->address;
        $this->is_active = (bool) $staff->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'staff.update' : 'staff.create');

        if ($this->user_id === 0) {
            $this->user_id = null;
        }

        if ($this->unit_id === 0) {
            $this->unit_id = null;
        }

        $data = $this->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('staff_profiles', 'user_id')->ignore($this->editingId)],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('staff_profiles', 'nip')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'position' => ['required', Rule::enum(StaffPosition::class)],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ], [
            'user_id.unique' => 'Akun pengguna tersebut sudah terhubung dengan petugas lain.',
            'nip.unique' => 'NIP sudah digunakan oleh petugas lain.',
        ]);

        if ($isEditing) {
            $staff = StaffProfile::query()->findOrFail($this->editingId);
            $staff->update($data);
            $logger->log('staff.updated', "Memperbarui data petugas {$staff->name}", $staff);
            $message = 'Data petugas berhasil diperbarui.';
        } else {
            $staff = StaffProfile::query()->create($data);
            $logger->log('staff.created', "Menambahkan petugas {$staff->name}", $staff);
            $message = 'Petugas baru berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('staff.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('staff.delete');

        if ($this->deletingId === null) {
            return;
        }

        $staff = StaffProfile::query()->findOrFail($this->deletingId);
        $staffName = $staff->name;

        $staff->delete();
        $logger->log('staff.deleted', "Menghapus petugas {$staffName}", $staff);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Petugas {$staffName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->positionFilter = '';
        $this->unitFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->user_id = null;
        $this->unit_id = null;
        $this->nip = '';
        $this->name = '';
        $this->gender = Gender::Male->value;
        $this->position = StaffPosition::Teacher->value;
        $this->phone = '';
        $this->email = '';
        $this->address = '';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('staff.view');

        $staffMembers = StaffProfile::query()
            ->search($this->search)
            ->when($this->positionFilter !== '', fn ($query) => $query->where('position', $this->positionFilter))
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->ordered()
            ->with(['unit:id,name', 'user:id,name,email'])
            ->paginate(Pagination::perPage());

        return view('livewire.master.staff-index', [
            'staffMembers' => $staffMembers,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'positions' => StaffPosition::options(),
            'genders' => Gender::options(),
            'users' => User::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
