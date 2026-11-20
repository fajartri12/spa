<?php

declare(strict_types=1);

namespace App\Livewire\Reports;

use App\Models\AcademicYear;
use App\Models\Account;
use App\Services\LedgerService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Laporan Buku Besar (General Ledger), Neraca Saldo, dan Ringkasan Laba-Rugi.
 */
#[Layout('layouts.app')]
#[Title('Buku Besar & Neraca Saldo')]
class LedgerIndex extends Component
{
    public string $activeTab = 'trial_balance'; // 'trial_balance' | 'ledger' | 'profit_loss'

    public ?int $academic_year_id = null;

    public ?int $selectedAccountId = null;

    public ?string $from = null;

    public ?string $to = null;

    public function mount(): void
    {
        $this->authorize('reports.view');

        $currentYear = AcademicYear::current() ?? AcademicYear::query()->orderByDesc('start_date')->first();
        $this->academic_year_id = $currentYear?->id;

        $firstAccount = Account::query()->ordered()->first();
        $this->selectedAccountId = $firstAccount?->id;
    }

    public function render(LedgerService $service): View
    {
        $this->authorize('reports.view');

        $fromDate = $this->from ? now()->parse($this->from) : null;
        $toDate = $this->to ? now()->parse($this->to) : null;

        $trialBalance = $this->activeTab === 'trial_balance'
            ? $service->trialBalance($this->academic_year_id, $fromDate, $toDate)
            : collect();

        $accountLedger = ($this->activeTab === 'ledger' && $this->selectedAccountId !== null)
            ? $service->accountLedger($this->selectedAccountId, $this->academic_year_id, $fromDate, $toDate)
            : null;

        $profitLoss = $this->activeTab === 'profit_loss'
            ? $service->profitLoss($this->academic_year_id, $fromDate, $toDate)
            : null;

        $totalOpeningDebit = 0.0;
        $totalOpeningCredit = 0.0;
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($trialBalance as $row) {
            $totalOpeningDebit += $row->opening_debit;
            $totalOpeningCredit += $row->opening_credit;
            $totalDebit += $row->debit;
            $totalCredit += $row->credit;
        }

        return view('livewire.reports.ledger-index', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'accounts' => Account::query()->ordered()->get()
                ->mapWithKeys(fn (Account $account) => [$account->id => $account->code.' — '.$account->name])
                ->all(),
            'trialBalance' => $trialBalance,
            'accountLedger' => $accountLedger,
            'profitLoss' => $profitLoss,
            'totalOpeningDebit' => $totalOpeningDebit,
            'totalOpeningCredit' => $totalOpeningCredit,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
        ]);
    }
}
