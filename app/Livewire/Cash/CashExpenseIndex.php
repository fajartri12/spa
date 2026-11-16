<?php

declare(strict_types=1);

namespace App\Livewire\Cash;

use App\Enums\AccountType;
use App\Enums\CashTransactionType;
use App\Models\Account;
use App\Models\CashTransaction;
use App\Services\CashExpenseService;
use App\Support\Formatter;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pengeluaran kas madrasah (operasional, kegiatan, ATK, dan sejenisnya).
 *
 * Setiap pengeluaran otomatis menghasilkan jurnal double-entry sehingga
 * laporan arus kas menampilkan sisi keluar tanpa entri manual terpisah.
 */
#[Layout('layouts.app')]
#[Title('Kas Keluar')]
class CashExpenseIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $accountId = null;

    public bool $showModal = false;

    public bool $showVoidModal = false;

    public ?int $voidingId = null;

    public string $voidReason = '';

    public ?int $cash_account_id = null;

    public ?int $expense_account_id = null;

    public string $amount = '';

    public string $transacted_at = '';

    public string $description = '';

    public string $reference_number = '';

    public function mount(): void
    {
        $this->authorize('cash-transactions.view');

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->transacted_at = now()->toDateString();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedAccountId(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('cash-transactions.create');

        $this->reset(['cash_account_id', 'expense_account_id', 'amount', 'description', 'reference_number']);
        $this->transacted_at = now()->toDateString();
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(CashExpenseService $service): void
    {
        $this->authorize('cash-transactions.create');

        $this->amount = Formatter::parseRupiah($this->amount);

        $data = $this->validate([
            'cash_account_id' => ['required', 'integer', 'exists:accounts,id'],
            'expense_account_id' => ['required', 'integer', 'exists:accounts,id', 'different:cash_account_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'transacted_at' => ['required', 'date', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:255'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ], [
            'expense_account_id.different' => 'Akun sumber dan akun beban tidak boleh sama.',
            'transacted_at.before_or_equal' => 'Tanggal pengeluaran tidak boleh melebihi hari ini.',
        ]);

        try {
            $service->record([
                'account_id' => (int) $data['cash_account_id'],
                'expense_account_id' => (int) $data['expense_account_id'],
                'amount' => (float) $data['amount'],
                'transacted_at' => $data['transacted_at'],
                'description' => $data['description'],
                'reference_number' => filled($data['reference_number']) ? (string) $data['reference_number'] : null,
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('description', $exception->getMessage());

            return;
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Pengeluaran kas berhasil dicatat beserta jurnalnya.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'accountId']);
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function confirmVoid(int $transactionId): void
    {
        $this->authorize('cash-transactions.delete');

        $this->voidingId = $transactionId;
        $this->voidReason = '';
        $this->resetErrorBag();
        $this->showVoidModal = true;
    }

    public function void(CashExpenseService $service): void
    {
        $this->authorize('cash-transactions.delete');

        $data = $this->validate([
            'voidReason' => ['required', 'string', 'max:255'],
        ], [
            'voidReason.required' => 'Alasan pembatalan wajib diisi.',
        ]);

        $transaction = CashTransaction::query()
            ->where('type', CashTransactionType::Expense->value)
            ->findOrFail($this->voidingId);

        try {
            $service->void($transaction, $data['voidReason'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('voidReason', $exception->getMessage());

            return;
        }

        $this->showVoidModal = false;
        $this->dispatch('toast', type: 'success', message: 'Pengeluaran dibatalkan dan jurnal ditandai void.');
    }

    public function render(): View
    {
        $this->authorize('cash-transactions.view');

        $filtered = fn (): Builder => CashTransaction::query()
            ->where('type', CashTransactionType::Expense->value)
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('description', 'like', $term)
                        ->orWhere('transaction_number', 'like', $term)
                        ->orWhere('reference_number', 'like', $term);
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('transacted_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('transacted_at', '<=', $this->dateTo))
            ->when($this->accountId !== null, fn (Builder $query) => $query->where('account_id', $this->accountId));

        $transactions = $filtered()
            ->with(['account', 'user'])
            ->latest('transacted_at')
            ->orderByDesc('id')
            ->paginate(Pagination::perPage());

        return view('livewire.cash.cash-expense-index', [
            'transactions' => $transactions,
            'totalExpense' => (float) $filtered()->sum('amount'),
            'cashAccounts' => $this->accountOptions([AccountType::Cash, AccountType::Bank]),
            'expenseAccounts' => $this->accountOptions([AccountType::Expense]),
            'filterAccounts' => $this->accountOptions([AccountType::Cash, AccountType::Bank]),
        ]);
    }

    /**
     * @param  list<AccountType>  $types
     * @return array<int, string>
     */
    private function accountOptions(array $types): array
    {
        return Account::query()
            ->active()
            ->whereIn('type', array_map(static fn (AccountType $type): string => $type->value, $types))
            ->ordered()
            ->get()
            ->mapWithKeys(static fn (Account $account): array => [$account->id => $account->code.' - '.$account->name])
            ->all();
    }
}
