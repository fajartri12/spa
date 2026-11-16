<?php

declare(strict_types=1);

namespace App\Livewire\Payroll;

use App\Enums\AccountType;
use App\Enums\PayrollStatus;
use App\Models\Account;
use App\Models\AcademicYear;
use App\Models\PayrollPeriod;
use App\Models\Unit;
use App\Services\PayrollService;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Daftar periode penggajian / honorarium.
 *
 * Satu periode menampung banyak slip dan baru menghasilkan uang keluar setelah
 * disetujui, sehingga penyusunan slip tidak mengubah laporan keuangan.
 */
#[Layout('layouts.app')]
#[Title('Honorarium & Penggajian')]
class PayrollIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public string $period = '';

    public string $title = '';

    public string $description = '';

    public ?int $unitId = null;

    public ?int $academicYearId = null;

    public ?int $cashAccountId = null;

    public ?int $expenseAccountId = null;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function mount(): void
    {
        $this->authorize('payrolls.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('payrolls.create');

        $this->reset(['title', 'description', 'unitId', 'academicYearId', 'cashAccountId', 'expenseAccountId']);
        $this->period = now()->format('Y-m');
        $this->academicYearId = AcademicYear::current()?->id;
        $this->cashAccountId = Account::query()->active()
            ->where('type', AccountType::Cash->value)->ordered()->value('id');
        $this->expenseAccountId = Account::query()->active()
            ->where('type', AccountType::Expense->value)
            ->where('code', 'like', '51%')->ordered()->value('id');
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(PayrollService $service): void
    {
        $this->authorize('payrolls.create');

        $data = $this->validate([
            'period' => ['required', 'date_format:Y-m'],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:500'],
            'unitId' => ['nullable', 'integer', 'exists:units,id'],
            'academicYearId' => ['nullable', 'integer', 'exists:academic_years,id'],
            'cashAccountId' => ['required', 'integer', 'exists:accounts,id'],
            'expenseAccountId' => ['required', 'integer', 'exists:accounts,id', 'different:cashAccountId'],
        ], [
            'expenseAccountId.different' => 'Akun sumber dan akun beban tidak boleh sama.',
        ]);

        try {
            $period = $service->createPeriod([
                'period' => $data['period'],
                'title' => $data['title'],
                'description' => $data['description'] ?: null,
                'unit_id' => $data['unitId'] ?? null,
                'academic_year_id' => $data['academicYearId'] ?? null,
                'cash_account_id' => (int) $data['cashAccountId'],
                'expense_account_id' => (int) $data['expenseAccountId'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['cashAccountId' => $exception->getMessage()]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'title' => 'Periode dengan judul ini sudah ada untuk unit yang sama.',
            ]);
        }

        $this->showModal = false;
        $this->dispatch('toast', type: 'success', message: 'Periode honorarium dibuat. Tambahkan slip penerima.');
        $this->redirectRoute('payrolls.show', $period);
    }

    public function render(): View
    {
        $this->authorize('payrolls.view');

        $periods = PayrollPeriod::query()
            ->with(['unit:id,name', 'creator:id,name', 'approver:id,name'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', $term)
                        ->orWhere('period', 'like', $term)
                        ->orWhere('description', 'like', $term);
                });
            })
            ->when($this->statusFilter !== '', fn (Builder $query) => $query
                ->where('status', $this->statusFilter))
            ->latest('period')
            ->latest('id')
            ->paginate(Pagination::perPage());

        return view('livewire.payroll.payroll-index', [
            'periods' => $periods,
            'units' => Unit::query()->active()->ordered()->pluck('name', 'id')->all(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->pluck('name', 'id')->all(),
            'cashAccounts' => $this->accountOptions([AccountType::Cash, AccountType::Bank]),
            'expenseAccounts' => $this->accountOptions([AccountType::Expense]),
            'statusOptions' => $this->statusOptions(),
            'draftTotal' => (float) PayrollPeriod::query()
                ->where('status', PayrollStatus::Draft->value)->sum('total_amount'),
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

    /**
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        $options = [];

        foreach (PayrollStatus::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
