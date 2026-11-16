<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentCategoryType;
use App\Livewire\Master\PaymentCategoryIndex;
use App\Models\PaymentCategory;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentCategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_guest_cannot_access_payment_category_page(): void
    {
        $this->get(route('payment-categories.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_payment_category_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('payment-categories.index'))
            ->assertOk()
            ->assertSee('Kategori Pembayaran');
    }

    public function test_user_without_permission_cannot_view_payment_category_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('payment-categories.index'))
            ->assertForbidden();
    }

    public function test_can_create_payment_category(): void
    {
        $unit = Unit::query()->firstOrFail();

        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('create')
            ->set('code', 'SPP')
            ->set('name', 'Sumbangan Pembinaan Pendidikan')
            ->set('type', PaymentCategoryType::Monthly->value)
            ->set('default_amount', '350000')
            ->set('due_day', 10)
            ->set('unit_id', $unit->id)
            ->set('sort_order', 1)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('payment_categories', [
            'code' => 'SPP',
            'name' => 'Sumbangan Pembinaan Pendidikan',
            'type' => PaymentCategoryType::Monthly->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_code_is_stored_in_uppercase(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('create')
            ->set('code', 'uang-gedung')
            ->set('name', 'Uang Gedung')
            ->set('type', PaymentCategoryType::Once->value)
            ->set('default_amount', '1500000')
            ->set('due_day', 15)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_categories', ['code' => 'UANG-GEDUNG']);
    }

    public function test_duplicate_code_fails_validation(): void
    {
        PaymentCategory::query()->create([
            'code' => 'SPP',
            'name' => 'SPP Lama',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 350000,
            'due_day' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('create')
            ->set('code', 'SPP')
            ->set('name', 'SPP Baru')
            ->set('type', PaymentCategoryType::Monthly->value)
            ->set('default_amount', '400000')
            ->set('due_day', 10)
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_due_day_must_be_between_1_and_31(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('create')
            ->set('code', 'SPP')
            ->set('name', 'SPP Bulanan')
            ->set('type', PaymentCategoryType::Monthly->value)
            ->set('default_amount', '350000')
            ->set('due_day', 45)
            ->call('save')
            ->assertHasErrors(['due_day']);
    }

    public function test_can_update_payment_category(): void
    {
        $category = PaymentCategory::query()->create([
            'code' => 'SPP',
            'name' => 'Nama Awal',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 350000,
            'due_day' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('edit', $category->id)
            ->set('name', 'SPP Bulanan Revisi')
            ->set('type', PaymentCategoryType::Annual->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('payment_categories', [
            'id' => $category->id,
            'name' => 'SPP Bulanan Revisi',
            'type' => PaymentCategoryType::Annual->value,
        ]);
    }

    public function test_can_soft_delete_payment_category(): void
    {
        $category = PaymentCategory::query()->create([
            'code' => 'DUMMY',
            'name' => 'Kategori Sementara',
            'type' => PaymentCategoryType::Once,
            'default_amount' => 50000,
            'due_day' => 5,
            'sort_order' => 99,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->call('confirmDelete', $category->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('payment_categories', ['id' => $category->id]);
    }

    public function test_search_filters_payment_category_by_name(): void
    {
        PaymentCategory::query()->create([
            'code' => 'SPP',
            'name' => 'Iuran Wajib Bulanan',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 350000,
            'due_day' => 10,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        PaymentCategory::query()->create([
            'code' => 'SERAGAM',
            'name' => 'Seragam Madrasah',
            'type' => PaymentCategoryType::Once,
            'default_amount' => 750000,
            'due_day' => 20,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(PaymentCategoryIndex::class)
            ->set('search', 'Seragam')
            ->assertSee('Seragam Madrasah')
            ->assertDontSee('Iuran Wajib Bulanan');
    }
}
