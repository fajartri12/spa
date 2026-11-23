<?php

declare(strict_types=1);

namespace App\Livewire\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\Pagination;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Verifikasi Pembayaran')]
class PaymentVerification extends Component
{
    use WithPagination;

    public string $search = '';
    public ?int $paymentId = null;
    public string $reason = '';
    public bool $rejectModal = false;

    public function updatedSearch(): void { $this->resetPage(); }

    public function approve(int $paymentId, PaymentService $service): void
    {
        $this->authorize('payments.verify');
        $this->process($paymentId, fn (Payment $payment) => $service->approve($payment), 'Pembayaran berhasil disetujui.');
    }

    public function startReject(int $paymentId): void
    {
        $this->authorize('payments.verify');
        $this->resetValidation();
        $this->paymentId = $paymentId;
        $this->reason = '';
        $this->rejectModal = true;
    }

    public function reject(PaymentService $service): void
    {
        $this->authorize('payments.verify');
        $this->validate([
            'paymentId' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $this->process($this->paymentId, fn (Payment $payment) => $service->reject($payment, $this->reason), 'Pembayaran berhasil ditolak.');
    }

    public function render(): View
    {
        $this->authorize('payments.verify');

        $payments = Payment::query()
            ->with(['student', 'paymentCategory'])
            ->withCount('proofs')
            ->where('status', PaymentStatus::Pending)
            ->when($this->search !== '', fn ($q) => $q->where(function ($query): void {
                $query->where('transaction_number', 'like', "%{$this->search}%")
                    ->orWhereHas('student', fn ($student) => $student->where('name', 'like', "%{$this->search}%"));
            }))
            ->oldest('paid_at')
            ->paginate(Pagination::perPage());

        return view('livewire.payments.payment-verification', [
            'payments' => $payments,
            'totalAmount' => (float) Payment::query()->where('status', PaymentStatus::Pending)->sum('amount'),
        ]);
    }

    private function process(int $paymentId, callable $action, string $message): void
    {
        try {
            $action(Payment::query()->findOrFail($paymentId));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }

        $this->reset('paymentId', 'reason', 'rejectModal');
        $this->dispatch('toast', type: 'success', message: $message);
    }
}
