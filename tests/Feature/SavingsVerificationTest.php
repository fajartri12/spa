<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Livewire\Savings\SavingsIndex;
use App\Livewire\Savings\SavingsVerification;
use App\Models\AcademicYear;
use App\Models\Account;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSavingsAccount;
use App\Models\StudentSavingsTransaction;
use App\Models\Unit;
use App\Models\User;
use App\Services\SavingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsVerificationTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private StudentSavingsAccount $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $unit = Unit::query()->create(['code' => 'SVF', 'name' => 'Unit Verifikasi']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas Verifikasi',
        ]);

        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'SVF-1',
            'name' => 'Siswa Verifikasi',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);

        $this->account = StudentSavingsAccount::query()->create([
            'student_id' => $this->student->id,
            'balance' => 0,
        ]);
    }

    private function treasurer(): User
    {
        return User::query()->where('email', 'bendahara@email.com')->firstOrFail();
    }

    private function paymentOfficer(): User
    {
        return User::query()->where('email', 'petugas@email.com')->firstOrFail();
    }

    private function deposit(float $amount): StudentSavingsTransaction
    {
        return app(SavingsService::class)->deposit($this->student, $amount, 'Setoran awal', $this->treasurer());
    }

    public function test_deposit_creates_cash_income_transaction(): void
    {
        $transaction = $this->deposit(200000);

        $this->assertSame('posted', $transaction->status);
        $this->assertEquals(200000, $this->account->refresh()->balance);

        $cash = CashTransaction::query()->where('student_savings_transaction_id', $transaction->id)->firstOrFail();

        $this->assertSame(CashTransactionType::Income, $cash->type);
        $this->assertSame('200000.00', (string) $cash->amount);
        $this->assertNotNull(Account::query()->find($cash->account_id));
    }

    public function test_withdrawal_request_does_not_change_balance(): void
    {
        $this->deposit(200000);

        $withdrawal = app(SavingsService::class)
            ->requestWithdrawal($this->student, 50000, 'Tarik tunai', $this->treasurer());

        $this->assertSame('pending', $withdrawal->status);
        $this->assertEquals(200000, $this->account->refresh()->balance);
        $this->assertDatabaseMissing('cash_transactions', ['student_savings_transaction_id' => $withdrawal->id]);
    }

    public function test_withdrawal_request_rejects_amount_above_available_balance(): void
    {
        $this->deposit(100000);

        $this->expectException(InvalidArgumentException::class);

        app(SavingsService::class)->requestWithdrawal($this->student, 150000, null, $this->treasurer());
    }

    public function test_pending_withdrawals_reduce_available_balance(): void
    {
        $this->deposit(100000);

        $service = app(SavingsService::class);
        $service->requestWithdrawal($this->student, 60000, null, $this->treasurer());

        $this->assertSame(40000.0, $service->availableBalance($this->account->refresh()));

        $this->expectException(InvalidArgumentException::class);
        $service->requestWithdrawal($this->student, 50000, null, $this->treasurer());
    }

    public function test_approval_deducts_balance_and_records_cash_expense(): void
    {
        $this->deposit(200000);
        $withdrawal = app(SavingsService::class)->requestWithdrawal($this->student, 75000, 'Tarik', $this->treasurer());

        $approved = app(SavingsService::class)->approve($withdrawal, $this->treasurer());

        $this->assertSame('posted', $approved->status);
        $this->assertEquals(125000, $this->account->refresh()->balance);
        $this->assertSame('125000.00', (string) $approved->balance_after);

        $cash = CashTransaction::query()->where('student_savings_transaction_id', $approved->id)->firstOrFail();

        $this->assertSame(CashTransactionType::Expense, $cash->type);
        $this->assertSame('75000.00', (string) $cash->amount);
        $this->assertNotNull($approved->verified_at);
    }

    public function test_rejection_keeps_balance_and_stores_reason(): void
    {
        $this->deposit(200000);
        $withdrawal = app(SavingsService::class)->requestWithdrawal($this->student, 75000, null, $this->treasurer());

        $rejected = app(SavingsService::class)->reject($withdrawal, 'Dokumen belum lengkap', $this->treasurer());

        $this->assertSame('rejected', $rejected->status);
        $this->assertSame('Dokumen belum lengkap', $rejected->rejection_reason);
        $this->assertEquals(200000, $this->account->refresh()->balance);
        $this->assertSame(200000.0, app(SavingsService::class)->availableBalance($this->account->refresh()));
        $this->assertDatabaseMissing('cash_transactions', ['student_savings_transaction_id' => $rejected->id]);
    }

    public function test_verification_component_approves_pending_withdrawal(): void
    {
        $this->deposit(150000);
        $withdrawal = app(SavingsService::class)->requestWithdrawal($this->student, 50000, null, $this->treasurer());

        Livewire::actingAs($this->treasurer())
            ->test(SavingsVerification::class)
            ->assertSee($this->student->name)
            ->call('approve', $withdrawal->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertEquals(100000, $this->account->refresh()->balance);
        $this->assertSame('posted', $withdrawal->refresh()->status);
    }

    public function test_verification_component_rejects_with_reason(): void
    {
        $this->deposit(150000);
        $withdrawal = app(SavingsService::class)->requestWithdrawal($this->student, 50000, null, $this->treasurer());

        Livewire::actingAs($this->treasurer())
            ->test(SavingsVerification::class)
            ->call('confirmReject', $withdrawal->id)
            ->set('rejectionReason', 'Saldo dibutuhkan untuk kegiatan')
            ->call('reject')
            ->assertHasNoErrors()
            ->assertSet('showRejectModal', false);

        $this->assertSame('rejected', $withdrawal->refresh()->status);
        $this->assertEquals(150000, $this->account->refresh()->balance);
    }

    public function test_verification_requires_reason_to_reject(): void
    {
        $this->deposit(150000);
        $withdrawal = app(SavingsService::class)->requestWithdrawal($this->student, 50000, null, $this->treasurer());

        Livewire::actingAs($this->treasurer())
            ->test(SavingsVerification::class)
            ->call('confirmReject', $withdrawal->id)
            ->call('reject')
            ->assertHasErrors(['rejectionReason']);
    }

    public function test_payment_officer_cannot_open_verification_page(): void
    {
        $this->assertFalse($this->paymentOfficer()->can('savings.verify'));

        $this->actingAs($this->paymentOfficer())
            ->get(route('savings.verification'))
            ->assertForbidden();
    }

    public function test_withdrawal_from_index_creates_pending_request(): void
    {
        $this->deposit(150000);

        Livewire::actingAs($this->treasurer())
            ->test(SavingsIndex::class)
            ->call('openTransaction', $this->student->id)
            ->set('type', 'withdrawal')
            ->set('amount', 40000)
            ->call('saveTransaction')
            ->assertHasNoErrors()
            ->assertSet('showTransactionModal', false)
            ->assertDispatched('toast', type: 'success', message: 'Pengajuan penarikan tersimpan dan menunggu verifikasi.');

        $this->assertEquals(150000, $this->account->refresh()->balance);
        $this->assertDatabaseHas('student_savings_transactions', [
            'student_savings_account_id' => $this->account->id,
            'type' => 'withdrawal',
            'status' => 'pending',
            'amount' => 40000,
        ]);
    }

    public function test_index_caps_withdrawal_at_available_balance(): void
    {
        $this->deposit(100000);
        app(SavingsService::class)->requestWithdrawal($this->student, 80000, null, $this->treasurer());

        Livewire::actingAs($this->treasurer())
            ->test(SavingsIndex::class)
            ->call('openTransaction', $this->student->id)
            ->set('type', 'withdrawal')
            ->set('amount', 50000)
            ->call('saveTransaction')
            ->assertHasErrors(['amount']);
    }

    public function test_treasurer_role_has_verification_permission(): void
    {
        $user = $this->treasurer();

        $this->assertTrue($user->hasRole(RoleName::Treasurer->value));
        $this->assertTrue($user->can('savings.verify'));
    }

    public function test_export_streams_savings_rows_with_pending_amount(): void
    {
        $this->deposit(200000);
        app(SavingsService::class)->requestWithdrawal($this->student, 60000, null, $this->treasurer());

        Livewire::actingAs($this->treasurer());

        $response = Livewire::test(SavingsIndex::class)->instance()->export();

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('saldo_tersedia', $csv);
        $this->assertStringContainsString($this->student->name, $csv);
        $this->assertStringContainsString('200000', $csv);
        $this->assertStringContainsString('60000', $csv);
        $this->assertStringContainsString('140000', $csv);
    }

    public function test_export_requires_export_permission(): void
    {
        $officer = $this->paymentOfficer();
        $officer->givePermissionTo('savings.view');

        $component = Livewire::actingAs($officer)->test(SavingsIndex::class);

        $this->assertFalse($officer->can('savings.export'));

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $component->instance()->export();
    }

    public function test_configured_cash_account_is_used_for_savings_mutation(): void
    {
        $bank = Account::query()->where('code', '1102')->firstOrFail();
        AppSetting::set('savings_cash_account_id', (string) $bank->id);

        $transaction = $this->deposit(100000);

        $cash = CashTransaction::query()->where('student_savings_transaction_id', $transaction->id)->firstOrFail();

        $this->assertSame($bank->id, $cash->account_id);
    }
}
