<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Models\AcademicYear;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tahun Ajaran')]
class AcademicYearIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $start_date = '';

    public string $end_date = '';

    public bool $is_active = false;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('academic-years.create');

        $this->resetForm();
        $this->start_date = now()->startOfYear()->toDateString();
        $this->end_date = now()->endOfYear()->toDateString();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('academic-years.update');

        $year = AcademicYear::query()->findOrFail($id);

        $this->editingId = $year->id;
        $this->name = $year->name;
        $this->start_date = $year->start_date->toDateString();
        $this->end_date = $year->end_date->toDateString();
        $this->is_active = (bool) $year->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'academic-years.update' : 'academic-years.create');

        $data = $this->validate([
            'name' => [
                'required',
                'string',
                'max:60',
                Rule::unique('academic_years', 'name')->ignore($this->editingId),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data, $isEditing, $logger): void {
            if ($data['is_active']) {
                AcademicYear::query()
                    ->when($this->editingId !== null, fn ($query) => $query->whereKeyNot($this->editingId))
                    ->update(['is_active' => false]);
            }

            if ($isEditing) {
                $year = AcademicYear::query()->findOrFail($this->editingId);
                $year->update($data);
                $logger->log('academic-years.updated', "Memperbarui tahun ajaran {$year->name}", $year);
            } else {
                $year = AcademicYear::query()->create($data);
                $logger->log('academic-years.created', "Menambahkan tahun ajaran {$year->name}", $year);
            }
        });

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $isEditing
            ? 'Tahun ajaran berhasil diperbarui.'
            : 'Tahun ajaran berhasil ditambahkan.');
    }

    public function activate(int $id, ActivityLogger $logger): void
    {
        $this->authorize('academic-years.update');

        DB::transaction(function () use ($id, $logger): void {
            AcademicYear::query()->whereKeyNot($id)->update(['is_active' => false]);

            $year = AcademicYear::query()->findOrFail($id);
            $year->update(['is_active' => true]);

            $logger->log('academic-years.activated', "Mengaktifkan tahun ajaran {$year->name}", $year);
        });

        $this->dispatch('toast', type: 'success', message: 'Tahun ajaran aktif berhasil diubah.');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('academic-years.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('academic-years.delete');

        if ($this->deletingId === null) {
            return;
        }

        $year = AcademicYear::query()->withCount('schoolClasses')->findOrFail($this->deletingId);

        if ($year->school_classes_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Tahun ajaran tidak dapat dihapus karena masih memiliki data kelas.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $yearName = $year->name;
        $year->delete();
        $logger->log('academic-years.deleted', "Menghapus tahun ajaran {$yearName}", $year);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Tahun ajaran {$yearName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->start_date = '';
        $this->end_date = '';
        $this->is_active = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('academic-years.view');

        $years = AcademicYear::query()
            ->when(trim($this->search) !== '', function ($query): void {
                $query->where('name', 'like', '%' . trim($this->search) . '%');
            })
            ->orderByDesc('start_date')
            ->withCount('schoolClasses')
            ->paginate(Pagination::perPage());

        return view('livewire.master.academic-year-index', [
            'years' => $years,
        ]);
    }
}
