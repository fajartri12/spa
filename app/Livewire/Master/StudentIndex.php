<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Siswa')]
class StudentIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $classFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $unit_id = null;

    public ?int $school_class_id = null;

    public string $major = '';

    public string $nis = '';

    public string $nisn = '';

    public string $name = '';

    public string $gender = 'male';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $religion = '';

    public string $address = '';

    public string $phone = '';

    public string $email = '';

    public string $parent_name = '';

    public string $parent_phone = '';

    public string $parent_relation = '';

    public int $entry_year = 0;

    public string $status = 'active';

    public string $notes = '';

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'classFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->entry_year = (int) now()->year;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->classFilter = '';
        $this->resetPage();
    }

    public function updatedClassFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitId(): void
    {
        // Reset kelas bila unit diubah agar tidak tersimpan kelas dari unit lain.
        if ($this->school_class_id !== null) {
            $belongsToUnit = SchoolClass::query()
                ->whereKey($this->school_class_id)
                ->where('unit_id', $this->unit_id)
                ->exists();

            if (! $belongsToUnit) {
                $this->school_class_id = null;
            }
        }
    }

    public function create(): void
    {
        $this->authorize('students.create');

        $this->resetForm();
        $this->unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : null;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('students.update');

        $student = Student::query()->findOrFail($id);

        $this->editingId = $student->id;
        $this->unit_id = $student->unit_id !== null ? (int) $student->unit_id : null;
        $this->school_class_id = $student->school_class_id !== null ? (int) $student->school_class_id : null;
        $this->nis = (string) $student->nis;
        $this->nisn = (string) $student->nisn;
        $this->name = $student->name;
        $this->gender = $student->gender?->value ?? Gender::Male->value;
        $this->birth_place = (string) $student->birth_place;
        $this->birth_date = $student->birth_date?->toDateString() ?? '';
        $this->religion = (string) $student->religion;
        $this->address = (string) $student->address;
        $this->phone = (string) $student->phone;
        $this->email = (string) $student->email;
        $this->parent_name = (string) $student->parent_name;
        $this->parent_phone = (string) $student->parent_phone;
        $this->parent_relation = (string) $student->parent_relation;
        $this->entry_year = (int) ($student->entry_year ?? now()->year);
        $this->status = $student->status?->value ?? StudentStatus::Active->value;
        $this->notes = (string) $student->notes;
        $this->major = (string) $student->major;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'students.update' : 'students.create');

        // Kolom opsional dikosongkan lewat form sebagai string kosong; ubah ke null
        // agar kolom date/unique tidak menerima nilai '' (invalid di MySQL).
        foreach (['unit_id', 'school_class_id'] as $key) {
            if ($this->{$key} === 0) {
                $this->{$key} = null;
            }
        }

        $data = $this->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'school_class_id' => ['nullable', 'integer', Rule::exists('school_classes', 'id')->where('unit_id', $this->unit_id)],
            'nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')->ignore($this->editingId)],
            'nisn' => ['nullable', 'string', 'max:30', Rule::unique('students', 'nisn')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'birth_place' => ['nullable', 'string', 'max:80'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'religion' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'phone' => ['nullable', 'string', 'max:25'],
            'email' => ['nullable', 'email', 'max:120'],
            'parent_name' => ['nullable', 'string', 'max:120'],
            'parent_phone' => ['nullable', 'string', 'max:25'],
            'parent_relation' => ['nullable', 'string', 'max:30'],
            'entry_year' => ['required', 'integer', 'min:1990', 'max:2100'],
            'status' => ['required', Rule::enum(StudentStatus::class)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'major' => ['nullable', 'string', 'max:60'],
        ], [
            'nis.unique' => 'NIS sudah digunakan oleh siswa lain.',
            'nisn.unique' => 'NISN sudah digunakan oleh siswa lain.',
            'school_class_id.exists' => 'Kelas yang dipilih tidak berada pada unit pendidikan yang sama.',
        ]);

        // Normalisasi string kosong menjadi null pada data tervalidasi agar kolom nullable tidak menyimpan ''
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        // Pastikan major tetap null jika dikosongkan
        if (isset($data['major']) && trim($data['major']) === '') {
            $data['major'] = null;
        }

        if ($isEditing) {
            $student = Student::query()->findOrFail($this->editingId);
            $student->update($data);
            $logger->log('students.updated', "Memperbarui data siswa {$student->name}", $student);
            $message = 'Data siswa berhasil diperbarui.';
        } else {
            $student = Student::query()->create($data);
            $logger->log('students.created', "Menambahkan siswa {$student->name}", $student);
            $message = 'Siswa baru berhasil ditambahkan.';

            // Setiap siswa baru mendapat password portal sendiri agar satu
            // password tidak berlaku untuk semua siswa (AGENTS.md bagian 9).
            $plainPassword = $this->issuePortalPassword($student);
            $message .= ' Password portal: '.$plainPassword;
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function resetPortalPassword(int $id, ActivityLogger $logger): void
    {
        $this->authorize('students.update');

        $student = Student::query()->findOrFail($id);
        $plainPassword = $this->issuePortalPassword($student);

        $logger->log('students.portal-password-reset', "Reset password portal siswa {$student->name}", $student);

        $this->dispatch('toast', type: 'success', message: "Password portal {$student->name}: {$plainPassword}");
    }

    /**
     * Terbitkan password portal baru dan kembalikan versi terbaca sekali saja.
     */
    private function issuePortalPassword(Student $student): string
    {
        $plain = Student::generatePortalPassword();

        $student->forceFill([
            'portal_password' => $plain,
            'portal_password_set_at' => now(),
        ])->save();

        return $plain;
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('students.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('students.delete');

        if ($this->deletingId === null) {
            return;
        }

        $student = Student::query()->findOrFail($this->deletingId);
        $studentName = $student->name;

        $student->delete();
        $logger->log('students.deleted', "Menghapus siswa {$studentName}", $student);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Siswa {$studentName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->classFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->unit_id = null;
        $this->school_class_id = null;
        $this->nis = '';
        $this->nisn = '';
        $this->name = '';
        $this->gender = Gender::Male->value;
        $this->birth_place = '';
        $this->birth_date = '';
        $this->religion = '';
        $this->address = '';
        $this->phone = '';
        $this->email = '';
        $this->parent_name = '';
        $this->parent_phone = '';
        $this->parent_relation = '';
        $this->entry_year = (int) now()->year;
        $this->status = StudentStatus::Active->value;
        $this->notes = '';
        $this->major = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('students.view');

        $students = Student::query()
            ->search($this->search)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->classFilter !== '', fn ($query) => $query->where('school_class_id', (int) $this->classFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->ordered()
            ->with(['unit:id,name', 'schoolClass:id,name'])
            ->paginate(Pagination::perPage());

        // Daftar kelas untuk filter mengikuti unit terpilih (atau semua unit bila belum dipilih).
        $filterClasses = SchoolClass::query()
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->ordered()
            ->pluck('name', 'id')
            ->all();

        // Daftar kelas pada form mengikuti unit yang dipilih di form.
        $formClasses = SchoolClass::query()
            ->when($this->unit_id !== null, fn ($query) => $query->where('unit_id', $this->unit_id))
            ->ordered()
            ->pluck('name', 'id')
            ->all();

        return view('livewire.master.student-index', [
            'students' => $students,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'filterClasses' => $filterClasses,
            'formClasses' => $formClasses,
            'genders' => Gender::options(),
            'statuses' => StudentStatus::options(),
            'activeYear' => AcademicYear::query()->active()->value('name'),
        ]);
    }
}
