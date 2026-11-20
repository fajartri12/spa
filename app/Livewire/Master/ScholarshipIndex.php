<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\ScholarshipAward;
use App\Models\ScholarshipProgram;
use App\Models\Student;
use App\Services\ActivityLogger;
use App\Services\DiscountService;
use App\Services\ScholarshipService;
use App\Support\Formatter;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Manajemen Program Beasiswa, Keringanan, Subsidi Silang, dan Penerimanya.
 */
#[Layout('layouts.app')]
#[Title('Beasiswa & Keringanan')]
class ScholarshipIndex extends Component
{
    use WithPagination;

    public string $activeTab = 'programs'; // 'programs' | 'awards'

    public string $search = '';

    public string $fundingFilter = '';

    public string $statusFilter = '';

    // Program Modal State
    public bool $showProgramModal = false;

    public ?int $editingProgramId = null;

    public string $code = '';

    public string $name = '';

    public ?string $sponsor = null;

    public string $funding_source = 'internal'; // internal, government, private

    public string $discount_type = DiscountService::TYPE_PERCENT;

    public string $discount_value = '';

    public ?int $payment_category_id = null;

    public ?int $academic_year_id = null;

    public ?int $quota = null;

    public ?string $valid_from = null;

    public ?string $valid_to = null;

    public bool $is_active = true;

    public ?string $notes = null;

    // Program Delete Modal State
    public bool $showProgramDeleteModal = false;

    public ?int $deletingProgramId = null;

    // Award Modal State
    public bool $showAwardModal = false;

    public ?int $selectedProgramId = null;

    public ?int $student_id = null;

    public ?string $starts_on = null;

    public ?string $ends_on = null;

    public ?string $award_notes = null;

    // Revoke Modal State
    public bool $showRevokeModal = false;

    public ?int $revokingAwardId = null;

    public string $revocation_reason = '';

    public function mount(): void
    {
        $this->authorize('scholarships.view');
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFundingFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function createProgram(): void
    {
        $this->authorize('scholarships.create');

        $this->resetProgramForm();
        $this->showProgramModal = true;
    }

    public function editProgram(int $id): void
    {
        $this->authorize('scholarships.update');

        $program = ScholarshipProgram::query()->findOrFail($id);

        $this->editingProgramId = $program->id;
        $this->code = $program->code;
        $this->name = $program->name;
        $this->sponsor = $program->sponsor;
        $this->funding_source = $program->funding_source;
        $this->discount_type = $program->discount_type;
        $this->discount_value = (string) (float) $program->discount_value;
        $this->payment_category_id = $program->payment_category_id;
        $this->academic_year_id = $program->academic_year_id;
        $this->quota = $program->quota;
        $this->valid_from = $program->valid_from?->toDateString();
        $this->valid_to = $program->valid_to?->toDateString();
        $this->is_active = (bool) $program->is_active;
        $this->notes = $program->notes;

        $this->showProgramModal = true;
    }

    public function saveProgram(ActivityLogger $logger): void
    {
        $isEditing = $this->editingProgramId !== null;
        $this->authorize($isEditing ? 'scholarships.update' : 'scholarships.create');

        $this->discount_value = Formatter::parseRupiah($this->discount_value);

        $data = $this->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('scholarship_programs', 'code')->ignore($this->editingProgramId)],
            'name' => ['required', 'string', 'max:120'],
            'sponsor' => ['nullable', 'string', 'max:120'],
            'funding_source' => ['required', Rule::in(['internal', 'government', 'private'])],
            'discount_type' => ['required', Rule::in(array_keys(DiscountService::typeOptions()))],
            'discount_value' => [
                'required',
                'numeric',
                'gt:0',
                $this->discount_type === DiscountService::TYPE_PERCENT ? 'max:100' : 'max:9999999999999',
            ],
            'payment_category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date', 'after_or_equal:valid_from'],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($isEditing) {
            $program = ScholarshipProgram::query()->findOrFail($this->editingProgramId);
            $program->update($data);
            $logger->log('scholarship.program_updated', 'Memperbarui program beasiswa', $program);
            $message = 'Program beasiswa berhasil diperbarui.';
        } else {
            $program = ScholarshipProgram::query()->create($data);
            $logger->log('scholarship.program_created', 'Menambahkan program beasiswa baru', $program);
            $message = 'Program beasiswa berhasil dibuat.';
        }

        $this->showProgramModal = false;
        $this->resetProgramForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDeleteProgram(int $id): void
    {
        $this->authorize('scholarships.delete');

        $this->deletingProgramId = $id;
        $this->showProgramDeleteModal = true;
    }

