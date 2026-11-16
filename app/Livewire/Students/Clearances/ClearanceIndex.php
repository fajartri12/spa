<?php

declare(strict_types=1);

namespace App\Livewire\Students\Clearances;

use App\Enums\ClearanceStatus;
use App\Models\GraduationClearance;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Services\ClearanceService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Bebas tanggungan sebagai prasyarat SKL.
 *
 * Pemeriksaan menghitung sisa tagihan siswa dan menyimpan snapshot-nya.
 * Penerbitan SKL diblokir selama status masih `pending`; bendahara dapat
 * memberi pengecualian dengan alasan tertulis bila kebijakan madrasah
 * mengizinkan.
 */
#[Layout('layouts.app')]
#[Title('Bebas Tanggungan & SKL')]
class ClearanceIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public ?int $unitFilter = null;

    public ?int $classFilter = null;

    public bool $showOverrideModal = false;

    public ?int $overridingId = null;

    public string $overrideReason = '';

    public ?int $detailId = null;

    public bool $showDetailModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'unitFilter' => ['except' => null],
        'classFilter' => ['except' => null],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->authorize('clearances.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->classFilter = null;
        $this->resetPage();
    }

    public function updatedClassFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'unitFilter', 'classFilter']);
        $this->resetPage();
    }

    public function check(int $studentId, ClearanceService $service): void
    {
        $this->authorize('clearances.check');

        $student = Student::query()->findOrFail($studentId);
        $clearance = $service->check($student, auth()->user());

        $this->dispatch(
            'toast',
            type: $clearance->hasOutstanding() ? 'warning' : 'success',
            message: $clearance->hasOutstanding()
                ? "{$student->name} masih memiliki tanggungan ".number_format((float) $clearance->remaining_amount, 0, ',', '.').'.'
                : "{$student->name} bebas tanggungan.",
        );
    }

    public function openDetail(int $studentId): void
    {
        $this->authorize('clearances.view');

        $this->detailId = $studentId;
        $this->showDetailModal = true;
    }

    public function startOverride(int $studentId): void
    {
        $this->authorize('clearances.override');

        $this->overridingId = $studentId;
        $this->overrideReason = '';
        $this->resetErrorBag();
        $this->showOverrideModal = true;
    }

    public function override(ClearanceService $service): void
    {
        $this->authorize('clearances.override');

        $data = $this->validate([
            'overridingId' => ['required', 'integer'],
            'overrideReason' => ['required', 'string', 'max:2000'],
        ], [
            'overrideReason.required' => 'Alasan pengecualian wajib diisi.',
        ]);

        $student = Student::query()->findOrFail($data['overridingId']);

        try {
            $service->override($student, $data['overrideReason'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['overrideReason' => $exception->getMessage()]);
        }

        $this->showOverrideModal = false;
        $this->reset('overridingId', 'overrideReason');
        $this->dispatch('toast', type: 'success', message: "Pengecualian bebas tanggungan {$student->name} dicatat.");
    }

    public function issueSkl(int $studentId, ClearanceService $service): void
    {
        $this->authorize('clearances.issue');

        $student = Student::query()->findOrFail($studentId);

        try {
            $clearance = $service->issueSkl($student, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: "SKL {$clearance->skl_number} diterbitkan untuk {$student->name}.");
    }

    public function render(): View
    {
        $this->authorize('clearances.view');

        $students = Student::query()
            ->with([
                'unit:id,name',
                'schoolClass:id,name',
                'clearance',
            ])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('nis', 'like', $term)
                        ->orWhere('nisn', 'like', $term);
                });
            })
            ->when($this->unitFilter !== null, fn (Builder $query) => $query->where('unit_id', $this->unitFilter))
            ->when($this->classFilter !== null, fn (Builder $query) => $query->where('school_class_id', $this->classFilter))
            ->when($this->statusFilter !== '', function (Builder $query): void {
                if ($this->statusFilter === 'none') {
                    $query->whereDoesntHave('clearance');

                    return;
                }

                $query->whereHas('clearance', fn (Builder $clearance) => $clearance
                    ->where('status', $this->statusFilter));
            })
            ->ordered()
            ->paginate(Pagination::perPage());

        $detail = $this->detailId === null
            ? null
            : Student::query()->with(['unit', 'schoolClass', 'clearance.overrider'])->find($this->detailId);

        return view('livewire.students.clearances.clearance-index', [
            'students' => $students,
            'units' => Unit::query()->active()->ordered()->pluck('name', 'id')->all(),
            'classes' => SchoolClass::query()
                ->when($this->unitFilter !== null, fn (Builder $query) => $query->where('unit_id', $this->unitFilter))
                ->orderBy('name')
                ->pluck('name', 'id')->all(),
            'statusOptions' => $this->statusOptions(),
            'detail' => $detail,
            'summary' => $this->summary(),
        ]);
    }

    /**
     * @return array<string, int>
     */
    private function summary(): array
    {
        $base = fn (): Builder => Student::query();

        return [
            'total' => $base()->count(),
            'cleared' => $base()->whereHas('clearance', fn (Builder $query) => $query
                ->where('status', ClearanceStatus::Cleared->value))->count(),
            'overridden' => $base()->whereHas('clearance', fn (Builder $query) => $query
                ->where('status', ClearanceStatus::Overridden->value))->count(),
            'pending' => $base()->whereDoesntHave('clearance')->count()
                + $base()->whereHas('clearance', fn (Builder $query) => $query
                    ->where('status', ClearanceStatus::Pending->value))->count(),
            'skl_issued' => GraduationClearance::query()->whereNotNull('skl_number')->count(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $options = ['none' => 'Belum Diperiksa'];

        foreach (ClearanceStatus::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}