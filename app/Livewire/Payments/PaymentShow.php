<?php

declare(strict_types=1);

namespace App\Livewire\Payments;

use App\Models\Payment;
use App\Models\PaymentProof;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Detail Pembayaran')]
class PaymentShow extends Component
{
    use WithFileUploads;

    public Payment $payment;
    public string $reason = '';
    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $proof = null;

    public function mount(Payment $payment): void
    {
        $this->authorize('payments.view');
        $this->payment = $payment->load(['student.schoolClass', 'paymentCategory', 'academicYear', 'allocations.studentBill.bill.paymentCategory', 'details.paymentMethod', 'verifications.verifier', 'proofs']);
    }

    public function approve(PaymentService $service): void
    {
        $this->authorize('payments.verify');
        $this->process(fn () => $service->approve($this->payment), 'Pembayaran berhasil disetujui.');
    }

    public function reject(PaymentService $service): void
    {
        $this->authorize('payments.verify');
        $this->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->process(fn () => $service->reject($this->payment, $this->reason), 'Pembayaran berhasil ditolak.');
    }

    public function cancel(PaymentService $service): void
    {
        $this->authorize('payments.cancel');
        $this->validate(['reason' => ['required', 'string', 'max:2000']]);
        $this->process(fn () => $service->cancel($this->payment, $this->reason), 'Pembayaran berhasil dibatalkan.');
    }

    public function render(): View
    {
        $this->authorize('payments.view');
        return view('livewire.payments.payment-show', ['status' => $this->payment->status]);
    }

    public function uploadProof(): void
    {
        $this->authorize('payments.create');
        $this->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $this->proof;
        $this->payment->proofs()->create([
            'file_path' => $file->store("payment-proofs/{$this->payment->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        $this->reset('proof');
        $this->payment->load('proofs');
        $this->dispatch('toast', type: 'success', message: 'Bukti pembayaran berhasil diunggah.');
    }

    public function deleteProof(int $proofId): void
    {
        $this->authorize('payments.create');
        $proof = PaymentProof::query()->where('payment_id', $this->payment->getKey())->findOrFail($proofId);
        Storage::disk('local')->delete($proof->file_path);
        $proof->delete();
        $this->payment->load('proofs');
        $this->dispatch('toast', type: 'success', message: 'Bukti pembayaran dihapus.');
    }

    private function process(callable $action, string $message): void
    {
        try {
            $this->payment = $action()->fresh(['student.schoolClass', 'paymentCategory', 'academicYear', 'allocations.studentBill.bill.paymentCategory', 'details.paymentMethod', 'verifications.verifier', 'proofs']);
            $this->reason = '';
            $this->dispatch('toast', type: 'success', message: $message);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['reason' => $exception->getMessage()]);
        }
    }
}
