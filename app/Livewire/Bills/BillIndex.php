<?php

declare(strict_types=1);

namespace App\Livewire\Bills;

use App\Enums\BillStatus;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Unit;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Tagihan')]
class BillIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    #[Url(as: 'tahun-ajaran', history: true)]
    public string $academicYearFilter = '';

    #[Url(as: 'unit', history: true)]
    public string $unitFilter = '';

    #[Url(as: 'kelas', history: true)]
    public string $classFilter = '';

    #[Url(as: 'kategori', history: true)]
    public string $categoryFilter = '';

    #[Url(as: 'periode', history: true)]
    public string $periodFilter = '';

    public function mount(): void
    {
        $this->authorize('bills.view');
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function updatedAcademicYearFilter(): void { $this->resetPage(); }

    public function updatedCategoryFilter(): void { $this->resetPage(); }

    public function updatedPeriodFilter(): void { $this->resetPage(); }

    public function updatedUnitFilter(): void
    {
        // Kelas terikat unit, jadi pilihan kelas harus direset agar tidak
        // menghasilkan kombinasi filter yang mustahil.
        $this->classFilter = '';
        $this->resetPage();
    }

    public function updatedClassFilter(): void { $this->resetPage(); }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'statusFilter',
            'academicYearFilter',
            'unitFilter',
            'classFilter',
            'categoryFilter',
            'periodFilter',
        ]);

        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('bills.view');

        $bills = $this->query()->paginate(Pagination::perPage());

        return view('livewire.bills.bill-index', [
            'bills' => $bills,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'classes' => $this->classOptions(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'categories' => PaymentCategory::query()->ordered()->pluck('name', 'id')->all(),
            'statuses' => collect(BillStatus::cases())->mapWithKeys(fn (BillStatus $status) => [$status->value => $status->label()])->all(),
            'hasFilter' => $this->hasFilter(),
        ]);
    }

    /**
     * @return Builder<Bill>
     */
    private function query(): Builder
    {
        return Bill::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('bill_number', 'like', $term)
                        ->orWhereHas('studentBills.student', fn (Builder $student) => $student
                            ->where('name', 'like', $term)
                            ->orWhere('nis', 'like', $term));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query
                ->whereHas('studentBills', fn (Builder $studentBill) => $studentBill->where('status', $this->statusFilter)))
            ->when($this->academicYearFilter !== '', fn (Builder $query) => $query
                ->where('academic_year_id', (int) $this->academicYearFilter))
            ->when($this->categoryFilter !== '', fn (Builder $query) => $query
                ->where('payment_category_id', (int) $this->categoryFilter))
            ->when($this->periodFilter !== '', fn (Builder $query) => $query
                ->where('period', $this->periodFilter))
            ->when($this->unitFilter !== '', fn (Builder $query) => $query
                ->whereHas('studentBills.student', fn (Builder $student) => $student->where('unit_id', (int) $this->unitFilter)))
            ->when($this->classFilter !== '', fn (Builder $query) => $query
                ->whereHas('studentBills.student', fn (Builder $student) => $student->where('school_class_id', (int) $this->classFilter)))
            ->with(['paymentCategory', 'academicYear', 'studentBills.student'])
            ->latest();
    }

    /**
     * @return array<int, string>
     */
    private function classOptions(): array
    {
        return SchoolClass::query()
            ->when($this->unitFilter !== '', fn (Builder $query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->academicYearFilter !== '', fn (Builder $query) => $query->where('academic_year_id', (int) $this->academicYearFilter))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function hasFilter(): bool
    {
        return trim($this->search) !== ''
            || $this->statusFilter !== ''
            || $this->academicYearFilter !== ''
            || $this->unitFilter !== ''
            || $this->classFilter !== ''
            || $this->categoryFilter !== ''
            || $this->periodFilter !== '';
    }
}
