<?php

namespace App\Livewire\Savings;

use App\Enums\BillStatus;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentSavingsAccount;
use App\Models\AppSetting;
use App\Services\SavingsService;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Transaksi Tabungan')]
class SavingsTransaction extends Component
{
    public ?int $student_id = null;

    public string $type = 'deposit';

    public string $amount = '';

    public string $description = '';

    /** @var array<int, string> [student_bill_id => amount] */
    public array $billAmounts = [];

    public function mount(?int $student = null): void
    {
        $this->student_id = $student;
    }

    public function updatedStudentId(): void
    {
        $this->amount = '';
        $this->description = '';
        $this->billAmounts = [];
        $this->resetValidation();
    }

    public function updatedType(): void
    {
        $this->resetValidation();
    }

    public function save(SavingsService $service): void
    {
        $this->authorize(in_array($this->type, ['withdrawal', 'bill_payment'], true) ? 'savings.withdraw' : 'savings.create');

        if ($this->type === 'bill_payment') {
            $this->payBillsFromSavings($service);

            return;
        }

        $account = $this->student_id === null
            ? null
            : StudentSavingsAccount::query()->where('student_id', $this->student_id)->first();

        $available = $account === null ? 0.0 : $service->availableBalance($account);

        $data = $this->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'in:deposit,withdrawal'],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                $this->type === 'deposit'
                    ? 'between:'.AppSetting::get('min_savings_deposit', 1000).','.AppSetting::get('max_savings_deposit', 1000000)
                    : 'max:'.max(0, $available),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'amount.max' => 'Jumlah penarikan melebihi saldo tabungan yang dapat ditarik.',
            'amount.between' => 'Jumlah setoran berada di luar batas yang diizinkan.',
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $amount = (float) $data['amount'];

        try {
            if ($data['type'] === 'withdrawal') {
                $service->requestWithdrawal($student, $amount, $data['description'], auth()->user());
            } else {
                $service->deposit($student, $amount, $data['description'], auth()->user());
            }
        } catch (InvalidArgumentException $exception) {
            $this->addError('amount', $exception->getMessage());

            return;
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: $data['type'] === 'withdrawal'
                ? 'Pengajuan penarikan tersimpan dan menunggu verifikasi.'
                : 'Setoran tabungan berhasil diproses.',
        );
        $this->reset();
        $this->redirectRoute('savings.index');
    }

    private function payBillsFromSavings(SavingsService $service): void
    {
        $this->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
        ], [
            'student_id.required' => 'Pilih siswa terlebih dahulu.',
        ]);

        $allocations = [];

        foreach ($this->billAmounts as $billId => $raw) {
            $value = Formatter::parseRupiah((string) $raw);

            if ($value > 0) {
                $allocations[] = [
                    'student_bill_id' => (int) $billId,
                    'amount' => $value,
                ];
            }
        }

        if ($allocations === []) {
            $this->addError('billAmounts', 'Isi nominal pada minimal satu tagihan.');

            return;
        }

        $student = Student::query()->findOrFail($this->student_id);

        try {
            $payment = $service->payBills($student, $allocations, $this->description ?: null, auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('billAmounts', $exception->getMessage());

            return;
        }

        $this->dispatch(
            'toast',
            type: 'success',
            message: 'Tagihan dibayar dari saldo tabungan. Nomor pembayaran: '.$payment->transaction_number,
        );
        $this->reset();
        $this->redirectRoute('savings.index');
    }

    public function render(): View
    {
        $this->authorize('savings.create');

        $selectedStudent = $this->student_id ? Student::query()->find($this->student_id) : null;

        $account = $selectedStudent !== null
            ? StudentSavingsAccount::query()->where('student_id', $selectedStudent->id)->first()
            : null;

        $availableBalance = $account !== null ? app(SavingsService::class)->availableBalance($account) : 0.0;

        $unpaidBills = collect();

        if ($selectedStudent !== null && $this->type === 'bill_payment') {
            $unpaidBills = StudentBill::query()
                ->with('bill.category')
                ->where('student_id', $selectedStudent->id)
                ->whereIn('status', [BillStatus::Unpaid->value, BillStatus::Partial->value])
                ->where('remaining_amount', '>', 0)
                ->orderBy('due_date')
                ->orderBy('id')
                ->get();
        }

        return view('livewire.savings.savings-transaction', [
            'students' => Student::query()->active()->ordered()->pluck('name', 'id'),
            'selectedStudent' => $selectedStudent,
            'availableBalance' => $availableBalance,
            'unpaidBills' => $unpaidBills,
        ]);
    }
}
