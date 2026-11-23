<?php

declare(strict_types=1);

namespace App\Livewire\Public\Concerns;

use App\Models\Guardian;
use App\Models\StudentBill;
use App\Services\ManualTransferService;
use InvalidArgumentException;
use Livewire\WithFileUploads;

/**
 * Form unggah bukti transfer manual di portal siswa dan portal wali.
 *
 * Wali tanpa Virtual Account tetap dapat membayar dengan mengunggah bukti
 * transfer bank; petugas kemudian memverifikasi lewat menu Verifikasi.
 */
trait UploadsTransferProof
{
    use WithFileUploads;

    public bool $showProofModal = false;

    public ?int $proofBillId = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $proofFile = null;

    public string $proofAmount = '';

    public string $proofNote = '';

    /**
     * Tagihan yang tampil di portal pengguna (sumber pilihan bukti transfer).
     *
     * @return iterable<StudentBill>
     */
    abstract protected function proofBills(): iterable;

    public function openProofModal(int $billId): void
    {
        $bill = $this->findOwnedBill($billId);

        $this->resetErrorBag();
        $this->reset('proofFile', 'proofNote');
        $this->proofBillId = $bill->id;
        $this->proofAmount = (string) (int) round((float) $bill->remaining_amount);
        $this->showProofModal = true;
    }

    public function submitProof(ManualTransferService $service): void
    {
        $data = $this->validate([
            'proofBillId' => ['required', 'integer'],
            'proofFile' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'proofAmount' => ['required', 'numeric', 'min:1'],
            'proofNote' => ['nullable', 'string', 'max:255'],
        ], [
            'proofFile.required' => 'Pilih berkas bukti transfer terlebih dahulu.',
            'proofFile.mimes' => 'Bukti transfer harus berupa JPG, PNG, atau PDF.',
            'proofFile.max' => 'Ukuran berkas maksimal 5 MB.',
            'proofAmount.required' => 'Nominal transfer wajib diisi.',
            'proofAmount.min' => 'Nominal transfer minimal Rp 1.',
        ]);

        $bill = $this->findOwnedBill((int) $data['proofBillId']);
        $note = trim($data['proofNote']);

        try {
            $payment = $service->submit(
                $bill,
                $this->proofFile,
                (float) $data['proofAmount'],
                $note !== '' ? $note : null,
                $this->proofUploaderName($bill),
            );
        } catch (InvalidArgumentException $exception) {
            $this->addError('proofFile', $exception->getMessage());

            return;
        }

        $this->showProofModal = false;
        $this->reset('proofBillId', 'proofFile', 'proofAmount', 'proofNote');
        $this->dispatch('toast', type: 'success', message: 'Bukti transfer terkirim. Menunggu verifikasi petugas ('.$payment->transaction_number.').');
    }

    public function cancelProof(): void
    {
        $this->showProofModal = false;
        $this->reset('proofBillId', 'proofFile', 'proofAmount', 'proofNote');
        $this->resetErrorBag();
    }

    private function findOwnedBill(int $billId): StudentBill
    {
        $bill = null;

        foreach ($this->proofBills() as $candidate) {
            if ($candidate->id === $billId) {
                $bill = $candidate;
                break;
            }
        }

        abort_if($bill === null, 403, 'Tagihan bukan milik siswa yang dapat Anda akses.');

        return $bill;
    }

    private function proofUploaderName(StudentBill $bill): string
    {
        $guardian = Guardian::query()->find(session('public_guardian_id'));

        if ($guardian !== null) {
            return 'Wali: '.$guardian->name;
        }

        return 'Siswa: '.($bill->student?->name ?? 'portal');
    }
}