<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\AcademicYear;
use App\Models\Payment;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Rekap Transaksi')]
class TransactionIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $academicYearId = null;

    public function mount(): void
    {
        $this->authorize('reports.view');
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->academicYearId = AcademicYear::current()?->id;
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }
    public function updatedAcademicYearId(): void { $this->resetPage(); }

    private function query()
    {
        return Payment::query()->with(['student', 'paymentCategory', 'details.paymentMethod'])
            ->when($this->search !== '', fn ($q) => $q->where(function ($query): void {
                $query->where('transaction_number', 'like', "%{$this->search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->dateFrom !== '', fn ($q) => $q->whereDate('paid_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn ($q) => $q->whereDate('paid_at', '<=', $this->dateTo))
            ->when($this->academicYearId, fn ($q) => $q->where('academic_year_id', $this->academicYearId));
    }

    public function render(): View
    {
        $this->authorize('reports.view');

        $approved = "CASE WHEN status = 'approved' THEN amount ELSE 0 END";
        $isBill = 'EXISTS (SELECT 1 FROM payment_allocations pa WHERE pa.payment_id = payments.id)';
        $isCash = "EXISTS (SELECT 1 FROM payment_details pd INNER JOIN payment_methods pm ON pm.id = pd.payment_method_id WHERE pd.payment_id = payments.id AND pm.code = 'cash')";

        $summary = (clone $this->query())
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as total_count")
            ->selectRaw("COALESCE(SUM({$approved}), 0) as total_amount")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$isBill} THEN {$approved} ELSE 0 END), 0) as bill_amount")
            ->selectRaw("SUM(CASE WHEN {$isBill} AND status = 'approved' THEN 1 ELSE 0 END) as bill_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN {$isCash} THEN {$approved} ELSE 0 END), 0) as cash_amount")
            ->selectRaw("SUM(CASE WHEN {$isCash} AND status = 'approved' THEN 1 ELSE 0 END) as cash_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN NOT {$isCash} THEN {$approved} ELSE 0 END), 0) as non_cash_amount")
            ->selectRaw("SUM(CASE WHEN NOT {$isCash} AND status = 'approved' THEN 1 ELSE 0 END) as non_cash_count")
            ->first();

        return view('livewire.reports.transaction-index', [
            'payments' => $this->query()->latest('paid_at')->paginate(20),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'summary' => [
                'total_count' => (int) ($summary->total_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'bill_amount' => (float) ($summary->bill_amount ?? 0),
                'bill_count' => (int) ($summary->bill_count ?? 0),
                'other_amount' => (float) ($summary->total_amount ?? 0) - (float) ($summary->bill_amount ?? 0),
                'other_count' => (int) ($summary->total_count ?? 0) - (int) ($summary->bill_count ?? 0),
                'cash_amount' => (float) ($summary->cash_amount ?? 0),
                'cash_count' => (int) ($summary->cash_count ?? 0),
                'non_cash_amount' => (float) ($summary->non_cash_amount ?? 0),
                'non_cash_count' => (int) ($summary->non_cash_count ?? 0),
            ],
        ]);
    }
}
