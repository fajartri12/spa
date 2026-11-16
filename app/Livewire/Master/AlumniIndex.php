<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\AlumniStatus;
use App\Enums\Gender;
use App\Models\Alumni;
use App\Models\Student;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\AlumniService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pengelolaan data alumni (lulusan) per unit pendidikan.
 *
 * Data dapat diisi manual untuk angkatan lama maupun ditarik otomatis dari
 * siswa yang sudah berstatus lulus.
 */
#[Layout('layouts.app')]
#[Title('Alumni')]
class AlumniIndex extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url(as: 'unit', history: true)]
    public string $unitFilter = '';

    #[Url(as: 'tahun-lulus', history: true)]
    public string $yearFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $unit_id = null;

    public string $nis = '';

    public string $nisn = '';

    public string $name = '';

    public string $gender = 'male';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public int $graduation_year = 0;

    public string $class_name = '';

    public string $major = '';

    public string $status = 'unknown';

    public string $occupation = '';

    public string $workplace = '';

    public string $higher_education = '';

    public string $notes = '';

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    public bool $showPullModal = false;

    /**
     * @var array<int, string>
     */
    public array $selectedStudents = [];

    public ?int $pull_unit_id = null;

    public int $pull_graduation_year = 0;

    public function mount(): void
    {
        $this->authorize('alumni.view');

        $this->graduation_year = (int) now()->year;
        $this->pull_graduation_year = (int) now()->year;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatedYearFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('alumni.create');

        $this->resetForm();
        $this->unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : null;
        $this->graduation_year = $this->yearFilter !== '' ? (int) $this->yearFilter : (int) now()->year;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('alumni.update');

        $alumni = Alumni::query()->findOrFail($id);

        $this->editingId = $alumni->id;
        $this->unit_id = (int) $alumni->unit_id;
        $this->nis = (string) $alumni->nis;
        $this->nisn = (string) $alumni->nisn;
        $this->name = $alumni->name;
        $this->gender = $alumni->gender?->value ?? Gender::Male->value;
        $this->birth_place = (string) $alumni->birth_place;
        $this->birth_date = $alumni->birth_date?->toDateString() ?? '';
        $this->phone = (string) $alumni->phone;
        $this->email = (string) $alumni->email;
        $this->address = (string) $alumni->address;
        $this->graduation_year = (int) $alumni->graduation_year;
        $this->class_name = (string) $alumni->class_name;
        $this->major = (string) $alumni->major;
        $this->status = $alumni->status?->value ?? AlumniStatus::Unknown->value;
        $this->occupation = (string) $alumni->occupation;
        $this->workplace = (string) $alumni->workplace;
        $this->higher_education = (string) $alumni->higher_education;
        $this->notes = (string) $alumni->notes;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'alumni.update' : 'alumni.create');

        if ($this->unit_id === 0) {
            $this->unit_id = null;
        }

        $data = $this->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'nis' => ['nullable', 'string', 'max:30'],
            'nisn' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:120'],
            'gender' => ['required', 'string', 'in:male,female'],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:500'],
            'graduation_year' => ['required', 'integer', 'min:1950', 'max:2100'],
            'class_name' => ['nullable', 'string', 'max:60'],
            'major' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'string', 'in:'.implode(',', array_keys(AlumniStatus::options()))],
            'occupation' => ['nullable', 'string', 'max:120'],
            'workplace' => ['nullable', 'string', 'max:150'],
            'higher_education' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'status.in' => 'Status alumni tidak dikenali.',
        ]);

        // Kolom teks opsional dikirim sebagai string kosong; ubah ke null agar
        // tidak tersimpan sebagai '' dan tampil sebagai teks kosong di laporan.
        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        if ($isEditing) {
            $alumni = Alumni::query()->findOrFail($this->editingId);
            $alumni->update($data);
            $logger->log('alumni.updated', "Memperbarui data alumni {$alumni->name}", $alumni);
            $message = 'Data alumni berhasil diperbarui.';
        } else {
            $data['created_by'] = auth()->id();

            // Bila NIS dipakai dan cocok dengan satu siswa pada unit yang sama,
            // tautkan otomatis agar data tidak terduplikasi.
            $data['student_id'] = $this->resolveStudentId($data['unit_id'], $data['nis'] ?? null);

            $alumni = Alumni::query()->create($data);
            $logger->log('alumni.created', "Menambahkan alumni {$alumni->name}", $alumni);
            $message = 'Data alumni berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    /**
     * Cocokkan siswa berdasarkan nomor induk pada unit yang sama.
     */
    private function resolveStudentId(int $unitId, ?string $nis): ?int
    {
        $nis = trim((string) $nis);

        if ($nis === '') {
            return null;
        }

        $studentId = Student::query()
            ->where('unit_id', $unitId)
            ->where('nis', $nis)
            ->value('id');

        if ($studentId === null) {
            return null;
        }

        // Satu siswa hanya boleh tertaut ke satu alumni.
        $taken = Alumni::query()->withTrashed()->where('student_id', $studentId)->exists();

        return $taken ? null : (int) $studentId;
    }

    public function openPull(): void
    {
        $this->authorize('alumni.create');

        $this->reset(['selectedStudents']);
        $this->pull_unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : null;
        $this->pull_graduation_year = $this->yearFilter !== '' ? (int) $this->yearFilter : (int) now()->year;
        $this->showPullModal = true;
    }

    public function pullFromGraduates(AlumniService $service): void
    {
        $this->authorize('alumni.create');

        $this->validate([
            'selectedStudents' => ['required', 'array', 'min:1'],
            'pull_graduation_year' => ['required', 'integer', 'min:1950', 'max:2100'],
        ], [
            'selectedStudents.required' => 'Pilih minimal satu siswa lulus.',
            'selectedStudents.min' => 'Pilih minimal satu siswa lulus.',
        ]);

        $result = $service->pullMany(
            array_map('intval', $this->selectedStudents),
            auth()->user(),
            (int) $this->pull_graduation_year,
        );

        $this->showPullModal = false;
        $this->reset(['selectedStudents', 'pull_unit_id']);

        if ($result['created'] === 0) {
            $this->dispatch('toast', type: 'error', message: $result['errors'][0] ?? 'Tidak ada data alumni yang dibuat.');

            return;
        }

        $message = "{$result['created']} alumni berhasil dibuat";
        $message .= $result['skipped'] > 0 ? ", {$result['skipped']} dilewati." : '.';

        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('alumni.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('alumni.delete');

        if ($this->deletingId === null) {
            return;
        }

        $alumni = Alumni::query()->findOrFail($this->deletingId);
        $alumniName = $alumni->name;

        $alumni->delete();
        $logger->log('alumni.deleted', "Menghapus alumni {$alumniName}", $alumni);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Alumni {$alumniName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'unitFilter', 'yearFilter', 'statusFilter']);
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->reset([
            'editingId', 'unit_id', 'nis', 'nisn', 'name', 'birth_place', 'birth_date',
            'phone', 'email', 'address', 'class_name', 'major', 'occupation', 'workplace',
            'higher_education', 'notes',
        ]);

        $this->gender = Gender::Male->value;
        $this->status = AlumniStatus::Unknown->value;
        $this->graduation_year = (int) now()->year;
        $this->resetErrorBag();
    }

    public function render(AlumniService $service): View
    {
        $this->authorize('alumni.view');

        $alumni = Alumni::query()
            ->search($this->search)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->yearFilter !== '', fn ($query) => $query->where('graduation_year', (int) $this->yearFilter))
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
            ->ordered()
            ->with(['unit:id,name'])
            ->paginate(Pagination::perPage());

        // Ringkasan mengikuti filter unit agar angka konsisten dengan tabel.
        $summaryQuery = Alumni::query()
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter));

        $statusCounts = [];

        foreach (AlumniStatus::cases() as $case) {
            $statusCounts[$case->value] = (clone $summaryQuery)->where('status', $case->value)->count();
        }

        return view('livewire.master.alumni-index', [
            'alumni' => $alumni,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'years' => Alumni::query()
                ->select('graduation_year')
                ->distinct()
                ->orderByDesc('graduation_year')
                ->pluck('graduation_year', 'graduation_year')
                ->all(),
            'genders' => Gender::options(),
            'statuses' => AlumniStatus::options(),
            'totalAlumni' => (clone $summaryQuery)->count(),
            'statusCounts' => $statusCounts,
            'candidates' => $this->showPullModal
                ? $service->candidates($this->pull_unit_id)
                : collect(),
        ]);
    }
}
