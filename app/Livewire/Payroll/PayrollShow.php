<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\StaffProfile;
use App\Services\PayrollService;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Detail periode honorarium: susun slip, setujui, atau batalkan.
 *
 * Slip hanya dapat diubah selama periode berstatus draf. Setelah disetujui,
 * nilai terkunci karena kas dan jurnal sudah terbentuk.
 */
#[Layout('layouts.app')]
#[Title('Slip Honorarium')]
class PayrollShow extends Component
{
    public PayrollPeriod $payrollPeriod;

    public bool $showItemModal = false;

    public ?int $editingId = null;

    public ?int $staffProfileId = null;

    public string $recipientName = '';

    public string $recipientPosition = '';

    public string $recipientBank = '';

    public string $recipientAccount = '';

    public string $grossAmount = '';

    public string $deductionAmount = '0';

    public string $notes = '';

    public bool $showApproveModal = false;

    public string $transactedAt = '';

    public bool $showCancelModal = false;

    public string $cancelReason = '';

    public function mount(PayrollPeriod $payrollPeriod): void
    {
        $this->authorize('payrolls.view');

        $this->payrollPeriod = $this->loadPeriod($payrollPeriod);
        $this->transactedAt = now()->toDateString();
    }

    public function updatedStaffProfileId(): void
    {
        if ($this->staffProfileId === null) {
            return;
        }

        $staff = StaffProfile::query()->find($this->staffProfileId);

        if ($staff === null) {
            return;
        }

        $this->recipientName = $staff->name;
        $this->recipientPosition = (string) ($staff->position?->label() ?? '');
    }

    public function openCreate(): void
    {
        $this->authorize('payrolls.update');

        $this->reset(['editingId', 'staffProfileId', 'recipientName', 'recipientPosition', 'recipientBank', 'recipientAccount', 'grossAmount', 'deductionAmount', 'notes']);
        $this->deductionAmount = '0';
        $this->resetErrorBag();
        $this->showItemModal = true;
    }

    public function editItem(int $itemId): void
    {
        $this->authorize('payrolls.update');

        $item = PayrollItem::query()->findOrFail($itemId);

        $this->editingId = $item->id;
        $this->staffProfileId = $item->staff_profile_id;
        $this->recipientName = $item->recipient_name;
        $this->recipientPosition = (string) $item->recipient_position;
        $this->recipientBank = (string) $item->recipient_bank;
        $this->recipientAccount = (string) $item->recipient_account;
        $this->grossAmount = (string) $item->gross_amount;
        $this->deductionAmount = (string) $item->deduction_amount;
        $this->notes = (string) $item->notes;
        $this->resetErrorBag();
        $this->showItemModal = true;
    }

    public function saveItem(PayrollService $service): void
    {
        $this->authorize('payrolls.update');

        $data = $this->validate([
            'staffProfileId' => ['nullable', 'integer', 'exists:staff_profiles,id'],
            'recipientName' => ['required', 'string', 'max:120'],
            'recipientPosition' => ['nullable', 'string', 'max:80'],
            'recipientBank' => ['nullable', 'string', 'max:80'],
            'recipientAccount' => ['nullable', 'string', 'max:60'],
            'grossAmount' => ['required', 'numeric', 'gt:0'],
            'deductionAmount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [
            'grossAmount.gt' => 'Nominal bruto harus lebih dari nol.',
        ]);

        $payload = [
            'staff_profile_id' => $data['staffProfileId'] ?? null,
            'recipient_name' => $data['recipientName'],
            'recipient_position' => $data['recipientPosition'] ?: null,
            'recipient_bank' => $data['recipientBank'] ?: null,
            'recipient_account' => $data['recipientAccount'] ?: null,
            'gross_amount' => (float) $data['grossAmount'],
            'deduction_amount' => (float) ($data['deductionAmount'] ?? 0),
            'notes' => $data['notes'] ?: null,
        ];

        try {
            if ($this->editingId === null) {
                $service->addItem($this->payrollPeriod, $payload, auth()->user());
            } else {
                $item = PayrollItem::query()->findOrFail($this->editingId);
                $service->updateItem($item, $payload, auth()->user());
            }
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['recipientName' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'staffProfileId' => 'Pegawai ini sudah memiliki slip pada periode ini.',
            ]);
        }

