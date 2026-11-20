<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Models\AcademicYear;
use App\Models\Account;
use App\Services\BudgetService;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Penyusunan RAPBM (Budget) dan evaluasi perbandingan Budget vs Actual.
 */
#[Layout('layouts.app')]
#[Title('RAPBM & Anggaran')]
class BudgetIndex extends Component
{
    public ?int $academic_year_id = null;

    /** @var array<int, string> [account_id => amount] */
    public array $amounts = [];

    /** @var array<int, string> [account_id => notes] */
    public array $notes = [];

    public bool $isEditing = false;

    public function mount(): void
    {
        $this->authorize('budgets.view');

        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $this->academic_year_id = $currentYear?->id;

        $this->loadBudgets();
    }

    public function updatedAcademicYearId(): void
    {
        $this->loadBudgets();
    }

    public function loadBudgets(): void
    {
        if ($this->academic_year_id === null) {
            return;
        }

        $accounts = Account::query()->ordered()->get();
        $budgets = \App\Models\Budget::query()
            ->where('academic_year_id', $this->academic_year_id)
            ->get()
            ->keyBy('account_id');

        $this->amounts = [];
        $this->notes = [];

        foreach ($accounts as $account) {
            $budget = $budgets[$account->id] ?? null;
            $this->amounts[$account->id] = $budget ? (string) (float) $budget->planned_amount : '';
            $this->notes[$account->id] = $budget?->notes ?? '';
        }
    }

    public function enableEdit(): void
    {
        $this->authorize('budgets.update');
        $this->isEditing = true;
    }

    public function cancelEdit(): void
    {
        $this->isEditing = false;
        $this->loadBudgets();
    }

    public function saveBudget(BudgetService $service): void
    {
        $this->authorize('budgets.update');

        if ($this->academic_year_id === null) {
            return;
        }

        $items = [];
        foreach ($this->amounts as $accountId => $val) {
            $parsed = Formatter::parseRupiah((string) $val);
            if ($parsed > 0 || ! empty($this->notes[$accountId])) {
                $items[] = [
                    'account_id' => (int) $accountId,
                    'planned_amount' => (float) $parsed,
                    'notes' => $this->notes[$accountId] ?? null,
                ];
            }
        }

        $service->saveBudget($this->academic_year_id, $items, auth()->user());

        $this->isEditing = false;
        $this->loadBudgets();
        $this->dispatch('toast', type: 'success', message: 'Rencana anggaran RAPBM berhasil disimpan.');
    }

    public function render(BudgetService $service): View
    {
        $this->authorize('budgets.view');

        $comparison = $this->academic_year_id !== null
            ? $service->compare($this->academic_year_id)
            : collect();

        $academicYears = AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all();

        $totalPlannedRevenue = 0.0;
        $totalActualRevenue = 0.0;
        $totalPlannedExpense = 0.0;
        $totalActualExpense = 0.0;

        foreach ($comparison as $row) {
            if ($row->account->type->value === 'revenue') {
                $totalPlannedRevenue += $row->planned;
                $totalActualRevenue += $row->actual;
            } elseif ($row->account->type->value === 'expense') {
                $totalPlannedExpense += $row->planned;
                $totalActualExpense += $row->actual;
            }
        }

        return view('livewire.finance.budget-index', [
            'academicYears' => $academicYears,
            'comparison' => $comparison,
            'totalPlannedRevenue' => $totalPlannedRevenue,
            'totalActualRevenue' => $totalActualRevenue,
            'totalPlannedExpense' => $totalPlannedExpense,
            'totalActualExpense' => $totalActualExpense,
            'plannedSurplus' => $totalPlannedRevenue - $totalPlannedExpense,
            'actualSurplus' => $totalActualRevenue - $totalActualExpense,
        ]);
    }
}
