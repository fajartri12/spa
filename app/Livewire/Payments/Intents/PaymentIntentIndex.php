<?php

declare(strict_types=1);

namespace App\Livewire\Payments\Intents;

use App\Enums\GatewayProvider;
use App\Enums\PaymentIntentStatus;
use App\Models\PaymentIntent;
use App\Services\PaymentGatewayService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar tagihan digital (Virtual Account / QRIS) beserta statusnya.
 *
 * Halaman ini menjadi pengganti antrean verifikasi manual untuk transaksi
 * non-tunai: pembayaran yang masuk lewat webhook langsung tampil `Dibayar`
 * dengan tautan ke transaksi yang dibentuk otomatis.
 */
#[Layout('layouts.app')]
#[Title('Tagihan Digital')]
class PaymentIntentIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $providerFilter = '';

    public bool $showCancelModal = false;

    public ?int $cancellingId = null;

    public string $cancelReason = '';

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'providerFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->authorize('payment-gateways.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedProviderFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'providerFilter']);
        $this->resetPage();
    }

    public function confirmCancel(int $intentId): void
    {
        $this->authorize('payment-gateways.cancel');

        $this->cancellingId = $intentId;
        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showCancelModal = true;
    }

    public function cancel(PaymentGatewayService $service): void
    {
        $this->authorize('payment-gateways.cancel');

        $data = $this->validate([
            'cancellingId' => ['required', 'integer'],
            'cancelReason' => ['required', 'string', 'max:255'],
        ], [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $intent = PaymentIntent::query()->findOrFail($data['cancellingId']);

        try {
            $service->cancel($intent, $data['cancelReason'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cancelReason' => $exception->getMessage()]);
        }

        $this->showCancelModal = false;
        $this->reset('cancellingId', 'cancelReason');
        $this->dispatch('toast', type: 'success', message: 'Tagihan digital berhasil dibatalkan.');
    }

    public function render(): View
    {
        $this->authorize('payment-gateways.view');

        $filtered = fn (): Builder => PaymentIntent::query()
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('reference', 'like', $term)
                        ->orWhere('pay_code', 'like', $term)
                        ->orWhereHas('student', fn (Builder $student) => $student
                            ->where('name', 'like', $term)
                            ->orWhere('nis', 'like', $term));
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query
                ->where('status', $this->statusFilter))
            ->when($this->providerFilter !== '', fn (Builder $query) => $query
                ->where('provider', $this->providerFilter));

        $intents = $filtered()
            ->with(['student:id,name,nis', 'studentBill:id,bill_number', 'payment:id,transaction_number,status'])
            ->latest('id')
            ->paginate(Pagination::perPage());

        return view('livewire.payments.intents.payment-intent-index', [
            'intents' => $intents,
            'pendingCount' => (int) PaymentIntent::query()
                ->where('status', PaymentIntentStatus::Pending->value)
                ->count(),
            'paidTotal' => (float) PaymentIntent::query()
                ->where('status', PaymentIntentStatus::Paid->value)
                ->sum('amount'),
            'statusOptions' => $this->statusOptions(),
            'providerOptions' => GatewayProvider::options(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $options = [];

        foreach (PaymentIntentStatus::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
