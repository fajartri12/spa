<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
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
#[Title('Kelas')]
class SchoolClassIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $yearFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $unit_id = null;

    public ?int $academic_year_id = null;

    public string $name = '';

    public string $level = '';

    public string $homeroom_teacher = '';

    public int $capacity = 0;

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'yearFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->yearFilter = (string) (AcademicYear::query()->active()->value('id') ?? '');
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

    public function create(): void
    {
        $this->authorize('school-classes.create');

        $this->resetForm();
        $this->unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : Unit::query()->active()->value('id');
        $this->academic_year_id = $this->yearFilter !== ''
            ? (int) $this->yearFilter
            : AcademicYear::query()->active()->value('id');
        $this->sort_order = ((int) SchoolClass::query()->max('sort_order')) + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('school-classes.update');

        $class = SchoolClass::query()->findOrFail($id);

        $this->editingId = $class->id;
        $this->unit_id = (int) $class->unit_id;
        $this->academic_year_id = (int) $class->academic_year_id;
        $this->name = $class->name;
        $this->level = (string) $class->level;
        $this->homeroom_teacher = (string) $class->homeroom_teacher;
        $this->capacity = (int) $class->capacity;
        $this->sort_order = (int) $class->sort_order;
        $this->is_active = (bool) $class->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'school-classes.update' : 'school-classes.create');

        // Pastikan foreign key yang kosong dikonversi menjadi null agar validasi exists() berjalan benar.
        $this->unit_id = $this->unit_id !== null && $this->unit_id !== 0 ? $this->unit_id : null;
        $this->academic_year_id = $this->academic_year_id !== null && $this->academic_year_id !== 0 ? $this->academic_year_id : null;

        $data = $this->validate([
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('school_classes', 'name')
                    ->where('unit_id', $this->unit_id)
                    ->where('academic_year_id', $this->academic_year_id)
                    ->ignore($this->editingId),
            ],
            'level' => ['nullable', 'string', 'max:40'],
            'homeroom_teacher' => ['nullable', 'string', 'max:120'],
            'capacity' => ['required', 'integer', 'min:0', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'name.unique' => 'Kelas dengan nama tersebut sudah ada pada unit dan tahun ajaran yang dipilih.',
        ]);

        if ($isEditing) {
            $class = SchoolClass::query()->findOrFail($this->editingId);
            $class->update($data);
            $logger->log('school-classes.updated', "Memperbarui kelas {$class->name}", $class);
            $message = 'Data kelas berhasil diperbarui.';
        } else {
            $class = SchoolClass::query()->create($data);
            $logger->log('school-classes.created', "Menambahkan kelas {$class->name}", $class);
            $message = 'Kelas baru berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('school-classes.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('school-classes.delete');

        if ($this->deletingId === null) {
            return;
        }

        $class = SchoolClass::query()->withCount('students')->findOrFail($this->deletingId);

        if ($class->students_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Kelas tidak dapat dihapus karena masih memiliki siswa terdaftar.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $className = $class->name;
        $class->delete();
        $logger->log('school-classes.deleted', "Menghapus kelas {$className}", $class);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Kelas {$className} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->yearFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->unit_id = null;
        $this->academic_year_id = null;
        $this->name = '';
        $this->level = '';
        $this->homeroom_teacher = '';
        $this->capacity = 0;
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('school-classes.view');

        $classes = SchoolClass::query()
            ->search($this->search)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->yearFilter !== '', fn ($query) => $query->where('academic_year_id', (int) $this->yearFilter))
            ->ordered()
            ->with(['unit:id,name', 'academicYear:id,name'])
            ->withCount('students')
            ->paginate(Pagination::perPage());

        return view('livewire.master.school-class-index', [
            'classes' => $classes,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
        ]);
    }
}
