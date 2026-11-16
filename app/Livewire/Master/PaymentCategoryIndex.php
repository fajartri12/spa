<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\PaymentCategoryType;
use App\Models\AppSetting;
use App\Models\PaymentCategory;
use App\Models\Unit;
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
#[Title('Kategori Pembayaran')]
class PaymentCategoryIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $unitFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $unit_id = null;

    public string $code = '';

    public string $name = '';

    public string $type = 'monthly';

    public string $default_amount = '0';

    public int $due_day = 10;

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
        'typeFilter' => ['except' => ''],
        'unitFilter' => ['except' => ''],
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

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('payment-categories.create');

        $this->resetForm();
        $this->unit_id = $this->unitFilter !== '' ? (int) $this->unitFilter : null;
        $this->sort_order = ((int) PaymentCategory::query()->max('sort_order')) + 1;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('payment-categories.update');

        $category = PaymentCategory::query()->findOrFail($id);

        $this->editingId = $category->id;
        $this->unit_id = $category->unit_id !== null ? (int) $category->unit_id : null;
        $this->code = $category->code;
        $this->name = $category->name;
        $this->type = $category->type?->value ?? PaymentCategoryType::Monthly->value;
        $this->default_amount = (string) $category->default_amount;
        $this->due_day = (int) ($category->due_day ?? 10);
        $this->description = (string) $category->description;
        $this->sort_order = (int) $category->sort_order;
        $this->is_active = (bool) $category->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'payment-categories.update' : 'payment-categories.create');

        if ($this->unit_id === 0) {
            $this->unit_id = null;
        }

        // Nominal diketik pengguna dengan pemisah ribuan; bersihkan sebelum validasi.
        $this->default_amount = Formatter::parseRupiah($this->default_amount);

        $data = $this->validate([
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'code' => ['required', 'string', 'max:30', Rule::unique('payment_categories', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::enum(PaymentCategoryType::class)],
            'default_amount' => [
                'required',
                'numeric',
                'min:'.(float) AppSetting::get('min_category_amount', 0),
                'max:'.(float) AppSetting::get('max_category_amount', 10000000),
            ],
            'due_day' => ['required', 'integer', 'min:1', 'max:31'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ], [
            'code.unique' => 'Kode kategori sudah digunakan.',
            'default_amount.min' => 'Nominal kategori di bawah batas minimal pengaturan sistem.',
            'default_amount.max' => 'Nominal kategori melebihi batas maksimal pengaturan sistem.',
        ]);

        $data['code'] = strtoupper($data['code']);

        foreach ($data as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                $data[$key] = null;
            }
        }

        if ($isEditing) {
            $category = PaymentCategory::query()->findOrFail($this->editingId);
            $category->update($data);
            $logger->log('payment-categories.updated', "Memperbarui kategori {$category->name}", $category);
            $message = 'Kategori pembayaran berhasil diperbarui.';
        } else {
            $category = PaymentCategory::query()->create($data);
            $logger->log('payment-categories.created', "Menambahkan kategori {$category->name}", $category);
            $message = 'Kategori pembayaran berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('payment-categories.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('payment-categories.delete');

        if ($this->deletingId === null) {
            return;
        }

        $category = PaymentCategory::query()->findOrFail($this->deletingId);
        $categoryName = $category->name;

        $category->delete();
        $logger->log('payment-categories.deleted', "Menghapus kategori {$categoryName}", $category);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Kategori {$categoryName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->unitFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->unit_id = null;
        $this->code = '';
        $this->name = '';
        $this->type = PaymentCategoryType::Monthly->value;
        $this->default_amount = '0';
        $this->due_day = 10;
        $this->description = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('payment-categories.view');

        $categories = PaymentCategory::query()
            ->search($this->search)
            ->when($this->typeFilter !== '', fn ($query) => $query->where('type', $this->typeFilter))
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->ordered()
            ->with('unit:id,name')
            ->paginate(Pagination::perPage());

        return view('livewire.master.payment-category-index', [
            'categories' => $categories,
            'units' => Unit::query()->ordered()->pluck('name', 'id')->all(),
            'types' => PaymentCategoryType::options(),
        ]);
    }
}
