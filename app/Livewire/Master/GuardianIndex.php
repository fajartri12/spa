<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\GuardianRelation;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\GuardianService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Orang Tua / Wali')]
class GuardianIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $relation = 'guardian';

    public string $phone = '';

    public string $email = '';

    public string $occupation = '';

    public string $address = '';

    public bool $is_active = true;

    /**
     * @var array<int, int>
     */
    public array $selectedStudents = [];

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    public ?string $generatedPassword = null;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
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
        $this->authorize('guardians.create');

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('guardians.update');

        $guardian = Guardian::query()->with('students:id')->findOrFail($id);

        $this->editingId = $guardian->id;
        $this->name = $guardian->name;
        $this->relation = $guardian->relation?->value ?? 'guardian';
        $this->phone = $guardian->phone;
        $this->email = (string) $guardian->email;
        $this->occupation = (string) $guardian->occupation;
        $this->address = (string) $guardian->address;
        $this->is_active = (bool) $guardian->is_active;
        $this->selectedStudents = $guardian->students->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $this->generatedPassword = null;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger, GuardianService $guardians): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'guardians.update' : 'guardians.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'relation' => ['required', Rule::enum(GuardianRelation::class)],
            'phone' => [
                'required',
                'string',
                'max:30',
                Rule::unique('guardians', 'phone')->ignore($this->editingId),
            ],
            'email' => ['nullable', 'email', 'max:150'],
            'occupation' => ['nullable', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
            'selectedStudents' => ['array'],
            'selectedStudents.*' => ['integer', 'exists:students,id'],
        ], [
            'phone.unique' => 'Nomor HP tersebut sudah dipakai akun orang tua lain.',
        ]);

        $studentIds = $data['selectedStudents'] ?? [];
        unset($data['selectedStudents']);

        if ($isEditing) {
            $guardian = Guardian::query()->findOrFail($this->editingId);
            $guardian->update($data);
            $guardians->syncStudents($guardian, $studentIds);
            $logger->log('guardians.updated', "Memperbarui data wali {$guardian->name}", $guardian);
            $message = 'Data orang tua berhasil diperbarui.';
        } else {
            $guardian = Guardian::query()->create($data);
            $guardians->syncStudents($guardian, $studentIds);
            $logger->log('guardians.created', "Menambahkan wali {$guardian->name}", $guardian);
            $message = 'Data orang tua berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * Terbitkan password portal baru. Password hanya ditampilkan sekali pada modal.
     */
    public function issuePassword(int $id, ActivityLogger $logger, GuardianService $guardians): void
    {
        $this->authorize('guardians.update');

        $guardian = Guardian::query()->findOrFail($id);
        $plain = $guardians->issuePassword($guardian);

        $logger->log('guardians.password-issued', "Menerbitkan password portal untuk wali {$guardian->name}", $guardian);

        $this->generatedPassword = $plain;
        $this->editingId = $guardian->id;
        $this->showModal = true;
        $this->dispatch('toast', type: 'success', message: 'Password portal berhasil diterbitkan. Catat dan sampaikan kepada wali.');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('guardians.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('guardians.delete');

        if ($this->deletingId === null) {
            return;
        }

        $guardian = Guardian::query()->findOrFail($this->deletingId);
        $name = $guardian->name;

        $guardian->students()->detach();
        $guardian->delete();

        $logger->log('guardians.deleted', "Menghapus wali {$name}", $guardian);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Data wali {$name} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->relation = 'guardian';
        $this->phone = '';
        $this->email = '';
        $this->occupation = '';
        $this->address = '';
        $this->is_active = true;
        $this->selectedStudents = [];
        $this->generatedPassword = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('guardians.view');

        $guardians = Guardian::query()
            ->search($this->search)
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->unitFilter !== '', fn ($query) => $query->whereHas(
                'students',
                fn ($student) => $student->where('unit_id', (int) $this->unitFilter),
            ))
            ->with(['students:id,name,nis,unit_id', 'students.unit:id,name'])
            ->orderBy('name')
            ->paginate(Pagination::perPage());

        return view('livewire.master.guardian-index', [
            'guardians' => $guardians,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'relations' => GuardianRelation::options(),
            'students' => $this->availableStudents(),
        ]);
    }

    /**
     * Daftar siswa yang dapat ditautkan pada modal.
     *
     * @return array<int, string>
     */
    private function availableStudents(): array
    {
        if (! $this->showModal) {
            return [];
        }

        return Student::query()
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->ordered()
            ->limit(500)
            ->get(['id', 'name', 'nis'])
            ->mapWithKeys(static fn (Student $student): array => [
                $student->id => "{$student->name} ({$student->nis})",
            ])
            ->all();
    }
}
