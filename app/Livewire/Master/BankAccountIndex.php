<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Unit;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Rekening Bank')]
class BankAccountIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $unit_id = null;

    public ?int $account_id = null;

    public string $bank_name = '';

    public string $account_number = '';

    public string $account_name = '';

    public string $branch = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('bank-accounts.create');

        $this->resetForm();
        $this->unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : null;
        $this->sort_order = ((int) BankAccount::query()->max('sort_order')) + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('bank-accounts.update');

        $rekening = BankAccount::query()->findOrFail($id);

        $this->editingId = $rekening->id;
        $this->unit_id = $rekening->unit_id !== null ? (int) $rekening->unit_id : null;
        $this->account_id = $rekening->account_id !== null ? (int) $rekening->account_id : null;
        $this->bank_name = $rekening->bank_name;
        $this->account_number = $rekening->account_number;
        $this->account_name = $rekening->account_name;
        $this->branch = (string) $rekening->branch;
        $this->description = (string) $rekening->description;
        $this->sort_order = (int) $rekening->sort_order;
        $this->is_active = (bool) $rekening->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'bank-accounts.update' : 'bank-accounts.create');

        if ($this->unit_id === 0) {
            $this->unit_id = null;
        }

        if ($this->account_id === 0) {
            $this->account_id = null;
        }

        $data = $this->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'bank_name' => ['required', 'string', 'max:60'],
            'account_number' => [
                'required',
                'string',
                'max:40',
                Rule::unique('bank_accounts', 'account_number')
                    ->where('bank_name', $this->bank_name)
                    ->ignore($this->editingId),
            ],
            'account_name' => ['required', 'string', 'max:120'],
            'branch' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'account_number.unique' => 'Nomor rekening tersebut sudah terdaftar pada bank yang sama.',
        ]);

        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        if ($isEditing) {
            $rekening = BankAccount::query()->findOrFail($this->editingId);
            $rekening->update($data);
            $logger->log('bank-accounts.updated', "Memperbarui rekening {$rekening->label()}", $rekening);
            $message = 'Rekening bank berhasil diperbarui.';
        } else {
            $rekening = BankAccount::query()->create($data);
            $logger->log('bank-accounts.created', "Menambahkan rekening {$rekening->label()}", $rekening);
            $message = 'Rekening bank berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('bank-accounts.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('bank-accounts.delete');

        if ($this->deletingId === null) {
            return;
        }

        $rekening = BankAccount::query()->findOrFail($this->deletingId);
        $label = $rekening->label();

        $rekening->delete();
        $logger->log('bank-accounts.deleted', "Menghapus rekening {$label}", $rekening);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Rekening {$label} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->unit_id = null;
        $this->account_id = null;
        $this->bank_name = '';
        $this->account_number = '';
        $this->account_name = '';
        $this->branch = '';
        $this->description = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('bank-accounts.view');

        $bankAccounts = BankAccount::query()
            ->search($this->search)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->ordered()
            ->with(['unit:id,name', 'account:id,code,name'])
            ->paginate(Pagination::perPage());

        return view('livewire.master.bank-account-index', [
            'bankAccounts' => $bankAccounts,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'accounts' => Account::query()->ordered()
                ->get()
                ->mapWithKeys(fn (Account $account): array => [$account->id => "{$account->code} — {$account->name}"])
                ->all(),
        ]);
    }
}
