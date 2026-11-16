<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Enums\JournalStatus;
use App\Models\Journal;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar jurnal umum beserta baris debit/kredit.
 *
 * Jurnal dibuat otomatis oleh pengeluaran kas dan penerimaan PPDB. Halaman ini
 * membuat entri tersebut dapat diperiksa tanpa membuka database.
 */
#[Layout('layouts.app')]
#[Title('Jurnal Umum')]
class JournalIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $statusFilter = '';

    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->authorize('reports.view');

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
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

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function toggle(int $journalId): void
    {
        $this->expandedId = $this->expandedId === $journalId ? null : $journalId;
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'statusFilter', 'expandedId']);
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    /**
     * @return Builder<Journal>
     */
    private function query(): Builder
    {
        return Journal::query()
            ->with(['user', 'details.account'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('journals.journal_number', 'like', $term)
                        ->orWhere('journals.description', 'like', $term)
                        ->orWhere('journals.reference_number', 'like', $term);
                });
            })
            ->when($this->dateFrom !== '', fn (Builder $query) => $query->whereDate('journal_date', '>=', $this->dateFrom))
            ->when($this->dateTo !== '', fn (Builder $query) => $query->whereDate('journal_date', '<=', $this->dateTo))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter));
    }

    public function render(): View
    {
        $this->authorize('reports.view');

        $summary = $this->query()
            ->join('journal_details', 'journals.id', '=', 'journal_details.journal_id')
            ->selectRaw('COALESCE(SUM(journal_details.debit), 0) as debit_total')
            ->selectRaw('COALESCE(SUM(journal_details.credit), 0) as credit_total')
            ->first();

        return view('livewire.reports.journal-index', [
            'journals' => $this->query()->orderByDesc('journal_date')->orderByDesc('id')->paginate(Pagination::perPage()),
            'debitTotal' => (float) ($summary->debit_total ?? 0),
            'creditTotal' => (float) ($summary->credit_total ?? 0),
            'statusOptions' => array_reduce(
                JournalStatus::cases(),
                static function (array $carry, JournalStatus $status): array {
                    $carry[$status->value] = ucfirst($status->value);

                    return $carry;
                },
                [],
            ),
        ]);
    }
}
