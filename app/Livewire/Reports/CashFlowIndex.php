<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\CashTransactionType;
use App\Models\Account;
use App\Models\CashTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.app')]
#[Title('Arus Kas')]
class CashFlowIndex extends Component
{
    use WithPagination;

    /**
     * @var list<int>
     */
    private const PER_PAGE_OPTIONS = [10, 25, 50, 100];

    /**
     * @var list<string>
     */
    private const SORTABLE = ['transacted_at', 'amount', 'transaction_number'];

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $typeFilter = '';

    public ?int $accountId = null;

    public int $perPage = 25;

    public bool $showFilters = false;

    public string $sortField = 'transacted_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('reports.view');

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }
    public function updatedTypeFilter(): void { $this->resetPage(); }
    public function updatedAccountId(): void { $this->resetPage(); }
    public function updatedPerPage(): void { $this->resetPage(); }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    public function toggleFilters(): void
    {
        $this->showFilters = ! $this->showFilters;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->accountId = null;
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    /**
     * @return Builder<CashTransaction>
     */
    private function query(): Builder
    {
        return CashTransaction::query()
            ->with(['account', 'payment.student.unit'])
            ->when($this->search !== '', fn (Builder $q) => $q->where(function (Builder $query): void {
                $term = '%' . $this->search . '%';

                $query->where('transaction_number', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('reference_number', 'like', $term)
                    ->orWhereHas('account', fn (Builder $account) => $account
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term))
                    ->orWhereHas('payment.student', fn (Builder $student) => $student
                        ->where('name', 'like', $term)
                        ->orWhere('nis', 'like', $term));
            }))
            ->when($this->dateFrom !== '', fn (Builder $q) => $q->whereDate('transacted_at', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('transacted_at', '<=', $this->dateTo))
            ->when($this->typeFilter !== '', fn (Builder $q) => $q->where('type', $this->typeFilter))
            ->when($this->accountId, fn (Builder $q) => $q->where('account_id', $this->accountId));
    }

    /**
     * Saldo kas kumulatif sampai tanggal akhir filter (saldo awal + mutasi).
     */
    private function closingBalance(): float
    {
        $opening = (float) Account::query()
            ->when($this->accountId, fn (Builder $q) => $q->whereKey($this->accountId))
            ->sum('opening_balance');

        $lifetime = CashTransaction::query()
            ->when($this->accountId, fn (Builder $q) => $q->where('account_id', $this->accountId))
            ->when($this->dateTo !== '', fn (Builder $q) => $q->whereDate('transacted_at', '<=', $this->dateTo))
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense_total")
            ->first();

        return $opening
            + (float) ($lifetime->income_total ?? 0)
            - (float) ($lifetime->expense_total ?? 0);
    }

    public function export(): StreamedResponse
    {
        $this->authorize('reports.export');

        $rows = $this->query()->orderBy($this->sortField, $this->sortDirection)->get();

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');

            fputcsv($output, ['No. Transaksi', 'Waktu Transaksi', 'Unit', 'Kode Akun', 'Nama Akun', 'Tujuan Transaksi', 'Jenis Transaksi', 'Jumlah Transaksi', 'Referensi'], escape: '');

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row->transaction_number,
                    $row->transacted_at?->format('d/m/Y H:i'),
                    $row->payment?->student?->unit?->name ?? '-',
                    $row->account?->code ?? '-',
                    $row->account?->name ?? '-',
                    $row->description,
                    self::typeLabel($row->type),
                    (float) $row->amount,
                    $row->reference_number ?? '-',
                ], escape: '');
            }

            fclose($output);
        }, 'arus-kas-' . now()->format('YmdHis') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        $this->authorize('reports.view');

        $summary = $this->query()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as income_total")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as expense_total")
            ->selectRaw('COUNT(*) as transaction_count')
            ->first();

        $income = (float) ($summary->income_total ?? 0);
        $expense = (float) ($summary->expense_total ?? 0);

        return view('livewire.reports.cash-flow-index', [
            'transactions' => $this->query()
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate($this->perPage),
            'incomeTotal' => $income,
            'expenseTotal' => $expense,
            'balance' => $income - $expense,
            'closingBalance' => $this->closingBalance(),
            'transactionCount' => (int) ($summary->transaction_count ?? 0),
            'accounts' => Account::query()->active()->ordered()->pluck('name', 'id')->all(),
            'perPageOptions' => array_combine(
                self::PER_PAGE_OPTIONS,
                array_map(static fn (int $value): string => (string) $value, self::PER_PAGE_OPTIONS),
            ),
            'types' => array_reduce(
                CashTransactionType::cases(),
                static function (array $carry, CashTransactionType $type): array {
                    $carry[$type->value] = self::typeLabel($type);

                    return $carry;
                },
                [],
            ),
        ]);
    }

    public static function typeLabel(?CashTransactionType $type): string
    {
        return match ($type) {
            CashTransactionType::Income => 'Pemasukan',
            CashTransactionType::Expense => 'Pengeluaran',
            CashTransactionType::Transfer => 'Transfer',
            CashTransactionType::Adjustment => 'Penyesuaian',
            default => '-',
        };
    }

    public static function typeBadge(?CashTransactionType $type): string
    {
        return match ($type) {
            CashTransactionType::Income => 'success',
            CashTransactionType::Expense => 'danger',
            CashTransactionType::Transfer => 'info',
            CashTransactionType::Adjustment => 'warning',
            default => 'neutral',
        };
    }
}