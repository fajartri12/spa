<?php

declare(strict_types=1);

namespace App\Livewire\Savings;

use App\Models\StudentSavingsTransaction;
use App\Services\SavingsService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Verifikasi pengajuan penarikan tabungan siswa.
 *
 * Penarikan tidak langsung memotong saldo; bendahara/verifikator memeriksa lalu
 * menyetujui atau menolak. Persetujuan memotong saldo dan mencatat kas keluar.
 */
#[Layout('layouts.app')]
#[Title('Verifikasi Penarikan Tabungan')]
class SavingsVerification extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = SavingsService::STATUS_PENDING;

    public bool $showRejectModal = false;

    public ?int $rejectingId = null;

    public string $rejectionReason = '';

    public function mount(): void
    {
        $this->authorize('savings.verify');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function approve(int $transactionId, SavingsService $service): void
    {
        $this->authorize('savings.verify');

        $transaction = StudentSavingsTransaction::query()
            ->where('type', 'withdrawal')
            ->findOrFail($transactionId);

        try {
            $service->approve($transaction, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->dispatch('toast', type: 'success', message: 'Penarikan disetujui dan kas keluar tercatat.');
    }

    public function confirmReject(int $transactionId): void
    {
        $this->authorize('savings.verify');

        $this->rejectingId = $transactionId;
        $this->rejectionReason = '';
        $this->resetErrorBag();
        $this->showRejectModal = true;
    }

    public function reject(SavingsService $service): void
    {
        $this->authorize('savings.verify');

        $data = $this->validate([
            'rejectionReason' => ['required', 'string', 'max:255'],
        ], [
            'rejectionReason.required' => 'Alasan penolakan wajib diisi.',
        ]);

        $transaction = StudentSavingsTransaction::query()
            ->where('type', 'withdrawal')
            ->findOrFail($this->rejectingId);

        try {
            $service->reject($transaction, $data['rejectionReason'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('rejectionReason', $exception->getMessage());

            return;
        }

        $this->showRejectModal = false;
        $this->dispatch('toast', type: 'success', message: 'Pengajuan penarikan ditolak.');
    }

    public function render(): View
    {
        $this->authorize('savings.verify');

        $withdrawals = StudentSavingsTransaction::query()
            ->with(['account.student.unit', 'account.student.schoolClass', 'user', 'verifier'])
            ->where('type', 'withdrawal')
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('transaction_number', 'like', $term)
                        ->orWhereHas('account.student', fn (Builder $student) => $student
                            ->where('name', 'like', $term)
                            ->orWhere('nis', 'like', $term));
                });
            })
            ->latest('transacted_at')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage());

        return view('livewire.savings.savings-verification', [
            'withdrawals' => $withdrawals,
            'pendingCount' => StudentSavingsTransaction::query()
                ->where('type', 'withdrawal')
                ->where('status', SavingsService::STATUS_PENDING)
                ->count(),
            'statusOptions' => [
                SavingsService::STATUS_PENDING => 'Menunggu Verifikasi',
                SavingsService::STATUS_POSTED => 'Disetujui',
                SavingsService::STATUS_REJECTED => 'Ditolak',
            ],
        ]);
    }
}
