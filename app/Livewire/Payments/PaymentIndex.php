<?php

declare(strict_types=1);

namespace App\Livewire\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Pembayaran')]
class PaymentIndex extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatusFilter(): void { $this->resetPage(); }

    public function render(): View
    {
        $this->authorize('payments.view');
        $payments = Payment::query()
            ->with(['student.schoolClass', 'paymentCategory', 'details.paymentMethod'])
            ->when($this->search !== '', fn ($q) => $q->where(function ($query): void {
                $query->where('transaction_number', 'like', "%{$this->search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$this->search}%")->orWhere('nis', 'like', "%{$this->search}%"));
            }))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->latest('paid_at')->paginate(Pagination::perPage());

        return view('livewire.payments.payment-index', compact('payments'));
    }
}
