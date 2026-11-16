<?php

namespace App\Livewire\Savings;

use App\Models\Student;
use App\Models\StudentSavingsAccount;
use App\Models\AppSetting;
use App\Services\SavingsService;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
#[Layout('layouts.app')]
#[Title('Transaksi Tabungan')]
class SavingsTransaction extends Component
{
    public function mount(?int $student = null): void
    {
        $this->student_id = $student;
    }

    public ?int $student_id = null;

    public string $type = 'deposit';

    public string $amount = '';

    public string $description = '';

    public function updatedStudentId(): void
    {
        $this->amount = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function save(SavingsService $service): void
    {
        $this->authorize($this->type === 'withdrawal' ? 'savings.withdraw' : 'savings.create');

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

    public function render(): View
    {
        $this->authorize('savings.create');
        $selectedStudent = $this->student_id ? Student::query()->find($this->student_id) : null;

        return view('livewire.savings.savings-transaction', [
            'students' => Student::query()->active()->ordered()->pluck('name', 'id'),
            'selectedStudent' => $selectedStudent,
        ]);
    }
}
