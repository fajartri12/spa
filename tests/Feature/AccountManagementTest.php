<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AccountType;
use App\Livewire\Master\AccountIndex;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AccountManagementTest extends TestCase
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

    public function test_guest_cannot_access_account_page(): void
    {
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_account_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSee('Akun Keuangan');
    }

    public function test_user_without_permission_cannot_view_account_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('accounts.index'))
            ->assertForbidden();
    }

    public function test_can_create_account(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('create')
            ->set('code', '1101')
            ->set('name', 'Kas Sekolah')
            ->set('type', AccountType::Cash->value)
            ->set('opening_balance', '2500000')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('accounts', [
            'code' => '1101',
            'name' => 'Kas Sekolah',
            'type' => AccountType::Cash->value,
        ]);
    }

    public function test_code_is_stored_uppercase_and_unique(): void
    {
        Account::query()->create([
            'code' => '1102',
            'name' => 'Bank Jatim',
            'type' => AccountType::Bank,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('create')
            ->set('code', '1102')
            ->set('name', 'Bank Lain')
            ->set('type', AccountType::Bank->value)
            ->set('opening_balance', '0')
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_currency_input_is_normalized_before_saving(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('create')
            ->set('code', '1103')
            ->set('name', 'Kas Kecil')
            ->set('type', AccountType::Cash->value)
            ->set('opening_balance', '1.500.000')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $account = Account::query()->where('code', '1103')->firstOrFail();
        $this->assertSame('1500000.00', (string) $account->opening_balance);
    }

    public function test_can_update_account(): void
    {
        $account = Account::query()->create([
            'code' => '1104',
            'name' => 'Nama Awal',
            'type' => AccountType::Cash,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('edit', $account->id)
            ->set('name', 'Nama Diperbarui')
            ->set('type', AccountType::Revenue->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('accounts', [
            'id' => $account->id,
            'name' => 'Nama Diperbarui',
            'type' => AccountType::Revenue->value,
        ]);
    }

    public function test_can_soft_delete_unused_account(): void
    {
        $account = Account::query()->create([
            'code' => '1105',
            'name' => 'Akun Tanpa Rekening',
            'type' => AccountType::Expense,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('confirmDelete', $account->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_account_used_by_bank_account_cannot_be_deleted(): void
    {
        $account = Account::query()->create([
            'code' => '1106',
            'name' => 'Akun Terpakai',
            'type' => AccountType::Bank,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        BankAccount::query()->create([
            'account_id' => $account->id,
            'bank_name' => 'Bank Jatim',
            'account_number' => '0001234567',
            'account_name' => 'KOMITE MAN 7 JOMBANG',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->call('confirmDelete', $account->id)
            ->call('delete')
            ->assertDispatched('toast', type: 'error');

        $this->assertDatabaseHas('accounts', ['id' => $account->id, 'deleted_at' => null]);
    }

    public function test_search_filters_account_by_code(): void
    {
        Account::query()->create([
            'code' => '4101',
            'name' => 'Pendapatan SPP',
            'type' => AccountType::Revenue,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Account::query()->create([
            'code' => '5101',
            'name' => 'Beban Listrik',
            'type' => AccountType::Expense,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AccountIndex::class)
            ->set('search', '4101')
            ->assertSee('Pendapatan SPP')
            ->assertDontSee('Beban Listrik');
    }
}
