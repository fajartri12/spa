<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\BillStatus;
use App\Enums\CashTransactionType;
use App\Enums\PaymentStatus;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\StudentBill;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class PaymentService
{
    private const NUMBER_ATTEMPTS = 3;

    public function __construct(private readonly ActivityLogger $logger) {}

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,array{student_bill_id:int,amount:numeric}>  $allocations
     */
    public function create(array $data, array $allocations): Payment
    {
        $idempotencyKey = trim((string) ($data['idempotency_key'] ?? ''));

        if ($idempotencyKey !== '') {
            $existing = Payment::query()->where('idempotency_key', $idempotencyKey)->first();

            if ($existing !== null) {
                return $existing->load('allocations');
            }
        }

        for ($attempt = 1; ; $attempt++) {
            try {
                return $this->createOnce($data, $allocations, $idempotencyKey !== '' ? $idempotencyKey : null);
            } catch (UniqueConstraintViolationException $exception) {
                // Nomor transaksi bisa bertabrakan saat dua petugas menyimpan
                // bersamaan; ulangi dengan nomor baru sebelum menyerah.
                if ($attempt >= self::NUMBER_ATTEMPTS || ! str_contains($exception->getMessage(), 'transaction_number')) {
                    throw $exception;
                }
            }
        }
    }

    public function approve(Payment $payment): Payment
    {
        return $this->changeStatus($payment, PaymentStatus::Approved, 'payment.approved');
    }

    public function reject(Payment $payment, ?string $reason = null): Payment
    {
        return $this->changeStatus($payment, PaymentStatus::Rejected, 'payment.rejected', $reason);
    }

    public function cancel(Payment $payment, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);

            if ($payment->status === PaymentStatus::Cancelled) {
                throw new InvalidArgumentException('Pembayaran sudah dibatalkan.');
            }

            $wasApproved = $payment->status === PaymentStatus::Approved;

            if ($wasApproved) {
                $this->adjustBills($payment, -1);
            }

            $payment->forceFill([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => auth()->id(),
                'cancellation_reason' => $reason,
            ])->save();

            if ($wasApproved) {
                $this->reverseCashTransaction($payment, $reason);
            }

            $this->logger->log('payment.cancelled', $reason, $payment);

            return $payment;
        });
    }

    /**
     * @param  array<string,mixed>  $data
     * @param  array<int,array{student_bill_id:int,amount:numeric}>  $allocations
     */
    private function createOnce(array $data, array $allocations, ?string $idempotencyKey): Payment
    {
        return DB::transaction(function () use ($data, $allocations, $idempotencyKey): Payment {
            $amount = round((float) ($data['amount'] ?? 0), 2);

            if ($amount <= 0 || $allocations === []) {
                throw new InvalidArgumentException('Nominal pembayaran dan alokasi wajib diisi.');
            }

            $ids = array_map(static fn ($item) => (int) $item['student_bill_id'], $allocations);
            $bills = StudentBill::query()->with('bill')->whereIn('id', $ids)->lockForUpdate()->get()->keyBy('id');

            if ($bills->count() !== count(array_unique($ids))) {
                throw new InvalidArgumentException('Tagihan tidak ditemukan.');
            }

            $studentId = (int) ($data['student_id'] ?? $bills->first()->student_id);

            if ($bills->contains(static fn (StudentBill $bill) => $bill->student_id !== $studentId)) {
                throw new InvalidArgumentException('Semua tagihan harus milik siswa yang sama.');
            }

            $perBill = [];

            foreach ($allocations as $item) {
                $value = round((float) $item['amount'], 2);

                if ($value <= 0) {
                    throw new InvalidArgumentException('Nominal alokasi tidak valid.');
                }

                $billId = (int) $item['student_bill_id'];
                $perBill[$billId] = round(($perBill[$billId] ?? 0) + $value, 2);
            }

            foreach ($perBill as $billId => $value) {
                if ($value > (float) $bills[$billId]->remaining_amount) {
                    throw new InvalidArgumentException('Alokasi melebihi sisa tagihan.');
                }
            }

            if (round(array_sum($perBill), 2) !== $amount) {
                throw new InvalidArgumentException('Total alokasi harus sama dengan nominal pembayaran.');
            }

            $payment = Payment::query()->create([
                'transaction_number' => $data['transaction_number'] ?? $this->nextTransactionNumber(),
                'idempotency_key' => $idempotencyKey,
                'student_id' => $studentId,
                'payment_category_id' => $data['payment_category_id'] ?? $bills->first()->bill->payment_category_id,
                'academic_year_id' => $data['academic_year_id'] ?? $bills->first()->bill->academic_year_id,
                'paid_at' => $data['paid_at'] ?? now(),
                'amount' => $amount,
                'status' => PaymentStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            // Satu baris alokasi per tagihan; duplikat pada input sudah digabung
            // agar tidak melanggar unique(payment_id, student_bill_id).
            foreach ($perBill as $billId => $value) {
                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'student_bill_id' => $billId,
                    'amount' => $value,
                ]);
            }

            if (! empty($data['payment_method_id'])) {
                $payment->details()->create([
                    'payment_method_id' => (int) $data['payment_method_id'],
                    'amount' => $amount,
                    'notes' => $data['notes'] ?? null,
                ]);
            }

            $this->logger->log('payment.created', 'Pembayaran dibuat', $payment, ['amount' => $amount]);

            return $payment->load('allocations');
        });
    }

    private function changeStatus(Payment $payment, PaymentStatus $status, string $event, ?string $reason = null): Payment
    {
        return DB::transaction(function () use ($payment, $status, $event, $reason): Payment {
            $payment = Payment::query()->lockForUpdate()->with('allocations')->findOrFail($payment->id);

            if ($payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException('Hanya pembayaran pending yang dapat diproses.');
            }

            if ($status === PaymentStatus::Approved) {
                $this->adjustBills($payment, 1);
            }

            $payment->forceFill(['status' => $status, 'notes' => $reason ?: $payment->notes])->save();
            $payment->verifications()->create([
                'verified_by' => auth()->id(),
                'status' => $status,
                'notes' => $reason,
                'verified_at' => now(),
            ]);

            if ($status === PaymentStatus::Approved) {
                $this->recordCashTransaction($payment);
            }

            $this->logger->log($event, $reason ?: 'Status pembayaran diperbarui', $payment);

            return $payment;
        });
    }

    private function adjustBills(Payment $payment, int $direction): void
    {
        foreach ($payment->allocations as $allocation) {
            $bill = StudentBill::query()->lockForUpdate()->findOrFail($allocation->student_bill_id);
            $paid = round((float) $bill->paid_amount + ($direction * (float) $allocation->amount), 2);

            if ($paid < 0 || $paid > (float) $bill->final_amount) {
                throw new InvalidArgumentException('Saldo tagihan tidak valid.');
            }

            $bill->forceFill([
                'paid_amount' => $paid,
                'remaining_amount' => round((float) $bill->final_amount - $paid, 2),
                'status' => $paid == 0
                    ? BillStatus::Unpaid
                    : ($paid == (float) $bill->final_amount ? BillStatus::Paid : BillStatus::Partial),
            ])->save();
        }
    }

    /**
     * Catat penerimaan kas saat pembayaran disetujui agar laporan arus kas
     * benar-benar mencerminkan uang yang diterima (AGENTS.md bagian 6).
     */
    private function recordCashTransaction(Payment $payment): void
    {
        $account = $this->resolveCashAccount($payment);

        if ($account === null) {
            Log::warning('Akun kas belum tersedia; transaksi kas pembayaran dilewati.', [
                'payment_id' => $payment->id,
            ]);

            return;
        }

        CashTransaction::query()->firstOrCreate(
            ['payment_id' => $payment->id, 'type' => CashTransactionType::Income->value],
            [
                'account_id' => $account->id,
                'user_id' => auth()->id(),
                'transaction_number' => 'KAS-'.$payment->transaction_number,
                'transacted_at' => $payment->paid_at ?? now(),
                'amount' => $payment->amount,
                'description' => 'Penerimaan pembayaran '.$payment->transaction_number,
                'reference_number' => $payment->transaction_number,
            ],
        );
    }

    /**
     * Balik penerimaan kas ketika pembayaran yang sudah disetujui dibatalkan.
     * Baris asal tidak dihapus agar histori tetap utuh.
     */
    private function reverseCashTransaction(Payment $payment, string $reason): void
    {
        $income = CashTransaction::query()
            ->where('payment_id', $payment->id)
            ->where('type', CashTransactionType::Income->value)
            ->first();

        if ($income === null) {
            return;
        }

        CashTransaction::query()->firstOrCreate(
            ['payment_id' => $payment->id, 'type' => CashTransactionType::Expense->value],
            [
                'account_id' => $income->account_id,
                'user_id' => auth()->id(),
                'transaction_number' => 'REV-'.$payment->transaction_number,
                'transacted_at' => now(),
                'amount' => $income->amount,
                'description' => 'Pembatalan pembayaran '.$payment->transaction_number.': '.$reason,
                'reference_number' => $payment->transaction_number,
            ],
        );
    }

    private function resolveCashAccount(Payment $payment): ?Account
    {
        $detail = $payment->details()->first();

        if ($detail?->account_id !== null) {
            $account = Account::query()->find($detail->account_id);

            if ($account !== null) {
                return $account;
            }
        }

        return Account::query()->where('type', AccountType::Cash->value)->active()->ordered()->first()
            ?? Account::query()->active()->ordered()->first();
    }

    /**
     * Nomor transaksi berurutan per hari agar mudah diaudit dan kecil
     * kemungkinan bertabrakan (AGENTS.md bagian 6.4).
     */
    private function nextTransactionNumber(): string
    {
        $prefix = $this->transactionPrefix().'-'.now()->format('Ymd').'-';
        $last = Payment::withTrashed()
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -6)) + 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Awalan nomor transaksi dari pengaturan aplikasi.
     */
    private function transactionPrefix(): string
    {
        $prefix = trim((string) AppSetting::get('payment_prefix', 'PAY'));

        return $prefix === '' ? 'PAY' : $prefix;
    }
}
