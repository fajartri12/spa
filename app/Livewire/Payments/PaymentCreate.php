<?php

declare(strict_types=1);

namespace App\Livewire\Payments;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\AppSetting;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Catat Pembayaran')]
class PaymentCreate extends Component
{
    public ?int $student_id = null;
    public string $classFilter = '';
    public ?int $payment_method_id = null;
    public string $amount = '';
    public string $notes = '';

    /**
     * Kunci idempotensi per sesi form: klik ganda atau retry jaringan akan
     * menghasilkan pembayaran yang sama, bukan dua transaksi (AGENTS.md 6.4).
     */
    #[Locked]
    public string $idempotencyKey = '';

    /** @var array<int, string> */
    public array $allocations = [];

    public function mount(): void
    {
        $this->idempotencyKey = (string) Str::ulid();
    }

    public function updatedClassFilter(): void
    {
        $this->student_id = null;
        $this->allocations = [];
        $this->amount = '';
        $this->resetValidation();
    }

    public function updatedStudentId(): void
    {
        $this->allocations = [];
        $this->amount = '';
        $this->resetValidation();
    }

    public function updatedAllocations(): void
    {
        $this->amount = (string) round(collect($this->allocations)->sum(fn ($amount) => (float) $amount), 2);
    }

    public function save(PaymentService $paymentService): void
    {
        $this->authorize('payments.create');
        $data = $this->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'min:'.(float) AppSetting::get('min_payment_amount', 1000)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $allocations = collect($data['allocations'])
            ->filter(fn ($amount) => (float) $amount > 0)
            ->map(fn ($amount, $billId) => ['student_bill_id' => (int) $billId, 'amount' => $amount])
            ->values()->all();

        if ($allocations === []) {
            throw ValidationException::withMessages(['allocations' => 'Isi minimal satu alokasi pembayaran.']);
        }

        try {
            $paymentService->create(array_merge($data, [
                'idempotency_key' => $this->idempotencyKey,
            ]), $allocations);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['allocations' => $exception->getMessage()]);
        }

        session()->flash('success', 'Pembayaran berhasil dicatat dan menunggu verifikasi.');
        $this->redirectRoute('payments.index');
    }

    public function fillFull(int $billId, float|string $amount): void
    {
        $this->allocations[$billId] = (string) $amount;
        $this->updatedAllocations();
    }

    public function fillAll(): void
    {
        if ($this->student_id === null) {
            return;
        }

        $bills = StudentBill::query()
            ->where('student_id', $this->student_id)
            ->whereIn('status', [BillStatus::Unpaid->value, BillStatus::Partial->value])
            ->where('remaining_amount', '>', 0)
            ->get();

        foreach ($bills as $bill) {
            $this->allocations[$bill->id] = (string) $bill->remaining_amount;
        }
        $this->updatedAllocations();
    }

    public function clearAllocations(): void
    {
        $this->allocations = [];
        $this->updatedAllocations();
    }

    /** Ringkasan pembayaran terverifikasi hari ini + tunggakan seluruh siswa aktif. */
    private function stats(): array
    {
        $paid = Payment::query()
            ->where('status', PaymentStatus::Approved->value)
            ->whereDate('paid_at', today())
            ->with('details.paymentMethod')
            ->get();

        $cash = $paid->filter(fn (Payment $payment) => $payment->details->contains(fn ($detail) => $detail->paymentMethod?->code === 'cash'));

        return [
            'outstanding' => (float) StudentBill::query()->where('remaining_amount', '>', 0)->sum('remaining_amount'),
            'total' => (float) $paid->sum('amount'),
            'total_count' => $paid->count(),
            'cash' => (float) $cash->sum('amount'),
            'cash_count' => $cash->count(),
            'non_cash' => (float) $paid->sum('amount') - (float) $cash->sum('amount'),
            'non_cash_count' => $paid->count() - $cash->count(),
        ];
    }

    public function render(): View
    {
        $this->authorize('payments.create');
        $selectedStudent = $this->student_id ? Student::query()->with(['unit', 'schoolClass'])->find($this->student_id) : null;
        $studentBills = $this->student_id === null ? collect() : StudentBill::query()
            ->with('bill.paymentCategory')
            ->where('student_id', $this->student_id)
            ->whereIn('status', [BillStatus::Unpaid->value, BillStatus::Partial->value])
            ->where('remaining_amount', '>', 0)
            ->orderBy('due_date')->get();

        return view('livewire.payments.payment-create', [
            'students' => Student::query()->active()->ordered()
                ->when($this->classFilter !== '', fn ($q) => $q->where('school_class_id', (int) $this->classFilter))
                ->get()->mapWithKeys(fn (Student $student) => [$student->id => "{$student->nis} — {$student->name}"]),
            'classes' => SchoolClass::query()->active()->ordered()->pluck('name', 'id'),
            'selectedStudent' => $selectedStudent,
            'paymentMethods' => PaymentMethod::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id'),
            'studentBills' => $studentBills,
            'totalRemaining' => (float) $studentBills->sum('remaining_amount'),
            'totalAllocated' => round((float) collect($this->allocations)->sum(fn ($value) => (float) $value), 2),
            'stats' => $this->stats(),
        ]);
    }
}
