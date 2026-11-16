<?php

declare(strict_types=1);

namespace App\Livewire\Finance;

use App\Enums\AccountType;
use App\Enums\ReconciliationMatchStatus;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\BankStatementProfile;
use App\Models\Payment;
use App\Services\BankReconciliationService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Import mutasi rekening koran dan pencocokan otomatis dengan pembayaran.
 *
 * Bendahara memilih profil bank, mengunggah berkas ekspor, lalu sistem
 * mencocokkan baris kredit berdasarkan nomor referensi, nominal unik, atau
 * nominal + tanggal. Baris yang tidak yakin dibiarkan untuk dicocokkan manual.
 */
#[Layout('layouts.app')]
#[Title('Rekonsiliasi Bank')]
class BankReconciliationIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    public bool $showImportModal = false;

    public ?int $profileId = null;

    public ?int $bankAccountId = null;

    public ?int $accountId = null;

    public bool $autoMatch = true;

    public string $notes = '';

    /**
     * @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null
     */
    public $file = null;

    // Override kolom (opsional, untuk berkas yang formatnya berbeda).
    public string $columnDate = '';

    public string $columnDescription = '';

    public string $columnReference = '';

    public string $columnDebit = '';

    public string $columnCredit = '';

    public string $columnBalance = '';

    public ?int $activeImportId = null;

    public string $statusFilter = '';

    public string $search = '';

    public bool $showMatchModal = false;

    public ?int $matchingLineId = null;

    public string $paymentSearch = '';

    public ?int $selectedPaymentId = null;

    public function mount(): void
    {
        $this->authorize('bank-reconciliations.view');

        $this->activeImportId = BankStatementImport::query()->latest('id')->value('id');
    }

    public function updatedSearch(): void
    {
        $this->resetPage('linePage');
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage('linePage');
    }

    public function updatedActiveImportId(): void
    {
        $this->resetPage('linePage');
    }

    public function openImport(): void
    {
        $this->authorize('bank-reconciliations.import');

        $this->reset(['file', 'notes', 'columnDate', 'columnDescription', 'columnReference', 'columnDebit', 'columnCredit', 'columnBalance']);
        $this->profileId = BankStatementProfile::query()->active()->ordered()->value('id');
        $this->bankAccountId = null;
        $this->accountId = null;
        $this->autoMatch = true;
        $this->resetErrorBag();
        $this->showImportModal = true;
    }

    public function import(BankReconciliationService $service): void
    {
        $this->authorize('bank-reconciliations.import');

        $data = $this->validate([
            'profileId' => ['required', 'integer', 'exists:bank_statement_profiles,id'],
            'bankAccountId' => ['nullable', 'integer', 'exists:bank_accounts,id'],
            'accountId' => ['nullable', 'integer', 'exists:accounts,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:500'],
            'autoMatch' => ['required', 'boolean'],
            'columnDate' => ['nullable', 'string', 'max:60'],
            'columnDescription' => ['nullable', 'string', 'max:60'],
            'columnReference' => ['nullable', 'string', 'max:60'],
            'columnDebit' => ['nullable', 'string', 'max:60'],
            'columnCredit' => ['nullable', 'string', 'max:60'],
            'columnBalance' => ['nullable', 'string', 'max:60'],
        ], [
            'file.mimes' => 'Berkas harus berformat CSV atau Excel (xlsx/xls).',
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        $profile = BankStatementProfile::query()->findOrFail($data['profileId']);

        $overrides = array_filter([
            'transacted_at' => $data['columnDate'] ?: null,
            'description' => $data['columnDescription'] ?: null,
            'reference' => $data['columnReference'] ?: null,
            'debit' => $data['columnDebit'] ?: null,
            'credit' => $data['columnCredit'] ?: null,
            'balance' => $data['columnBalance'] ?: null,
        ]);

        try {
            $import = $service->import(
                $profile,
                $this->file,
                [
                    'bank_account_id' => $data['bankAccountId'] ?? null,
                    'account_id' => $data['accountId'] ?? null,
                    'notes' => $data['notes'] ?: null,
                    'overrides' => $overrides,
                    'auto_match' => (bool) $data['autoMatch'],
                ],
                auth()->user(),
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $this->activeImportId = $import->id;
        $this->showImportModal = false;
        $this->resetPage('linePage');

        $this->dispatch(
            'toast',
            type: 'success',
            message: "Mutasi diimpor: {$import->matched_rows} dari {$import->total_rows} baris tercocokkan otomatis.",
        );
    }

    /**
     * Terapkan ulang aturan pencocokan pada baris yang belum cocok.
     */
    public function runAutoMatch(BankReconciliationService $service): void
    {
        $this->authorize('bank-reconciliations.match');

        $import = $this->resolveActiveImport();

        if ($import === null) {
            $this->dispatch('toast', type: 'error', message: 'Pilih berkas mutasi terlebih dahulu.');

            return;
        }

        $matched = $service->autoMatch($import, auth()->user());
        $import->refreshSummary();

        $this->dispatch('toast', type: 'success', message: "{$matched} baris berhasil dicocokkan otomatis.");
    }

    public function startMatch(int $lineId): void
    {
        $this->authorize('bank-reconciliations.match');

        $this->matchingLineId = $lineId;
        $this->selectedPaymentId = null;
        $this->paymentSearch = '';
        $this->resetErrorBag();
        $this->showMatchModal = true;
    }

    public function match(BankReconciliationService $service): void
    {
        $this->authorize('bank-reconciliations.match');

        $data = $this->validate([
            'matchingLineId' => ['required', 'integer'],
            'selectedPaymentId' => ['required', 'integer', 'exists:payments,id'],
        ], [
            'selectedPaymentId.required' => 'Pilih pembayaran yang sesuai.',
        ]);

        $line = BankStatementLine::query()->findOrFail($data['matchingLineId']);
        $payment = Payment::query()->findOrFail($data['selectedPaymentId']);

        try {
            $service->match($line, $payment, auth()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['selectedPaymentId' => $exception->getMessage()]);
        }

        $this->refreshActiveImportSummary();

        $this->showMatchModal = false;
        $this->reset('matchingLineId', 'selectedPaymentId', 'paymentSearch');
        $this->dispatch('toast', type: 'success', message: 'Baris mutasi berhasil dicocokkan.');
    }

    public function unmatch(int $lineId, BankReconciliationService $service): void
    {
        $this->authorize('bank-reconciliations.match');

        $line = BankStatementLine::query()->findOrFail($lineId);
        $service->unmatch($line, auth()->user());

        $this->refreshActiveImportSummary();
        $this->dispatch('toast', type: 'success', message: 'Pencocokan dilepas.');
    }

    public function ignoreLine(int $lineId, BankReconciliationService $service): void
    {
        $this->authorize('bank-reconciliations.match');

        $line = BankStatementLine::query()->findOrFail($lineId);
        $service->ignore($line, auth()->user());

        $this->refreshActiveImportSummary();
        $this->dispatch('toast', type: 'success', message: 'Baris mutasi diabaikan.');
    }

    public function render(): View
    {
        $this->authorize('bank-reconciliations.view');

        $imports = BankStatementImport::query()
            ->with('profile')
            ->latest('id')
            ->limit(50)
            ->get();

        $activeImport = $this->resolveActiveImport();

        $lines = $activeImport === null
            ? null
            : BankStatementLine::query()
                ->where('bank_statement_import_id', $activeImport->id)
                ->when($this->statusFilter !== '', fn (Builder $query) => $query
                    ->where('match_status', $this->statusFilter))
                ->when(trim($this->search) !== '', function (Builder $query): void {
                    $term = '%'.trim($this->search).'%';

                    $query->where(function (Builder $inner) use ($term): void {
                        $inner->where('description', 'like', $term)
                            ->orWhere('reference', 'like', $term);
                    });
                })
                ->with('payment.student')
                ->orderBy('transacted_at')
                ->orderBy('row_number')
                ->paginate(Pagination::perPage(), pageName: 'linePage');

        return view('livewire.finance.bank-reconciliation-index', [
            'imports' => $imports,
            'activeImport' => $activeImport,
            'lines' => $lines,
            'profiles' => BankStatementProfile::query()->active()->ordered()->get(),
            'bankAccounts' => BankAccount::query()->active()->ordered()->get()
                ->mapWithKeys(fn (BankAccount $account): array => [$account->id => $account->label()])
                ->all(),
            'cashAccounts' => Account::query()->active()
                ->whereIn('type', [AccountType::Cash->value, AccountType::Bank->value])
                ->ordered()->get()
                ->mapWithKeys(fn (Account $account): array => [$account->id => $account->code.' - '.$account->name])
                ->all(),
            'matchStatusOptions' => $this->matchStatusOptions(),
            'paymentCandidates' => $this->paymentCandidates(),
        ]);
    }

    private function resolveActiveImport(): ?BankStatementImport
    {
        return $this->activeImportId === null
            ? null
            : BankStatementImport::query()->find($this->activeImportId);
    }

    private function refreshActiveImportSummary(): void
    {
        $this->resolveActiveImport()?->refreshSummary();
    }

    /**
     * Kandidat pembayaran untuk dialog pencocokan manual.
     *
     * @return array<int, string>
     */
    private function paymentCandidates(): array
    {
        if ($this->matchingLineId === null) {
            return [];
        }

        $line = BankStatementLine::query()->find($this->matchingLineId);

        if ($line === null) {
            return [];
        }

        return Payment::query()
            ->with('student:id,name')
            ->whereIn('status', ['pending', 'approved'])
            ->whereDoesntHave('reconciliationLines')
            ->when(trim($this->paymentSearch) !== '', function (Builder $query): void {
                $term = '%'.trim($this->paymentSearch).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('transaction_number', 'like', $term)
                        ->orWhereHas('student', fn (Builder $student) => $student->where('name', 'like', $term));
                });
            })
            ->orderByRaw('ABS(amount - ?) asc', [(float) $line->credit])
            ->orderByDesc('paid_at')
            ->limit(25)
            ->get()
            ->mapWithKeys(static fn (Payment $payment): array => [
                $payment->id => $payment->transaction_number
                    .' — '.($payment->student?->name ?? '-')
                    .' — Rp'.number_format((float) $payment->amount, 0, ',', '.')
                    .' ('.$payment->paid_at?->format('d/m/Y').')',
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private function matchStatusOptions(): array
    {
        $options = [];

        foreach (ReconciliationMatchStatus::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
