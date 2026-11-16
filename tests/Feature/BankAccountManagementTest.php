<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Master\BankAccountIndex;
use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BankAccountManagementTest extends TestCase
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

    public function test_guest_cannot_access_bank_account_page(): void
    {
        $this->get(route('bank-accounts.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_bank_account_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('bank-accounts.index'))
            ->assertOk()
            ->assertSee('Rekening Bank');
    }

    public function test_user_without_permission_cannot_view_bank_account_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('bank-accounts.index'))
            ->assertForbidden();
    }

    public function test_can_create_bank_account(): void
    {
        $account = Account::query()->create([
            'code' => '1102',
            'name' => 'Bank Jatim',
            'type' => \App\Enums\AccountType::Bank,
            'opening_balance' => 0,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(BankAccountIndex::class)
            ->call('create')
            ->set('bank_name', 'Bank Jatim')
            ->set('account_number', '1234567890')
            ->set('account_name', 'KOMITE MAN 7 JOMBANG')
            ->set('account_id', $account->id)
            ->set('sort_order', 1)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('bank_accounts', [
            'bank_name' => 'Bank Jatim',
            'account_number' => '1234567890',
            'account_id' => $account->id,
        ]);
    }

    public function test_duplicate_number_per_bank_fails_validation(): void
    {
        BankAccount::query()->create([
            'bank_name' => 'Bank Jatim',
            'account_number' => '1234567890',
            'account_name' => 'Pemilik A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(BankAccountIndex::class)
            ->call('create')
            ->set('bank_name', 'Bank Jatim')
            ->set('account_number', '1234567890')
            ->set('account_name', 'Pemilik B')
            ->set('sort_order', 2)
            ->call('save')
            ->assertHasErrors(['account_number']);
    }

    public function test_can_update_bank_account(): void
    {
        $account = BankAccount::query()->create([
            'bank_name' => 'Bank Jatim',
            'account_number' => '1234567890',
            'account_name' => 'Pemilik A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(BankAccountIndex::class)
            ->call('edit', $account->id)
            ->set('account_name', 'Pemilik Diperbarui')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bank_accounts', [
            'id' => $account->id,
            'account_name' => 'Pemilik Diperbarui',
        ]);
    }

    public function test_can_soft_delete_bank_account(): void
    {
        $account = BankAccount::query()->create([
            'bank_name' => 'Bank BRI',
            'account_number' => '987654321',
            'account_name' => 'Pemilik BRI',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(BankAccountIndex::class)
            ->call('confirmDelete', $account->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('bank_accounts', ['id' => $account->id]);
    }

    public function test_search_filters_bank_account_by_number(): void
    {
        BankAccount::query()->create([
            'bank_name' => 'Bank Jatim',
            'account_number' => '111111',
            'account_name' => 'Pemilik A',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        BankAccount::query()->create([
            'bank_name' => 'Bank BNI',
            'account_number' => '222222',
            'account_name' => 'Pemilik B',
            'sort_order' => 2,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(BankAccountIndex::class)
            ->set('search', '1111')
            ->assertSee('111111')
            ->assertDontSee('222222');
    }
}
