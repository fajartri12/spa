<?php

declare(strict_types=1);

namespace App\Livewire\Master;

use App\Enums\GatewayProvider;
use App\Models\Unit;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use App\Services\Gateway\GatewayManager;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Unit Pendidikan')]
class UnitIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $level = '';

    public string $payment_provider = '';

    public string $payment_channel = '';

    public bool $payment_gateway_enabled = false;

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
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('units.create');

        $this->resetForm();
        $this->sort_order = ((int) Unit::query()->max('sort_order')) + 1;
        $this->is_active = true;
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('units.update');

        $unit = Unit::query()->findOrFail($id);

        $this->editingId = $unit->id;
        $this->code = $unit->code;
        $this->name = $unit->name;
        $this->level = (string) $unit->level;
        $this->payment_provider = (string) ($unit->payment_provider?->value ?? '');
        $this->payment_channel = (string) $unit->payment_channel;
        $this->payment_gateway_enabled = (bool) $unit->payment_gateway_enabled;
        $this->description = (string) $unit->description;
        $this->sort_order = (int) $unit->sort_order;
        $this->is_active = (bool) $unit->is_active;

        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $isEditing = $this->editingId !== null;

        $this->authorize($isEditing ? 'units.update' : 'units.create');

        $data = $this->validate([
            'code' => [
                'required',
                'string',
                'max:20',
                'alpha_dash',
                Rule::unique('units', 'code')->ignore($this->editingId),
            ],
            'name' => ['required', 'string', 'max:120'],
            'level' => ['nullable', 'string', 'max:60'],
            'payment_provider' => [
                Rule::requiredIf(fn (): bool => $this->payment_gateway_enabled),
                'nullable',
                Rule::in(array_keys(GatewayProvider::options())),
            ],
            'payment_channel' => ['nullable', 'string', 'max:30'],
            'payment_gateway_enabled' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['required', 'boolean'],
        ]);

        // Kolom enum tidak menerima string kosong; simpan sebagai NULL.
        $data['payment_provider'] = $data['payment_provider'] !== '' ? $data['payment_provider'] : null;
        $data['payment_channel'] = $data['payment_channel'] !== '' ? $data['payment_channel'] : null;

        if ($isEditing) {
            $unit = Unit::query()->findOrFail($this->editingId);
            $unit->update($data);
            $logger->log('units.updated', "Memperbarui unit {$unit->name}", $unit);
            $message = 'Unit pendidikan berhasil diperbarui.';
        } else {
            $unit = Unit::query()->create($data);
            $logger->log('units.created', "Menambahkan unit {$unit->name}", $unit);
            $message = 'Unit pendidikan berhasil ditambahkan.';
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('units.delete');

        $this->deletingId = $id;
        $this->showDeleteModal = true;
    }

    public function delete(ActivityLogger $logger): void
    {
        $this->authorize('units.delete');

        if ($this->deletingId === null) {
            return;
        }

        $unit = Unit::query()->findOrFail($this->deletingId);

        if ($unit->schoolClasses()->exists() || $unit->students()->exists()) {
            $this->dispatch('toast', type: 'error', message: 'Unit tidak dapat dihapus karena masih memiliki relasi data kelas atau siswa.');
            $this->showDeleteModal = false;
            $this->deletingId = null;

            return;
        }

        $unitName = $unit->name;
        $unit->delete();
        $logger->log('units.deleted', "Menghapus unit {$unitName}", $unit);

        $this->showDeleteModal = false;
        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: "Unit {$unitName} berhasil dihapus.");
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->code = '';
        $this->name = '';
        $this->level = '';
        $this->payment_provider = '';
        $this->payment_channel = '';
        $this->payment_gateway_enabled = false;
        $this->description = '';
        $this->sort_order = 0;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function updatedPaymentProvider(): void
    {
        $this->payment_channel = '';
    }

    public function render(): View
    {
        $this->authorize('units.view');

        $units = Unit::query()
            ->search($this->search)
            ->when($this->statusFilter !== '', function ($query): void {
                $query->where('is_active', $this->statusFilter === '1');
            })
            ->ordered()
            ->withCount(['schoolClasses', 'students'])
            ->paginate(Pagination::perPage());

        $manager = app(GatewayManager::class);

        $channelOptions = $this->payment_provider !== ''
            ? $manager->channels($this->payment_provider)
            : [];

        return view('livewire.master.unit-index', [
            'units' => $units,
            'providerOptions' => GatewayProvider::options(),
            'channelOptions' => $channelOptions,
            'providerAvailability' => $manager->availability(),
        ]);
    }
}