        $this->payrollPeriod = $this->loadPeriod($this->payrollPeriod->refresh());
        $this->showItemModal = false;
        $this->dispatch('toast', type: 'success', message: 'Slip honorarium tersimpan.');
    }

    public function removeItem(int $itemId, PayrollService $service): void
    {
        $this->authorize('payrolls.update');

        try {
            $service->removeItem(PayrollItem::query()->findOrFail($itemId), auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->payrollPeriod = $this->loadPeriod($this->payrollPeriod->refresh());
        $this->dispatch('toast', type: 'success', message: 'Slip dihapus.');
    }

    public function confirmApprove(): void
    {
        $this->authorize('payrolls.approve');

        $this->transactedAt = now()->toDateString();
        $this->resetErrorBag();
        $this->showApproveModal = true;
    }

    public function approve(PayrollService $service): void
    {
        $this->authorize('payrolls.approve');

        $data = $this->validate([
            'transactedAt' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'transactedAt.before_or_equal' => 'Tanggal pembayaran tidak boleh melebihi hari ini.',
        ]);

        try {
            $service->approve($this->payrollPeriod, auth()->user(), $data['transactedAt']);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['transactedAt' => $exception->getMessage()]);
        }

        $this->payrollPeriod = $this->loadPeriod($this->payrollPeriod->refresh());
        $this->showApproveModal = false;
        $this->dispatch('toast', type: 'success', message: 'Honorarium disetujui; kas keluar dan jurnal telah dibentuk.');
    }

    public function confirmCancel(): void
    {
        $this->authorize('payrolls.cancel');

        $this->cancelReason = '';
        $this->resetErrorBag();
        $this->showCancelModal = true;
    }

    public function cancel(PayrollService $service): void
    {
        $this->authorize('payrolls.cancel');

        $data = $this->validate([
            'cancelReason' => ['required', 'string', 'max:2000'],
        ], [
            'cancelReason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        try {
            $service->cancel($this->payrollPeriod, $data['cancelReason'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cancelReason' => $exception->getMessage()]);
        }

        $this->payrollPeriod = $this->loadPeriod($this->payrollPeriod->refresh());
        $this->showCancelModal = false;
        $this->dispatch('toast', type: 'success', message: 'Periode honorarium dibatalkan; kas dan jurnal dibalik.');
    }

    public function render(): View
    {
        $this->authorize('payrolls.view');

        $items = $this->payrollPeriod->items()->get();

        return view('livewire.payroll.payroll-show', [
            'items' => $items,
            'isDraft' => $this->payrollPeriod->isDraft(),
            'staffOptions' => StaffProfile::query()
                ->where('is_active', true)
                ->when($this->payrollPeriod->unit_id !== null, fn ($query) => $query
                    ->where('unit_id', $this->payrollPeriod->unit_id))
                ->orderBy('name')
                ->get()
                ->mapWithKeys(static fn (StaffProfile $staff): array => [
                    $staff->id => $staff->name.' — '.($staff->position?->label() ?? 'Staf'),
                ])->all(),
            'totalNet' => (float) $items->sum('net_amount'),
            'totalGross' => (float) $items->sum('gross_amount'),
            'totalDeduction' => (float) $items->sum('deduction_amount'),
            'byPosition' => $items->groupBy(static fn (PayrollItem $item): string => (string) ($item->recipient_position ?: 'Lainnya'))
                ->map(static fn ($group): float => (float) $group->sum('net_amount'))
                ->all(),
            'totalInWords' => Formatter::terbilang((float) $items->sum('net_amount')),
        ]);
    }

    private function loadPeriod(PayrollPeriod $period): PayrollPeriod
    {
        return $period->load(['unit', 'academicYear', 'cashAccount', 'expenseAccount', 'creator:id,name', 'approver:id,name', 'journal']);
    }
}