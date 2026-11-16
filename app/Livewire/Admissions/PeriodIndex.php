<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionPeriod;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Periode PPDB')]
class PeriodIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $status = 'draft';

    public string $description = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->starts_at = now()->toDateString();
        $this->ends_at = now()->addMonths(3)->toDateString();
        $this->showModal = true;
    }

    public function edit(int $periodId): void
    {
        $this->authorize('admissions.update');
        $this->resetForm();

        $period = AdmissionPeriod::query()->findOrFail($periodId);
        $this->editingId = $period->id;
        $this->name = $period->name;
        $this->starts_at = $period->starts_at->toDateString();
        $this->ends_at = $period->ends_at->toDateString();
        $this->status = $period->status;
        $this->description = (string) $period->description;
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize($this->editingId === null ? 'admissions.create' : 'admissions.update');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after_or_equal:starts_at'],
            'status' => ['required', Rule::in(['draft', 'open', 'closed'])],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->editingId === null) {
            $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
            $period = AdmissionPeriod::query()->create($data);
            $logger->log('admissions.period_created', "Menambahkan periode {$period->name}", $period);
            $message = 'Periode PPDB berhasil ditambahkan.';
        } else {
            $period = AdmissionPeriod::query()->findOrFail($this->editingId);
            $period->update($data);
            $logger->log('admissions.period_updated', "Memperbarui periode {$period->name}", $period);
            $message = 'Periode PPDB berhasil diperbarui.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function delete(int $periodId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.delete');

        $period = AdmissionPeriod::query()->withCount('applicants')->findOrFail($periodId);

        if ($period->applicants_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Periode masih memiliki pendaftar sehingga tidak dapat dihapus.');

            return;
        }

        $period->delete();
        $logger->log('admissions.period_deleted', "Menghapus periode {$period->name}");

        $this->dispatch('toast', type: 'success', message: 'Periode PPDB berhasil dihapus.');
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->status = 'draft';
        $this->description = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('admissions.view');

        $periods = AdmissionPeriod::query()
            ->withCount('applicants')
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))
            ->orderByDesc('starts_at')
            ->paginate(Pagination::perPage());

        return view('livewire.admissions.period-index', ['periods' => $periods]);
    }
}
