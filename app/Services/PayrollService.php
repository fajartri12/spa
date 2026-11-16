<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Enums\PayrollStatus;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\PayrollItem;
use App\Models\PayrollPeriod;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Penggajian / honorarium guru & karyawan.
 *
 * Satu periode berisi banyak slip (`payroll_items`). Saat periode disetujui,
 * dibentuk SATU transaksi kas keluar dan SATU jurnal batch untuk seluruh slip,
 * sehingga rekonsiliasi bank cukup mencocokkan satu kali transfer. Pembatalan
 * tidak menghapus apa pun: transaksi kas di-soft-delete dan jurnal diubah
 * menjadi `voided` agar jejak audit tetap utuh (AGENTS.MD bagian 6).
 */
final class PayrollService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly JournalService $journals,
    ) {}

    /**
     * @param  array{period:string, title:string, description?:?string, unit_id?:?int, academic_year_id?:?int, cash_account_id:int, expense_account_id:int}  $data
     *
     * @throws InvalidArgumentException
     */
    public function createPeriod(array $data, User $actor): PayrollPeriod
    {
        $this->assertAccounts((int) $data['cash_account_id'], (int) $data['expense_account_id']);

        return DB::transaction(function () use ($data, $actor): PayrollPeriod {
            $period = PayrollPeriod::query()->create([
                'period' => $data['period'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'unit_id' => $data['unit_id'] ?? null,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'cash_account_id' => (int) $data['cash_account_id'],
                'expense_account_id' => (int) $data['expense_account_id'],
                'status' => PayrollStatus::Draft->value,
                'total_amount' => 0,
                'employee_count' => 0,
                'created_by' => $actor->id,
            ]);

            $this->logger->log(
                'payroll.created',
                "Membuat periode honorarium {$period->title} ({$period->period})",
                $period,
                [],
                $actor,
            );

            return $period;
        });
    }

    /**
     * @param  array{staff_profile_id?:?int, recipient_name:string, recipient_position?:?string, recipient_account?:?string, recipient_bank?:?string, gross_amount:numeric, deduction_amount?:numeric, notes?:?string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function addItem(PayrollPeriod $period, array $data, User $actor): PayrollItem
    {
        $this->assertDraft($period);

        return DB::transaction(function () use ($period, $data, $actor): PayrollItem {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            $this->assertDraft($period);

            [$gross, $deduction] = $this->amounts($data);

            $item = $period->items()->create([
                'staff_profile_id' => $data['staff_profile_id'] ?? null,
                'recipient_name' => $data['recipient_name'],
                'recipient_position' => $data['recipient_position'] ?? null,
                'recipient_account' => $data['recipient_account'] ?? null,
                'recipient_bank' => $data['recipient_bank'] ?? null,
                'gross_amount' => $gross,
                'deduction_amount' => $deduction,
                'net_amount' => round($gross - $deduction, 2),
                'notes' => $data['notes'] ?? null,
                'sort_order' => (int) ($data['sort_order'] ?? $period->items()->count()),
            ]);

            $this->syncTotals($period);
            $this->logger->log(
                'payroll.item_added',
                "Menambah slip {$item->recipient_name} pada {$period->title}",
                $item,
                ['net_amount' => (float) $item->net_amount],
                $actor,
            );

            return $item;
        });
    }

    /**
     * @param  array{gross_amount:numeric, deduction_amount?:numeric, notes?:?string, recipient_account?:?string, recipient_bank?:?string, recipient_position?:?string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function updateItem(PayrollItem $item, array $data, User $actor): PayrollItem
    {
        return DB::transaction(function () use ($item, $data, $actor): PayrollItem {
            $item = PayrollItem::query()->lockForUpdate()->findOrFail($item->id);
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($item->payroll_period_id);

            $this->assertDraft($period);

            [$gross, $deduction] = $this->amounts($data);

            $item->forceFill([
                'gross_amount' => $gross,
                'deduction_amount' => $deduction,
                'net_amount' => round($gross - $deduction, 2),
                'notes' => $data['notes'] ?? $item->notes,
                'recipient_account' => $data['recipient_account'] ?? $item->recipient_account,
                'recipient_bank' => $data['recipient_bank'] ?? $item->recipient_bank,
                'recipient_position' => $data['recipient_position'] ?? $item->recipient_position,
            ])->save();

            $this->syncTotals($period);
            $this->logger->log('payroll.item_updated', "Memperbarui slip {$item->recipient_name}", $item, [], $actor);

            return $item;
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function removeItem(PayrollItem $item, User $actor): void
    {
        DB::transaction(function () use ($item, $actor): void {
            $item = PayrollItem::query()->lockForUpdate()->findOrFail($item->id);
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($item->payroll_period_id);

            $this->assertDraft($period);

            $item->delete();
            $this->syncTotals($period);
            $this->logger->log('payroll.item_removed', "Menghapus slip {$item->recipient_name}", $period, [], $actor);
        });
    }

    /**
     * Setujui periode: kunci slip, bentuk satu transaksi kas keluar dan satu
     * jurnal batch.
     *
     * @throws InvalidArgumentException
     */
    public function approve(PayrollPeriod $period, User $actor, ?string $transactedAt = null): PayrollPeriod
    {
        return DB::transaction(function () use ($period, $actor, $transactedAt): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status === PayrollStatus::Approved) {
                throw new InvalidArgumentException('Periode honorarium ini sudah disetujui.');
            }

            if ($period->status === PayrollStatus::Cancelled) {
                throw new InvalidArgumentException('Periode honorarium yang dibatalkan tidak dapat disetujui.');
            }

            $items = $period->items()->lockForUpdate()->get();

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('Periode honorarium belum memiliki slip.');
            }

            $total = round((float) $items->sum('net_amount'), 2);

            if ($total <= 0) {
                throw new InvalidArgumentException('Total honorarium harus lebih dari nol.');
            }

            $cashAccount = Account::query()->lockForUpdate()->find($period->cash_account_id);
            $expenseAccount = Account::query()->lockForUpdate()->find($period->expense_account_id);

            $this->assertAccounts((int) $period->cash_account_id, (int) $period->expense_account_id);

            $date = $transactedAt ?? now()->toDateString();
            $number = $this->nextNumber($date);

            $transaction = CashTransaction::query()->create([
                'account_id' => $cashAccount?->id,
                'user_id' => $actor->id,
                'transaction_number' => $number,
                'transacted_at' => $date,
                'type' => CashTransactionType::Expense->value,
                'amount' => $total,
                'description' => "Honorarium {$period->title} ({$period->period}) — {$items->count()} penerima",
                'reference_number' => 'PAYROLL-'.$period->id,
            ]);

            $journal = $this->journals->post(
                $date,
                "Honorarium {$period->title} ({$period->period})",
                $number,
                [
                    ['account_id' => $expenseAccount?->id, 'debit' => $total],
                    ['account_id' => $cashAccount?->id, 'credit' => $total],
                ],
                $actor,
            );

            $period->forceFill([
                'status' => PayrollStatus::Approved->value,
                'total_amount' => $total,
                'employee_count' => $items->count(),
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'cash_transaction_id' => $transaction->id,
                'journal_id' => $journal->id,
            ])->save();

            $this->logger->log(
                'payroll.approved',
                "Menyetujui honorarium {$period->title} sebesar {$total}",
                $period,
                ['transaction' => $number, 'journal' => $journal->journal_number, 'items' => $items->count()],
                $actor,
            );

            return $period;
        });
    }

    /**
     * Batalkan periode yang sudah disetujui dengan membalik kas dan jurnal.
     * Alasan wajib diisi (AGENTS.MD bagian 6: tidak ada penghapusan transaksi).
     *
     * @throws InvalidArgumentException
     */
    public function cancel(PayrollPeriod $period, string $reason, User $actor): PayrollPeriod
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
        }

        return DB::transaction(function () use ($period, $reason, $actor): PayrollPeriod {
            $period = PayrollPeriod::query()->lockForUpdate()->findOrFail($period->id);

            if ($period->status === PayrollStatus::Cancelled) {
                throw new InvalidArgumentException('Periode honorarium ini sudah dibatalkan.');
            }

            if ($period->status !== PayrollStatus::Approved) {
                throw new InvalidArgumentException('Hanya periode yang sudah disetujui yang dapat dibatalkan.');
            }

            if ($period->journal_id !== null) {
                Journal::query()->whereKey($period->journal_id)->update([
                    'status' => JournalStatus::Voided->value,
                    'updated_at' => now(),
                ]);
            }

            if ($period->cash_transaction_id !== null) {
                CashTransaction::query()->whereKey($period->cash_transaction_id)->first()?->delete();
            }

            $period->forceFill([
                'status' => PayrollStatus::Cancelled->value,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'cancellation_reason' => $reason,
            ])->save();

            $this->logger->log(
                'payroll.cancelled',
                "Membatalkan honorarium {$period->title}: {$reason}",
                $period,
                ['reason' => $reason, 'total' => (float) $period->total_amount],
                $actor,
            );

            return $period;
        });
    }

    /**
     * Salin total dan jumlah penerima dari slip ke periode agar daftar tidak
     * perlu menjumlahkan ulang.
     */
    public function syncTotals(PayrollPeriod $period): PayrollPeriod
    {
        $items = $period->items()->get();

        $period->forceFill([
            'total_amount' => round((float) $items->sum('net_amount'), 2),
            'employee_count' => $items->count(),
        ])->save();

        return $period;
    }

    /**
     * @param  array{gross_amount:numeric, deduction_amount?:numeric}  $data
     * @return array{0:float, 1:float}
     */
    private function amounts(array $data): array
    {
        $gross = round((float) ($data['gross_amount'] ?? 0), 2);
        $deduction = round((float) ($data['deduction_amount'] ?? 0), 2);

        if ($gross <= 0) {
            throw new InvalidArgumentException('Nominal bruto harus lebih dari nol.');
        }

        if ($deduction < 0 || $deduction > $gross) {
            throw new InvalidArgumentException('Potongan tidak boleh negatif atau melebihi nominal bruto.');
        }

        return [$gross, $deduction];
    }

    private function assertDraft(PayrollPeriod $period): void
    {
        if ($period->status !== PayrollStatus::Draft) {
            throw new InvalidArgumentException('Slip hanya dapat diubah saat periode masih berstatus draf.');
        }
    }

    private function assertAccounts(int $cashAccountId, int $expenseAccountId): void
    {
        $cash = Account::query()->find($cashAccountId);
        $expense = Account::query()->find($expenseAccountId);

        if ($cash === null || ! in_array($cash->type, [AccountType::Cash, AccountType::Bank], true)) {
            throw new InvalidArgumentException('Akun sumber harus berjenis kas atau bank.');
        }

        if ($expense === null || $expense->type !== AccountType::Expense) {
            throw new InvalidArgumentException('Akun beban honorarium harus berjenis beban.');
        }
    }

    /**
     * Nomor bukti kas keluar honorarium berurutan per hari.
     */
    private function nextNumber(string $date): string
    {
        $prefix = 'HONOR-'.date('Ymd', strtotime($date)).'-';

        $last = CashTransaction::withTrashed()
            ->where('transaction_number', 'like', $prefix.'%')
            ->orderByDesc('transaction_number')
            ->value('transaction_number');

        $sequence = $last === null ? 1 : ((int) substr((string) $last, -5)) + 1;

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Ringkasan slip per pegawai untuk ditampilkan sebagai rekap.
     *
     * @return array<string, float>
     */
    public function summaryByPosition(PayrollPeriod $period): array
    {
        return $period->items()
            ->get()
            ->groupBy(static fn (PayrollItem $item): string => (string) ($item->recipient_position ?: 'Lainnya'))
            ->map(static fn ($group): float => round((float) $group->sum('net_amount'), 2))
            ->all();
    }

    /**
     * Daftar pegawai aktif untuk pilihan penerima slip.
     *
     * @return \Illuminate\Support\Collection<int, StaffProfile>
     */
    public function eligibleStaff(?int $unitId = null): \Illuminate\Support\Collection
    {
        return StaffProfile::query()
            ->where('is_active', true)
            ->when($unitId !== null, fn ($query) => $query->where('unit_id', $unitId))
            ->orderBy('name')
            ->get();
    }
}
