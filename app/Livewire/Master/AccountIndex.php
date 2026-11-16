<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\AccountType;
use App\Models\Account;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use App\Support\Formatter;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Akun Keuangan')]
class AccountIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'cash';

    public string $opening_balance = '0';

    public string $description = '';

    public bool $is_active = true;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    /**
     * @var array<string, mixed>
     */
    protected $queryString = [
        'search' => ['except' => ''],
        'typeFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('accounts.create');

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('accounts.update');

        $account = Account::query()->findOrFail($id);

        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->type = $account->type?->value ?? AccountType::Cash->value;
        $this->opening_balance = (string) $account->opening_balance;
        $this->description = (string) $account->description;
        $this->is_active = (bool) $account->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'accounts.update' : 'accounts.create');

        $this->opening_balance = Formatter::parseRupiah($this->opening_balance);

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('accounts', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'opening_balance' => ['required', 'numeric'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Kode akun sudah digunakan.',
        ]);

        $data['code'] = strtoupper($data['code']);

        if (is_string($data['description']) && trim($data['description']) === '') {
            $data['description'] = null;
        }

        if ($isEditing) {
            $account = Account::query()->findOrFail($this->editingId);
            $account->update($data);
            $logger->log('accounts.updated', "Memperbarui akun {$account->name}", $account);
            $message = 'Akun keuangan berhasil diperbarui.';
        } else {
            $account = Account::query()->create($data);
            $logger->log('accounts.created', "Menambahkan akun {$account->name}", $account);
            $message = 'Akun keuangan berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('accounts.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('accounts.delete');

        if ($this->deletingId === null) {
            return;
        }

        $account = Account::query()->withCount('bankAccounts')->findOrFail($this->deletingId);

        if ($account->bank_accounts_count > 0) {
            $this->dispatch('toast', type: 'error', message: 'Akun tidak dapat dihapus karena masih digunakan oleh rekening bank.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $accountName = $account->name;
        $account->delete();
        $logger->log('accounts.deleted', "Menghapus akun {$accountName}", $account);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Akun {$accountName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->type = AccountType::Cash->value;
        $this->opening_balance = '0';
        $this->description = '';
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('accounts.view');

        $accounts = Account::query()
            ->search($this->search)
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->ordered()
            ->withCount('bankAccounts')
            ->paginate(Pagination::perPage());

        return view('livewire.master.account-index', [
            'accounts' => $accounts,
            'types' => AccountType::options(),
        ]);
    }
}
