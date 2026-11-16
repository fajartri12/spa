<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionBill;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Tagihan PPDB')]
class AdmissionBillIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $periodFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $applicant_id = null;

    public string $bill_title = '';

    public string $bill_amount = '';

    public string $bill_due_date = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'periodFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedApplicantSearch(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->periodFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->bill_due_date = now()->addMonth()->toDateString();
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('admissions.create');

        $data = $this->validate([
            'applicant_id' => ['required', 'exists:applicants,id'],
            'bill_title' => ['required', 'string', 'max:150'],
            'bill_amount' => ['required', 'numeric', 'min:1000'],
            'bill_due_date' => ['required', 'date'],
        ]);

        $applicant = Applicant::query()->findOrFail($data['applicant_id']);

        $billNumber = 'PPDB-INV-'.now()->format('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

        $bill = AdmissionBill::query()->create([
            'applicant_id' => $applicant->getKey(),
            'bill_number' => $billNumber,
            'title' => $data['bill_title'],
            'amount' => $data['bill_amount'],
            'due_date' => $data['bill_due_date'],
            'status' => 'unpaid',
        ]);

        $logger->log('admissions.bill_created', "Menerbitkan tagihan PPDB {$bill->bill_number} untuk {$applicant->name}", $bill);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: "Tagihan {$bill->bill_number} berhasil diterbitkan.");
    }

    public function updateStatus(int $billId, string $newStatus, ActivityLogger $logger): void
    {
        $this->authorize('admissions.update');

        if (! in_array($newStatus, ['unpaid', 'paid', 'cancelled'], true)) {
            return;
        }

        $bill = AdmissionBill::query()->with('applicant')->findOrFail($billId);
        $old = $bill->status;
        $bill->update(['status' => $newStatus]);

        $logger->log('admissions.bill_status_changed', "Mengubah status tagihan {$bill->bill_number} dari {$old} ke {$newStatus}", $bill);

        $this->dispatch('toast', type: 'success', message: 'Status tagihan berhasil diperbarui.');
    }

    public function resetForm(): void
    {
        $this->applicant_id = null;
        $this->bill_title = '';
        $this->bill_amount = '';
        $this->bill_due_date = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('admissions.view');

        $query = AdmissionBill::query()
            ->with(['applicant.admissionPeriod'])
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->periodFilter !== '', function ($q): void {
                $q->whereHas('applicant', fn ($sub) => $sub->where('admission_period_id', $this->periodFilter));
            })
            ->when(trim($this->search) !== '', function ($q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($sub) => $sub->where('bill_number', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhereHas('applicant', fn ($a) => $a->where('name', 'like', $term)->orWhere('registration_number', 'like', $term)));
            });

        return view('livewire.admissions.admission-bill-index', [
            'bills' => $query->latest()->paginate(Pagination::perPage()),
            'periodOptions' => AdmissionPeriod::query()->orderByDesc('starts_at')->pluck('name', 'id'),
            'applicantOptions' => Applicant::query()->orderBy('name')->take(100)->get()->mapWithKeys(
                fn ($a) => [$a->id => "{$a->registration_number} - {$a->name}"]
            ),
            'totalBills' => AdmissionBill::query()->count(),
            'totalUnpaidAmount' => (float) AdmissionBill::query()->where('status', 'unpaid')->sum('amount'),
            'totalPaidAmount' => (float) AdmissionBill::query()->where('status', 'paid')->sum('amount'),
        ]);
    }
}
