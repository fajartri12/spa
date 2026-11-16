<?php

declare(strict_types=1);

namespace App\Livewire\Bills;

use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\StudentDiscount;
use App\Services\ActivityLogger;
use App\Services\DiscountService;
use App\Support\Formatter;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Master potongan tagihan siswa (beasiswa, keringanan, anak pegawai).
 *
 * Potongan di sini bersifat permanen dan otomatis diterapkan saat tagihan
 * dibuat untuk kategori yang cocok.
 */
#[Layout('layouts.app')]
#[Title('Potongan Tagihan')]
class DiscountIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public string $activeFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $student_id = null;

    public ?int $payment_category_id = null;

    public string $type = DiscountService::TYPE_PERCENT;

    public string $value = '';

    public string $reason = '';

    public bool $is_active = true;

    public ?int $deletingId = null;

    public bool $showDeleteModal = false;

    public function mount(): void
    {
        $this->authorize('discounts.view');
    }

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedTypeFilter(): void { $this->resetPage(); }

    public function updatedActiveFilter(): void { $this->resetPage(); }

    public function create(): void
    {
        $this->authorize('discounts.create');

        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('discounts.update');

        $discount = StudentDiscount::query()->findOrFail($id);

        $this->editingId = $discount->id;
        $this->student_id = $discount->student_id;
        $this->payment_category_id = $discount->payment_category_id;
        $this->type = (string) $discount->type;
        $this->value = (string) (float) $discount->value;
        $this->reason = (string) $discount->reason;
        $this->is_active = (bool) $discount->is_active;
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'discounts.update' : 'discounts.create');

        $this->value = Formatter::parseRupiah($this->value);

        $data = $this->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'payment_category_id' => ['nullable', 'integer', 'exists:payment_categories,id'],
            'type' => ['required', Rule::in(array_keys(DiscountService::typeOptions()))],
            'value' => [
                'required',
                'numeric',
                'gt:0',
                $this->type === DiscountService::TYPE_PERCENT ? 'max:100' : 'max:9999999999999',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ], [
            'value.max' => $this->type === DiscountService::TYPE_PERCENT
                ? 'Potongan persen tidak boleh melebihi 100%.'
                : 'Nominal potongan terlalu besar.',
        ]);

        $data['payment_category_id'] = $data['payment_category_id'] ?: null;

        $duplicate = StudentDiscount::query()
            ->where('student_id', $data['student_id'])
            ->where('payment_category_id', $data['payment_category_id'])
            ->where('type', $data['type'])
            ->when($isEditing, fn (Builder $query) => $query->whereKeyNot($this->editingId))
            ->exists();

        if ($duplicate) {
            $this->addError('type', 'Potongan dengan siswa, kategori, dan tipe yang sama sudah ada.');

            return;
        }

        if ($isEditing) {
            $discount = StudentDiscount::query()->findOrFail($this->editingId);
            $discount->update($data);
            $logger->log('discounts.updated', 'Memperbarui potongan tagihan siswa', $discount);
            $message = 'Potongan tagihan berhasil diperbarui.';
        } else {
            $discount = StudentDiscount::query()->create($data);
            $logger->log('discounts.created', 'Menambahkan potongan tagihan siswa', $discount);
            $message = 'Potongan tagihan berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function toggleActive(int $id, ActivityLogger $logger): void
    {
        $this->authorize('discounts.update');

        $discount = StudentDiscount::query()->findOrFail($id);
        $discount->update(['is_active' => ! $discount->is_active]);

        $logger->log(
            'discounts.toggled',
            ($discount->is_active ? 'Mengaktifkan' : 'Menonaktifkan').' potongan tagihan siswa',
            $discount,
        );

        $this->dispatch('toast', type: 'success', message: 'Status potongan diperbarui.');
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('discounts.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('discounts.delete');

        if ($this->deletingId === null) {
            return;
        }

        $discount = StudentDiscount::query()->findOrFail($this->deletingId);
        $discount->delete();
        $logger->log('discounts.deleted', 'Menghapus potongan tagihan siswa', $discount);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: 'Potongan tagihan dihapus.');
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'activeFilter']);
        $this->resetPage();
    }

    private function resetForm(): void
    {
        $this->reset([
            'editingId',
            'student_id',
            'payment_category_id',
            'value',
            'reason',
        ]);

        $this->type = DiscountService::TYPE_PERCENT;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('discounts.view');

        $discounts = StudentDiscount::query()
            ->with(['student.unit', 'paymentCategory'])
            ->when(trim($this->search) !== '', function (Builder $query): void {
                $term = '%'.trim($this->search).'%';

                $query->whereHas('student', fn (Builder $student) => $student
                    ->where('name', 'like', $term)
                    ->orWhere('nis', 'like', $term));
            })
            ->when($this->typeFilter !== '', fn (Builder $query) => $query->where('type', $this->typeFilter))
            ->when($this->activeFilter !== '', fn (Builder $query) => $query->where('is_active', $this->activeFilter === '1'))
            ->latest('id')
            ->paginate(Pagination::perPage());

        return view('livewire.bills.discount-index', [
            'discounts' => $discounts,
            'typeOptions' => DiscountService::typeOptions(),
            'students' => Student::query()
                ->active()
                ->ordered()
                ->limit(300)
                ->get()
                ->mapWithKeys(fn (Student $student) => [$student->id => $student->nis.' — '.$student->name])
                ->all(),
            'categories' => PaymentCategory::query()->ordered()->pluck('name', 'id')->all(),
            'hasFilter' => trim($this->search) !== '' || $this->typeFilter !== '' || $this->activeFilter !== '',
        ]);
    }
}
