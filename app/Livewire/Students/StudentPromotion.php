<?php

declare(strict_types=1);

namespace App\Livewire\Students;

use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Services\PromotionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Kenaikan Kelas')]
class StudentPromotion extends Component
{
    public ?int $source_unit_id = null;

    public ?int $source_year_id = null;

    public ?int $source_class_id = null;

    public ?int $target_unit_id = null;

    public ?int $target_year_id = null;

    public ?int $target_class_id = null;

    /** @var array<int, int> */
    public array $selected = [];

    public string $target_status = 'active';

    public string $note = '';

    public bool $confirmed = false;

    public function mount(): void
    {
        $this->authorize('students.promote');
        $this->source_year_id = AcademicYear::current()?->id;
    }

    public function updatedSourceUnitId(): void
    {
        $this->reset(['source_class_id', 'selected']);
    }

    public function updatedSourceYearId(): void
    {
        $this->reset(['source_class_id', 'selected']);
    }

    public function updatedSourceClassId(): void
    {
        $this->selected = [];
    }

    public function updatedTargetUnitId(): void
    {
        $this->target_class_id = null;
    }

    public function updatedTargetYearId(): void
    {
        $this->target_class_id = null;
    }

    public function promote(PromotionService $service): void
    {
        $this->authorize('students.promote');

        $data = $this->validate([
            'target_unit_id' => ['required', 'integer', 'exists:units,id'],
            'target_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'target_class_id' => ['required', 'integer', 'exists:school_classes,id'],
            'selected' => ['required', 'array', 'min:1'],
            'selected.*' => ['integer', 'exists:students,id'],
            'target_status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:255'],
            'confirmed' => ['accepted'],
        ], [
            'selected.required' => 'Pilih minimal satu siswa.',
            'confirmed.accepted' => 'Centang konfirmasi sebelum memproses.',
        ]);

        try {
            $result = $service->promote(
                $data['selected'],
                (int) $data['target_class_id'],
                (int) $data['target_year_id'],
                StudentStatus::from($data['target_status']),
                $data['note'] ?: null,
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('confirmed', $exception->getMessage());

            return;
        }

        $this->selected = [];
        $this->confirmed = false;

        session()->flash(
            'success',
            "{$result['promoted']} siswa dipindahkan. {$result['skipped']} siswa dilewati karena sudah berada di kelas tujuan.",
        );

        $this->redirectRoute('students.promotion');
    }

    /**
     * @return array<int, string>
     */
    public function sourceStudents(): array
    {
        return Student::query()
            ->when($this->source_unit_id, fn (Builder $q, $id) => $q->where('unit_id', $id))
            ->when($this->source_class_id, fn (Builder $q, $id) => $q->where('school_class_id', $id))
            ->when($this->source_year_id, fn (Builder $q, $id) => $q->whereHas(
                'schoolClass',
                fn (Builder $class) => $class->where('academic_year_id', $id),
            ))
            ->active()
            ->ordered()
            ->limit(500)
            ->get()
            ->mapWithKeys(fn (Student $student) => [
                $student->id => $student->nis.' — '.$student->name
                    .' ('.($student->schoolClass?->name ?? 'tanpa kelas').')',
            ])
            ->all();
    }

    public function render(PromotionService $service): View
    {
        $this->authorize('students.promote');

        $students = $service->candidates(array_map('intval', $this->selected));

        return view('livewire.students.student-promotion', [
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'years' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'sourceClasses' => SchoolClass::query()
                ->when($this->source_unit_id, fn (Builder $q, $id) => $q->where('unit_id', $id))
                ->when($this->source_year_id, fn (Builder $q, $id) => $q->where('academic_year_id', $id))
                ->ordered()->pluck('name', 'id')->all(),
            'targetClasses' => SchoolClass::query()
                ->when($this->target_unit_id, fn (Builder $q, $id) => $q->where('unit_id', $id))
                ->when($this->target_year_id, fn (Builder $q, $id) => $q->where('academic_year_id', $id))
                ->ordered()->pluck('name', 'id')->all(),
            'availableStudents' => $this->sourceStudents(),
            'selectedStudents' => $students,
            'statuses' => StudentStatus::options(),
            'targetClass' => $this->target_class_id ? SchoolClass::query()->find($this->target_class_id) : null,
            'clearanceBlockers' => $this->target_status === StudentStatus::Graduated->value
                ? $service->clearanceBlockers(array_map('intval', $this->selected))
                : collect(),
        ]);
    }
}
