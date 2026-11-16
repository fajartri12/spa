<?php

namespace App\Livewire\Savings;

use App\Models\AppSetting;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSavingsAccount;
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
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Tabungan Siswa')]
class SavingsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $classFilter = '';

    public bool $showTransactionModal = false;

    public ?int $student_id = null;

    public string $type = 'deposit';

    public string $amount = '';

    public string $description = '';

    public function openTransaction(?int $studentId = null): void
    {
        $this->authorize('savings.create');
        $this->resetValidation();
        $this->reset(['amount', 'description']);
        $this->type = 'deposit';
        $this->student_id = $studentId;
        $this->showTransactionModal = true;
    }

    public function updatedStudentId(): void
    {
        $this->amount = '';
        $this->description = '';
        $this->resetValidation();
    }

    public function saveTransaction(SavingsService $service): void
    {
        $this->authorize($this->type === 'withdrawal' ? 'savings.withdraw' : 'savings.create');

        $available = $this->availableBalance();

        $data = $this->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'type' => ['required', 'in:deposit,withdrawal'],
            'amount' => [
                'required', 'numeric', 'gt:0',
                $this->type === 'deposit'
                    ? 'between:'.AppSetting::get('min_savings_deposit', 1000).','.AppSetting::get('max_savings_deposit', 1000000)
                    : 'max:'.max(0, $available),
            ],
            'description' => ['nullable', 'string', 'max:255'],
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

        $this->showTransactionModal = false;
        $this->reset(['student_id', 'amount', 'description']);
        $this->dispatch(
            'toast',
            type: 'success',
            message: $data['type'] === 'withdrawal'
                ? 'Pengajuan penarikan tersimpan dan menunggu verifikasi.'
                : 'Setoran tabungan berhasil diproses.',
        );
    }

    /**
     * Saldo yang dapat ditarik (saldo dikurangi pengajuan tertunda).
     */
    private function availableBalance(): float
    {
        if ($this->student_id === null) {
            return 0.0;
        }

        $account = StudentSavingsAccount::query()->where('student_id', $this->student_id)->first();

        return $account === null ? 0.0 : app(SavingsService::class)->availableBalance($account);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedClassFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'classFilter']);
        $this->resetPage();
    }

    /**
     * Ekspor daftar tabungan + saldo sesuai filter aktif.
     */
    public function export(): StreamedResponse
    {
        $this->authorize('savings.export');

        $rows = Student::query()
            ->with(['unit', 'schoolClass', 'savingsAccount'])
            ->when(trim($this->search) !== '', fn ($q) => $q->where(function ($query): void {
                $term = '%'.trim($this->search).'%';

                $query->where('name', 'like', $term)
                    ->orWhere('nis', 'like', $term)
                    ->orWhere('nisn', 'like', $term);
            }))
            ->when($this->classFilter !== '', fn ($q) => $q->where('school_class_id', (int) $this->classFilter))
            ->active()
            ->ordered()
            ->get();

        $pending = StudentSavingsTransaction::query()
            ->where('type', 'withdrawal')
            ->where('status', SavingsService::STATUS_PENDING)
            ->selectRaw('student_savings_account_id, SUM(amount) as total')
            ->groupBy('student_savings_account_id')
            ->pluck('total', 'student_savings_account_id');

        return response()->streamDownload(function () use ($rows, $pending): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, ['nis', 'nisn', 'nama', 'unit', 'kelas', 'saldo', 'penarikan_menunggu', 'saldo_tersedia'], escape: '');

            foreach ($rows as $row) {
                $balance = (float) ($row->savingsAccount?->balance ?? 0);
                $pendingTotal = $row->savingsAccount === null
                    ? 0.0
                    : (float) ($pending[$row->savingsAccount->id] ?? 0);

                fputcsv($output, [
                    $row->nis,
                    $row->nisn,
                    $row->name,
                    $row->unit?->name,
                    $row->schoolClass?->name,
                    $balance,
                    $pendingTotal,
                    round($balance - $pendingTotal, 2),
                ], escape: '');
            }

            fclose($output);
        }, 'tabungan-siswa-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $this->authorize('savings.view');

        $students = Student::query()
            ->with(['unit', 'schoolClass', 'savingsAccount'])
            ->when($this->search !== '', fn ($q) => $q->where(function ($query): void {
                $query->where('name', 'like', "%{$this->search}%")
                    ->orWhere('nis', 'like', "%{$this->search}%")
                    ->orWhere('nisn', 'like', "%{$this->search}%")
                    ->orWhereHas('savingsAccount', fn ($account) => $account->where('balance', 'like', "%{$this->search}%"));
            }))
            ->when($this->classFilter !== '', fn ($q) => $q->where('school_class_id', (int) $this->classFilter))
            ->active()
            ->ordered()
            ->paginate(Pagination::perPage());

        $activeStudents = Student::query()->active();
        $summary = [
            'balance' => (float) (clone $activeStudents)
                ->join('student_savings_accounts', 'students.id', '=', 'student_savings_accounts.student_id')
                ->when($this->classFilter !== '', fn ($q) => $q->where('students.school_class_id', (int) $this->classFilter))
                ->sum('student_savings_accounts.balance'),
        ];

        $transactionSummary = StudentSavingsTransaction::query()
            ->whereHas('account.student', fn ($q) => $q->active()
                ->when($this->classFilter !== '', fn ($studentQuery) => $studentQuery->where('school_class_id', (int) $this->classFilter)))
            ->where('status', SavingsService::STATUS_POSTED)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END), 0) as deposits")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END), 0) as withdrawals")
            ->selectRaw('COUNT(*) as count')
            ->first();

        $pendingWithdrawalTotal = (float) StudentSavingsTransaction::query()
            ->whereHas('account.student', fn ($q) => $q->active()
                ->when($this->classFilter !== '', fn ($studentQuery) => $studentQuery->where('school_class_id', (int) $this->classFilter)))
            ->where('type', 'withdrawal')
            ->where('status', SavingsService::STATUS_PENDING)
            ->sum('amount');

        return view('livewire.savings.savings-index', [
            'students' => $students,
            'summary' => [
                ...$summary,
                'deposits' => (float) $transactionSummary->deposits,
                'withdrawals' => (float) $transactionSummary->withdrawals,
                'transactions' => (int) $transactionSummary->count,
                'volume' => (float) ($transactionSummary->deposits + $transactionSummary->withdrawals),
                'pendingWithdrawals' => $pendingWithdrawalTotal,
            ],
            'classOptions' => SchoolClass::query()->orderBy('name')->pluck('name', 'id'),
            'studentOptions' => Student::query()
                ->active()
                ->when($this->classFilter !== '', fn ($q) => $q->where('school_class_id', (int) $this->classFilter))
                ->ordered()
                ->pluck('name', 'id'),
            'selectedStudent' => $this->student_id ? Student::query()->with('savingsAccount')->find($this->student_id) : null,
            'availableBalance' => $this->availableBalance(),
            'pendingWithdrawals' => $this->student_id === null ? 0 : StudentSavingsTransaction::query()
                ->whereHas('account', fn (Builder $query) => $query->where('student_id', $this->student_id))
                ->where('type', 'withdrawal')
                ->where('status', SavingsService::STATUS_PENDING)
                ->count(),
        ]);
    }
}