    public function deleteProgram(ActivityLogger $logger): void
    {
        $this->authorize('scholarships.delete');

        if ($this->deletingProgramId === null) {
            return;
        }

        $program = ScholarshipProgram::query()->findOrFail($this->deletingProgramId);
        $program->delete();
        $logger->log('scholarship.program_deleted', 'Menghapus program beasiswa', $program);

        $this->showProgramDeleteModal = false;
        $this->deletingProgramId = null;
        $this->dispatch('toast', type: 'success', message: 'Program beasiswa berhasil dihapus.');
    }

    public function openAwardModal(?int $programId = null): void
    {
        $this->authorize('scholarships.award');

        $this->resetAwardForm();
        $this->selectedProgramId = $programId;
        $this->showAwardModal = true;
    }

    public function awardScholarship(ScholarshipService $service): void
    {
        $this->authorize('scholarships.award');

        $data = $this->validate([
            'selectedProgramId' => ['required', 'integer', 'exists:scholarship_programs,id'],
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'award_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $program = ScholarshipProgram::query()->findOrFail($data['selectedProgramId']);
        $student = Student::query()->findOrFail($data['student_id']);

        try {
            $service->award(
                $program,
                $student,
                $data['starts_on'] ? now()->parse($data['starts_on']) : null,
                $data['ends_on'] ? now()->parse($data['ends_on']) : null,
                $data['award_notes'],
                auth()->user(),
            );

            $this->showAwardModal = false;
            $this->resetAwardForm();
            $this->dispatch('toast', type: 'success', message: "Beasiswa berhasil diberikan kepada {$student->name}.");
        } catch (\Throwable $e) {
            $this->addError('student_id', $e->getMessage());
        }
    }

    public function confirmRevokeAward(int $awardId): void
    {
        $this->authorize('scholarships.award');

        $this->revokingAwardId = $awardId;
        $this->revocation_reason = '';
        $this->showRevokeModal = true;
    }

    public function revokeAward(ScholarshipService $service): void
    {
        $this->authorize('scholarships.award');

        if ($this->revokingAwardId === null) {
            return;
        }

        $this->validate([
            'revocation_reason' => ['required', 'string', 'max:255'],
        ]);

        $award = ScholarshipAward::query()->findOrFail($this->revokingAwardId);

        try {
            $service->revoke($award, $this->revocation_reason, auth()->user());

            $this->showRevokeModal = false;
            $this->revokingAwardId = null;
            $this->dispatch('toast', type: 'success', message: 'Penerimaan beasiswa berhasil dicabut.');
        } catch (\Throwable $e) {
            $this->addError('revocation_reason', $e->getMessage());
        }
    }

    private function resetProgramForm(): void
    {
        $this->reset([
            'editingProgramId',
            'code',
            'name',
            'sponsor',
            'quota',
            'valid_from',
            'valid_to',
            'notes',
            'payment_category_id',
            'academic_year_id',
        ]);

        $this->funding_source = 'internal';
        $this->discount_type = DiscountService::TYPE_PERCENT;
        $this->discount_value = '';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    private function resetAwardForm(): void
    {
        $this->reset([
            'selectedProgramId',
            'student_id',
            'starts_on',
            'ends_on',
            'award_notes',
        ]);
        $this->resetErrorBag();
    }

    public function render(ScholarshipService $service): View
    {
        $this->authorize('scholarships.view');

        $activeCounts = $service->activeCounts();

        $programs = ScholarshipProgram::query()
            ->with(['paymentCategory', 'academicYear'])
            ->search($this->search)
            ->when($this->fundingFilter !== '', fn (Builder $query) => $query->where('funding_source', $this->fundingFilter))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('is_active', $this->statusFilter === '1'))
            ->latest('id')
            ->paginate(Pagination::perPage());

        $awards = ScholarshipAward::query()
            ->with(['program', 'student.schoolClass', 'student.unit', 'awardedBy'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';
                $query->whereHas('student', fn (Builder $s) => $s->where('name', 'like', $term)->orWhere('nis', 'like', $term))
                    ->orWhereHas('program', fn (Builder $p) => $p->where('name', 'like', $term));
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->latest('id')
            ->paginate(Pagination::perPage());

        return view('livewire.master.scholarship-index', [
            'programs' => $programs,
            'awards' => $awards,
            'activeCounts' => $activeCounts,
            'categories' => PaymentCategory::query()->ordered()->pluck('name', 'id')->all(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'activePrograms' => ScholarshipProgram::query()->active()->ordered()->get(),
            'students' => Student::query()->active()->ordered()->limit(300)->get()
                ->mapWithKeys(fn (Student $s) => [$s->id => $s->nis.' — '.$s->name]),
        ]);
    }
}
