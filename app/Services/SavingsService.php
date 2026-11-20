<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CashTransactionType;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentSavingsAccount;
use App\Models\StudentSavingsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Alur tabungan siswa: setoran langsung posted, penarikan menunggu verifikasi.
 *
 * Setoran menambah saldo dan mencatat kas masuk; penarikan baru mengubah saldo
 * setelah disetujui, sehingga saldo tidak pernah berkurang oleh pengajuan yang
 * masih tertunda. Setiap transaksi posted membuat baris `cash_transactions`
 * agar laporan arus kas mencerminkan uang fisik yang benar-benar berpindah.
 */
final class SavingsService
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REJECTED = 'rejected';

    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly PaymentService $payments,
    ) {}

    public function deposit(Student $student, float $amount, ?string $description, User $actor): StudentSavingsTransaction
    {
        return $this->postImmediately($student, 'deposit', $amount, $description, $actor);
    }

    /**
     * Ajukan penarikan. Saldo belum berubah sampai disetujui.
     */
    public function requestWithdrawal(Student $student, float $amount, ?string $description, User $actor): StudentSavingsTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal penarikan harus lebih dari nol.');
        }

        return DB::transaction(function () use ($student, $amount, $description, $actor): StudentSavingsTransaction {
            $account = $this->lockAccount($student);
            $available = $this->availableBalance($account);

            if ($amount > $available) {
                throw new InvalidArgumentException('Saldo tabungan yang dapat ditarik tidak mencukupi.');
            }

            $balance = (float) $account->balance;

            $transaction = StudentSavingsTransaction::query()->create([
                'student_savings_account_id' => $account->id,
                'user_id' => $actor->id,
                'transaction_number' => $this->nextNumber('SAV-TARIK'),
                'type' => 'withdrawal',
                'status' => self::STATUS_PENDING,
                'amount' => $amount,
                'balance_before' => $balance,
                'balance_after' => $balance,
                'transacted_at' => now(),
                'description' => $description,
            ]);

            $this->logger->log(
                'savings.withdrawal_requested',
                "Pengajuan penarikan tabungan {$student->name} sebesar ".number_format($amount, 0, ',', '.'),
                $transaction,
                ['student_id' => $student->id, 'amount' => $amount],
                $actor,
            );

            return $transaction;
        });
    }

    /**
     * Setujui penarikan: saldo dikurangi lalu kas keluar dicatat.
     */
    public function approve(StudentSavingsTransaction $transaction, User $actor): StudentSavingsTransaction
    {
        if ($transaction->status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('Hanya penarikan yang menunggu verifikasi dapat disetujui.');
        }

        return DB::transaction(function () use ($transaction, $actor): StudentSavingsTransaction {
            $account = StudentSavingsAccount::query()
                ->whereKey($transaction->student_savings_account_id)
                ->lockForUpdate()
                ->firstOrFail();

            $balanceBefore = (float) $account->balance;
            $amount = (float) $transaction->amount;

            if ($amount > $balanceBefore) {
                throw new InvalidArgumentException('Saldo tabungan tidak cukup untuk menyetujui penarikan ini.');
            }

            $balanceAfter = round($balanceBefore - $amount, 2);

            $account->balance = $balanceAfter;
            $account->save();

            $transaction->fill([
                'status' => self::STATUS_POSTED,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            $this->recordCash($transaction, CashTransactionType::Expense, $account, $actor);

            $this->logger->log(
                'savings.withdrawal_approved',
                "Penarikan tabungan {$transaction->transaction_number} disetujui",
                $transaction,
                ['amount' => $amount, 'balance_after' => $balanceAfter],
                $actor,
            );

            return $transaction;
        });
    }

    public function reject(StudentSavingsTransaction $transaction, string $reason, User $actor): StudentSavingsTransaction
    {
        if ($transaction->status !== self::STATUS_PENDING) {
            throw new InvalidArgumentException('Hanya penarikan yang menunggu verifikasi dapat ditolak.');
        }

        return DB::transaction(function () use ($transaction, $reason, $actor): StudentSavingsTransaction {
            $transaction->fill([
                'status' => self::STATUS_REJECTED,
                'rejection_reason' => $reason,
                'verified_by' => $actor->id,
                'verified_at' => now(),
            ])->save();

            $this->logger->log(
                'savings.withdrawal_rejected',
                "Penarikan tabungan {$transaction->transaction_number} ditolak",
                $transaction,
                ['reason' => $reason],
                $actor,
            );

            return $transaction;
        });
    }

    /**
     * Bayar satu atau beberapa tagihan langsung dari saldo tabungan siswa.
     *
     * Uang fisik sudah tercatat di kas madrasah sejak setoran, sehingga
     * pembayaran ini tidak mencatat kas masuk lagi (menghindari double count).
     *
     * @param  array<int, array{student_bill_id:int, amount:numeric}>  $allocations
     */
    public function payBills(
        Student $student,
        array $allocations,
        ?string $description,
        User $actor,
    ): Payment {
        if ($allocations === []) {
            throw new InvalidArgumentException('Pilih minimal satu tagihan untuk dibayar.');
        }

        return DB::transaction(function () use ($student, $allocations, $description, $actor): Payment {
            $account = $this->lockAccount($student);
            $total = 0.0;

            foreach ($allocations as $row) {
                $value = round((float) $row['amount'], 2);

                if ($value <= 0) {
                    throw new InvalidArgumentException('Nominal pembayaran tagihan tidak valid.');
                }

                $total = round($total + $value, 2);
            }

            $available = $this->availableBalance($account);

            if ($total > $available) {
                throw new InvalidArgumentException(
                    'Saldo tabungan yang dapat digunakan tidak mencukupi (tersedia: Rp '.
                    number_format($available, 0, ',', '.').').',
                );
            }

            $payment = $this->payments->create([
                'student_id' => $student->id,
                'paid_at' => now(),
                'amount' => $total,
                'notes' => $description ?: 'Pembayaran tagihan via saldo tabungan',
            ], $allocations);

            $this->payments->approve($payment, recordCash: false);

            $balanceBefore = (float) $account->balance;
            $balanceAfter = round($balanceBefore - $total, 2);

            $account->balance = $balanceAfter;
            $account->save();

            $transaction = StudentSavingsTransaction::query()->create([
                'student_savings_account_id' => $account->id,
                'user_id' => $actor->id,
                'transaction_number' => $this->nextNumber('SAV-BAYAR'),
                'type' => 'withdrawal',
                'status' => self::STATUS_POSTED,
                'amount' => $total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transacted_at' => now(),
                'description' => 'Pembayaran tagihan #'.$payment->transaction_number.($description ? ' ('.$description.')' : ''),
            ]);

            $this->logger->log(
                'savings.bill_paid',
                "Pembayaran tagihan {$payment->transaction_number} dari saldo {$student->name} sebesar ".number_format($total, 0, ',', '.'),
                $transaction,
                ['payment_id' => $payment->id, 'amount' => $total],
                $actor,
            );

            return $payment->fresh(['allocations']);
        });
    }

    /**
     * Saldo yang benar-benar bisa ditarik: saldo dikurangi pengajuan tertunda.
     */
    public function availableBalance(StudentSavingsAccount $account): float
    {
        $pending = (float) StudentSavingsTransaction::query()
            ->where('student_savings_account_id', $account->id)
            ->where('status', self::STATUS_PENDING)
            ->where('type', 'withdrawal')
            ->sum('amount');

        return round((float) $account->balance - $pending, 2);
    }

    private function postImmediately(Student $student, string $type, float $amount, ?string $description, User $actor): StudentSavingsTransaction
    {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Nominal transaksi harus lebih dari nol.');
        }

        return DB::transaction(function () use ($student, $type, $amount, $description, $actor): StudentSavingsTransaction {
            $account = $this->lockAccount($student);
            $balanceBefore = (float) $account->balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $account->balance = $balanceAfter;
            $account->save();

            $transaction = StudentSavingsTransaction::query()->create([
                'student_savings_account_id' => $account->id,
                'user_id' => $actor->id,
                'transaction_number' => $this->nextNumber('SAV-SETOR'),
                'type' => $type,
                'status' => self::STATUS_POSTED,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'transacted_at' => now(),
                'description' => $description,
            ]);

            $this->recordCash($transaction, CashTransactionType::Income, $account, $actor);

            $this->logger->log(
                'savings.deposit',
                "Setoran tabungan {$student->name} sebesar ".number_format($amount, 0, ',', '.'),
                $transaction,
                ['amount' => $amount, 'balance_after' => $balanceAfter],
                $actor,
            );

            return $transaction;
        });
    }

    private function lockAccount(Student $student): StudentSavingsAccount
    {
        $account = StudentSavingsAccount::query()->firstOrCreate(
            ['student_id' => $student->id],
            ['balance' => 0],
        );

        return StudentSavingsAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Catat mutasi kas bila akun kas/bank tersedia. Bila belum ada akun, fitur
     * tabungan tetap berjalan dan hanya peringatan yang dicatat.
     */
    private function recordCash(StudentSavingsTransaction $transaction, CashTransactionType $type, StudentSavingsAccount $account, User $actor): void
    {
        $cashAccount = $this->resolveCashAccount();

        if ($cashAccount === null) {
            Log::warning('Akun kas belum tersedia; mutasi kas tabungan dilewati.', [
                'savings_transaction_id' => $transaction->id,
            ]);

            return;
        }

        CashTransaction::query()->firstOrCreate(
            [
                'student_savings_transaction_id' => $transaction->id,
                'type' => $type->value,
            ],
            [
                'account_id' => $cashAccount->id,
                'user_id' => $actor->id,
                'transaction_number' => 'KAS-'.$transaction->transaction_number,
                'transacted_at' => $transaction->transacted_at ?? now(),
                'amount' => $transaction->amount,
                'description' => ($type === CashTransactionType::Income ? 'Setoran' : 'Penarikan')
                    .' tabungan siswa #'.$account->student_id,
                'reference_number' => $transaction->transaction_number,
            ],
        );
    }

    private function resolveCashAccount(): ?Account
    {
        $configured = AppSetting::get('savings_cash_account_id');

        if ($configured !== null && $configured !== '') {
            $account = Account::query()->active()->find((int) $configured);

            if ($account !== null) {
                return $account;
            }
        }

        return Account::query()->where('type', AccountType::Cash->value)->active()->ordered()->first()
            ?? Account::query()->active()->ordered()->first();
    }

    /**
     * Nomor transaksi tabungan berurutan per hari agar mudah ditelusuri.
     */
    private function nextNumber(string $prefix): string
    {
        $prefix .= '-'.now()->format('Ymd').'-';

        $last = StudentSavingsTransaction::query()
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -4)) + 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
