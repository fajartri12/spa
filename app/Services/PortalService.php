<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BillStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentIntent;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\StudentSavingsTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Sumber data tunggal untuk portal siswa dan portal orang tua.
 *
 * Kedua portal menampilkan angka yang sama, sehingga perhitungan sisa tagihan
 * dan saldo tabungan sengaja tidak diduplikasi di komponen Livewire maupun
 * Blade (AGENTS.MD bagian 2.1: business logic tidak di dalam Blade).
 */
class PortalService
{
    /**
     * @return array{count: int, amount: float}
     */
    public function outstanding(Student $student): array
    {
        $query = $this->outstandingQuery($student);

        return [
            'count' => (clone $query)->count(),
            'amount' => (float) (clone $query)->sum('remaining_amount'),
        ];
    }

    /**
     * Kode bayar (Virtual Account / QRIS) yang masih berlaku untuk tagihan siswa.
     *
     * Petugas menerbitkan tagihan digital dari halaman detail tagihan; portal
     * hanya menampilkan kode yang sudah terbit agar orang tua dapat membayar
     * tanpa menunggu konfirmasi manual. Kunci koleksi adalah `student_bill_id`.
     *
     * @return Collection<int, PaymentIntent>
     */
    public function openIntents(Student $student): Collection
    {
        return PaymentIntent::query()
            ->forStudent($student->getKey())
            ->open()
            ->orderByDesc('id')
            ->get()
            ->unique('student_bill_id')
            ->keyBy('student_bill_id');
    }

    public function latestApprovedPayment(Student $student): ?Payment
    {
        return Payment::query()
            ->where('student_id', $student->getKey())
            ->where('status', PaymentStatus::Approved->value)
            ->latest('paid_at')
            ->first();
    }

    public function bills(Student $student, int $perPage = 10): LengthAwarePaginator
    {
        return StudentBill::query()
            ->where('student_id', $student->getKey())
            ->with(['bill.paymentCategory', 'discounts'])
            ->orderByDesc('due_date')
            ->paginate($perPage);
    }

    public function payments(Student $student, int $perPage = 10, string $pageName = 'paymentsPage'): LengthAwarePaginator
    {
        return Payment::query()
            ->where('student_id', $student->getKey())
            ->with(['paymentCategory', 'academicYear', 'allocations'])
            ->orderByDesc('paid_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    public function savingsTransactions(Student $student, int $perPage = 10, string $pageName = 'savingsPage'): LengthAwarePaginator
    {
        $account = $student->savingsAccount;

        if ($account === null) {
            return StudentSavingsTransaction::query()
                ->whereRaw('1 = 0')
                ->paginate($perPage, ['*'], $pageName);
        }

        return $account->transactions()
            ->latest('transacted_at')
            ->paginate($perPage, ['*'], $pageName);
    }

    /**
     * Ringkasan keuangan seluruh anak dalam satu akun orang tua.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array<string, mixed>>
     */
    public function childrenOverview(Collection $students): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        $ids = $students->pluck('id')->all();

        $outstanding = StudentBill::query()
            ->selectRaw('student_id, COUNT(*) as bill_count, COALESCE(SUM(remaining_amount), 0) as remaining')
            ->whereIn('student_id', $ids)
            ->whereIn('status', [BillStatus::Unpaid->value, BillStatus::Partial->value])
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        $paid = Payment::query()
            ->selectRaw('student_id, COALESCE(SUM(amount), 0) as total_paid, MAX(paid_at) as last_paid_at')
            ->whereIn('student_id', $ids)
            ->where('status', PaymentStatus::Approved->value)
            ->groupBy('student_id')
            ->get()
            ->keyBy('student_id');

        return $students->map(static function (Student $student) use ($outstanding, $paid): array {
            $bill = $outstanding->get($student->getKey());
            $payment = $paid->get($student->getKey());

            return [
                'student' => $student,
                'outstanding_count' => (int) ($bill->bill_count ?? 0),
                'outstanding_amount' => (float) ($bill->remaining ?? 0),
                'total_paid' => (float) ($payment->total_paid ?? 0),
                'last_paid_at' => $payment->last_paid_at ?? null,
                'savings_balance' => (float) ($student->savingsAccount?->balance ?? 0),
            ];
        })->values();
    }

    /**
     * @return Builder<StudentBill>
     */
    private function outstandingQuery(Student $student): Builder
    {
        return StudentBill::query()
            ->where('student_id', $student->getKey())
            ->whereIn('status', [BillStatus::Unpaid->value, BillStatus::Partial->value]);
    }
}
