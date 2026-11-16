<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CashTransactionType;
use App\Models\Account;
use App\Models\AdmissionBill;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Pembayaran tagihan PPDB.
 *
 * Sebelumnya status tagihan PPDB hanya diubah manual, sehingga uang masuk dari
 * pendaftar tidak pernah muncul di laporan arus kas. Service ini mencatat
 * penerimaan kas (dan jurnal debit kas / kredit pendapatan) saat tagihan
 * dinyatakan lunas, serta membaliknya bila status dikembalikan.
 */
final class AdmissionPaymentService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly ActivityLogger $logger,
    ) {}

    public function markPaid(AdmissionBill $bill, ?User $actor = null): AdmissionBill
    {
        if ($bill->status === 'paid') {
            throw new InvalidArgumentException('Tagihan PPDB ini sudah lunas.');
        }

        if ($bill->status === 'cancelled') {
            throw new InvalidArgumentException('Tagihan PPDB yang dibatalkan tidak dapat dibayar.');
        }

        if ((float) $bill->amount <= 0) {
            throw new InvalidArgumentException('Nominal tagihan PPDB tidak valid.');
        }

        return DB::transaction(function () use ($bill, $actor): AdmissionBill {
            $cashAccount = $this->resolveAccount(AccountType::Cash, 'ppdb_cash_account_id');
            $revenueAccount = $this->resolveAccount(AccountType::Revenue, 'ppdb_revenue_account_id');

            $bill->forceFill([
                'status' => 'paid',
                'paid_at' => now(),
            ])->save();

            if ($cashAccount !== null) {
                CashTransaction::query()->firstOrCreate(
                    ['admission_bill_id' => $bill->id, 'type' => CashTransactionType::Income->value],
                    [
                        'account_id' => $cashAccount->id,
                        'user_id' => $actor?->id,
                        'transaction_number' => 'KAS-'.$bill->bill_number,
                        'transacted_at' => now(),
                        'amount' => $bill->amount,
                        'description' => 'Pembayaran tagihan PPDB '.$bill->bill_number,
                        'reference_number' => $bill->bill_number,
                    ],
                );
            } else {
                Log::warning('Akun kas belum tersedia; penerimaan kas PPDB dilewati.', ['bill_id' => $bill->id]);
            }

            if ($cashAccount !== null && $revenueAccount !== null) {
                $this->journals->post(
                    now()->toDateString(),
                    'Penerimaan tagihan PPDB '.$bill->bill_number,
                    $bill->bill_number,
                    [
                        ['account_id' => $cashAccount->id, 'debit' => (float) $bill->amount],
                        ['account_id' => $revenueAccount->id, 'credit' => (float) $bill->amount],
                    ],
                    $actor,
                );
            }

            $this->logger->log(
                'admissions.bill_paid',
                "Tagihan PPDB {$bill->bill_number} dibayar",
                $bill,
                ['amount' => (float) $bill->amount],
                $actor,
            );

            return $bill;
        });
    }

    /**
     * Batalkan pembayaran: kas keluar pembalik dicatat agar saldo kembali.
     */
    public function revertToUnpaid(AdmissionBill $bill, ?User $actor = null): AdmissionBill
    {
        if ($bill->status !== 'paid') {
            throw new InvalidArgumentException('Hanya tagihan PPDB yang sudah lunas dapat dibatalkan pembayarannya.');
        }

        return DB::transaction(function () use ($bill, $actor): AdmissionBill {
            $income = CashTransaction::query()
                ->where('admission_bill_id', $bill->id)
                ->where('type', CashTransactionType::Income->value)
                ->first();

            if ($income !== null) {
                CashTransaction::query()->firstOrCreate(
                    ['admission_bill_id' => $bill->id, 'type' => CashTransactionType::Expense->value],
                    [
                        'account_id' => $income->account_id,
                        'user_id' => $actor?->id,
                        'transaction_number' => 'REV-'.$bill->bill_number,
                        'transacted_at' => now(),
                        'amount' => $income->amount,
                        'description' => 'Pembatalan pembayaran tagihan PPDB '.$bill->bill_number,
                        'reference_number' => $bill->bill_number,
                    ],
                );
            }

            $bill->forceFill(['status' => 'unpaid', 'paid_at' => null])->save();

            $this->logger->log(
                'admissions.bill_payment_reverted',
                "Pembayaran tagihan PPDB {$bill->bill_number} dibatalkan",
                $bill,
                [],
                $actor,
            );

            return $bill;
        });
    }

    public function cancel(AdmissionBill $bill, ?User $actor = null): AdmissionBill
    {
        if ($bill->status === 'paid') {
            throw new InvalidArgumentException('Batalkan pembayaran terlebih dahulu sebelum membatalkan tagihan.');
        }

        $bill->forceFill(['status' => 'cancelled'])->save();

        $this->logger->log(
            'admissions.bill_cancelled',
            "Tagihan PPDB {$bill->bill_number} dibatalkan",
            $bill,
            [],
            $actor,
        );

        return $bill;
    }

    /**
     * Pilih akun dari pengaturan bila ada, jika tidak pakai akun pertama aktif
     * dengan jenis yang diminta.
     */
    private function resolveAccount(AccountType $type, string $settingKey): ?Account
    {
        $configured = AppSetting::get($settingKey);

        if ($configured !== null && $configured !== '') {
            $account = Account::query()->active()->find((int) $configured);

            if ($account !== null) {
                return $account;
            }
        }

        return Account::query()->active()->where('type', $type->value)->ordered()->first()
            ?? ($type === AccountType::Cash ? Account::query()->active()->where('type', AccountType::Bank->value)->ordered()->first() : null);
    }
}
