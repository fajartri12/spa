<?php

declare(strict_types=1);

namespace App\Livewire\Bills;

use App\Enums\BillStatus;
use App\Enums\GatewayProvider;
use App\Models\PaymentIntent;
use App\Models\StudentBill;
use App\Services\BillingService;
use App\Services\Gateway\GatewayException;
use App\Services\Gateway\GatewayManager;
use App\Services\PaymentGatewayService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Tagihan')]
class BillShow extends Component
{
    public StudentBill $studentBill;

    public string $gatewayProvider = '';

    public string $gatewayChannel = '';

    public bool $showIntentModal = false;

    /**
     * @var array<string, string>
     */
    public array $channelOptions = [];

    public function mount(StudentBill $studentBill): void
    {
        $this->authorize('bills.view');
        $this->studentBill = $this->loadBill($studentBill);
    }

    public function cancel(BillingService $service): void
    {
        $this->authorize('bills.cancel');

        try {
            $this->studentBill = $this->loadBill($service->cancel($this->studentBill));
            $this->dispatch('toast', type: 'success', message: 'Tagihan berhasil dibatalkan.');
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cancel' => $exception->getMessage()]);
        }
    }

    /**
     * Buka dialog penerbitan Virtual Account / QRIS.
     */
    public function startIntent(): void
    {
        $this->authorize('payment-gateways.create');

        $provider = $this->resolveProvider();

        if ($provider === null) {
            $this->dispatch('toast', type: 'error', message: 'Belum ada penyedia payment gateway yang aktif untuk unit siswa ini.');

            return;
        }

        $this->gatewayProvider = $provider->value;
        $this->syncChannels();
        $this->resetErrorBag();
        $this->showIntentModal = true;
    }

    public function updatedGatewayProvider(): void
    {
        $this->syncChannels();
    }

    public function issueIntent(PaymentGatewayService $service): void
    {
        $this->authorize('payment-gateways.create');

        $data = $this->validate([
            'gatewayProvider' => ['required', 'string'],
            'gatewayChannel' => ['required', 'string'],
        ], [
            'gatewayChannel.required' => 'Pilih kanal pembayaran terlebih dahulu.',
        ]);

        $provider = GatewayProvider::tryFrom($data['gatewayProvider']);

        if ($provider === null) {
            throw ValidationException::withMessages(['gatewayProvider' => 'Penyedia pembayaran tidak dikenal.']);
        }

        try {
            $intent = $service->createIntent($this->studentBill, $provider, $data['gatewayChannel']);
        } catch (GatewayException|InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['gatewayProvider' => $exception->getMessage()]);
        }

        $this->studentBill = $this->loadBill($this->studentBill->refresh());
        $this->showIntentModal = false;
        $this->dispatch('toast', type: 'success', message: "Tagihan digital {$intent->reference} berhasil diterbitkan.");
    }

    /**
     * Isi pilihan kanal sesuai penyedia yang dipilih.
     */
    private function syncChannels(): void
    {
        $provider = GatewayProvider::tryFrom($this->gatewayProvider);

        $this->channelOptions = $provider === null
            ? []
            : app(GatewayManager::class)->channels($provider);

        $this->gatewayChannel = (string) (array_key_first($this->channelOptions) ?? '');
    }

    /**
     * @return StudentBill
     */
    private function loadBill(StudentBill $studentBill): StudentBill
    {
        return $studentBill->load([
            'student.unit',
            'student.schoolClass',
            'bill.paymentCategory',
            'bill.academicYear',
            'bill.items',
            'discounts',
        ]);
    }

    /**
     * Penyedia gateway yang dipakai unit siswa, atau penyedia default aplikasi.
     */
    private function resolveProvider(): ?GatewayProvider
    {
        $provider = $this->studentBill->student?->unit?->gatewayProvider()
            ?? GatewayProvider::tryFrom((string) config('payment_gateway.default'));

        if ($provider === null) {
            return null;
        }

        // Sembunyikan penyedia yang kredensialnya belum lengkap agar tombol
        // terbitkan tidak berujung pada kegagalan panggilan API.
        return app(GatewayManager::class)->driver($provider)->isConfigured() ? $provider : null;
    }

    public function render(): View
    {
        $this->authorize('bills.view');

        $canCancel = $this->studentBill->status !== BillStatus::Cancelled
            && (float) $this->studentBill->paid_amount <= 0
            && ! $this->hasActivePayment();

        $intents = PaymentIntent::query()
            ->where('student_bill_id', $this->studentBill->getKey())
            ->latest('id')
            ->limit(10)
            ->get();

        $provider = $this->resolveProvider();

        return view('livewire.bills.bill-show', [
            'canCancel' => $canCancel,
            'intents' => $intents,
            'gatewayProvider' => $provider,
            'providerOptions' => GatewayProvider::options(),
            'canIssueIntent' => $canCancel
                && $provider !== null
                && ! $intents->contains(fn (PaymentIntent $intent): bool => $intent->status->isOpen()),
        ]);
    }

    /**
     * Samakan dengan aturan BillingService::cancel(): pembayaran pending/approved menghalangi pembatalan.
     */
    private function hasActivePayment(): bool
    {
        return DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payment_allocations.student_bill_id', $this->studentBill->getKey())
            ->whereNull('payments.deleted_at')
            ->whereIn('payments.status', ['pending', 'approved'])
            ->exists();
    }
}
