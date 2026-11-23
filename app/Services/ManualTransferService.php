<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentMethod;
use App\Models\StudentBill;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Pembayaran transfer manual dari portal wali/siswa.
 *
 * Wali yang tidak memakai Virtual Account mengunggah bukti transfer. Sistem
 * membuat pembayaran berstatus `pending` beserta lampirannya, lalu petugas
 * memverifikasi seperti pembayaran manual lainnya. Uang tidak dianggap masuk
 * sebelum disetujui (AGENTS.MD bagian 6).
 */
class ManualTransferService
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * @throws InvalidArgumentException
     */
    public function submit(
        StudentBill $bill,
        UploadedFile $file,
        float $amount,
        ?string $note = null,
        ?string $submittedBy = null,
    ): Payment {
        $amount = round($amount, 2);

        if ($bill->status === BillStatus::Cancelled) {
            throw new InvalidArgumentException('Tagihan sudah dibatalkan dan tidak dapat dibayar.');
        }

        if ((float) $bill->remaining_amount <= 0 || $bill->status === BillStatus::Paid) {
            throw new InvalidArgumentException('Tagihan ini sudah lunas.');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal transfer harus lebih dari nol.');
        }

        if ($amount > (float) $bill->remaining_amount) {
            throw new InvalidArgumentException('Nominal transfer melebihi sisa tagihan.');
        }

        if ($this->hasPendingTransfer($bill)) {
            throw new InvalidArgumentException('Sudah ada bukti transfer yang menunggu verifikasi untuk tagihan ini.');
        }

        $notes = trim('Bukti transfer manual (portal)'.($submittedBy !== null ? ' — '.$submittedBy : '')
            .($note !== null && $note !== '' ? '. Catatan: '.$note : ''));

        $payment = $this->payments->create([
            'student_id' => $bill->student_id,
            'payment_category_id' => $bill->bill?->payment_category_id,
            'academic_year_id' => $bill->bill?->academic_year_id,
            'paid_at' => now(),
            'amount' => $amount,
            'notes' => $notes,
            'payment_method_id' => $this->transferMethodId(),
        ], [
            ['student_bill_id' => $bill->id, 'amount' => $amount],
        ]);

        $payment->proofs()->create([
            'file_path' => $file->store("payment-proofs/{$payment->id}", 'local'),
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ]);

        return $payment->load('proofs');
    }

    /**
     * Cegah bukti transfer ganda untuk tagihan yang sama sebelum diverifikasi.
     */
    public function hasPendingTransfer(StudentBill $bill): bool
    {
        return PaymentAllocation::query()
            ->where('student_bill_id', $bill->id)
            ->whereHas('payment', fn ($query) => $query->where('status', PaymentStatus::Pending->value))
            ->exists();
    }

    private function transferMethodId(): ?int
    {
        return PaymentMethod::query()->where('code', 'transfer')->value('id');
    }
}