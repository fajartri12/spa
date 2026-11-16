<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CashTransactionType;
use App\Enums\JournalStatus;
use App\Livewire\Admissions\ApplicantShow;
use App\Models\Account;
use App\Models\AdmissionBill;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\AppSetting;
use App\Models\CashTransaction;
use App\Models\Journal;
use App\Models\User;
use App\Services\AdmissionPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionBillPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Applicant $applicant;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(\Database\Seeders\AccountSeeder::class);

        $period = AdmissionPeriod::query()->create([
            'name' => 'PPDB 2027',
            'slug' => 'ppdb-2027',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-03-31',
        ]);

        $this->applicant = Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-2027-00500',
            'name' => 'Calon Siswa PPDB',
            'status' => 'verified',
        ]);

        $this->superadmin = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function bill(float $amount = 150000, string $status = 'unpaid'): AdmissionBill
    {
        return AdmissionBill::query()->create([
            'applicant_id' => $this->applicant->id,
            'bill_number' => 'PPDB-INV-TEST-'.strtoupper(bin2hex(random_bytes(3))),
            'title' => 'Biaya Pendaftaran',
            'amount' => $amount,
            'due_date' => now()->addMonth(),
            'status' => $status,
        ]);
    }

    public function test_marking_bill_paid_records_cash_income(): void
    {
        $bill = $this->bill();

        $paid = app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        $this->assertSame('paid', $paid->status);
        $this->assertNotNull($paid->paid_at);

        $cash = CashTransaction::query()->where('admission_bill_id', $bill->id)->firstOrFail();

        $this->assertSame(CashTransactionType::Income, $cash->type);
        $this->assertSame('150000.00', (string) $cash->amount);
        $this->assertNotNull(Account::query()->find($cash->account_id));
    }

    public function test_marking_paid_posts_balanced_journal(): void
    {
        $bill = $this->bill(275000);

        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        $journal = Journal::query()->where('reference_number', $bill->bill_number)->firstOrFail();

        $this->assertSame(JournalStatus::Posted, $journal->status);

        $debit = (float) $journal->details()->sum('debit');
        $credit = (float) $journal->details()->sum('credit');

        $this->assertSame(275000.0, $debit);
        $this->assertSame($debit, $credit);
    }

    public function test_marking_paid_twice_is_rejected(): void
    {
        $bill = $this->bill();

        $service = app(AdmissionPaymentService::class);
        $service->markPaid($bill, $this->superadmin);

        $this->expectException(InvalidArgumentException::class);

        $service->markPaid($bill->refresh(), $this->superadmin);
    }

    public function test_cancelled_bill_cannot_be_paid(): void
    {
        $bill = $this->bill(status: 'cancelled');

        $this->expectException(InvalidArgumentException::class);

        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);
    }

    public function test_reverting_payment_creates_reversal_and_clears_paid_at(): void
    {
        $bill = $this->bill();

        $service = app(AdmissionPaymentService::class);
        $service->markPaid($bill, $this->superadmin);
        $reverted = $service->revertToUnpaid($bill->refresh(), $this->superadmin);

        $this->assertSame('unpaid', $reverted->status);
        $this->assertNull($reverted->paid_at);

        $types = CashTransaction::query()
            ->where('admission_bill_id', $bill->id)
            ->pluck('type')
            ->map(static fn (CashTransactionType $type): string => $type->value)
            ->all();

        sort($types);
        $this->assertSame(['expense', 'income'], $types);
    }

    public function test_cancel_requires_payment_reverted_first(): void
    {
        $bill = $this->bill();
        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        $this->expectException(InvalidArgumentException::class);

        app(AdmissionPaymentService::class)->cancel($bill->refresh(), $this->superadmin);
    }

    public function test_component_marks_bill_paid_and_shows_toast(): void
    {
        $bill = $this->bill();

        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('updateBillStatus', $bill->id, 'paid')
            ->assertDispatched('toast', type: 'success');

        $this->assertSame('paid', $bill->refresh()->status);
        $this->assertDatabaseHas('cash_transactions', [
            'admission_bill_id' => $bill->id,
            'type' => 'income',
        ]);
    }

    public function test_component_reports_error_when_bill_already_paid(): void
    {
        $bill = $this->bill();
        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('updateBillStatus', $bill->id, 'paid')
            ->assertDispatched('toast', type: 'error');
    }

    public function test_ppdb_income_is_counted_in_cash_flow_report(): void
    {
        $bill = $this->bill(125000);
        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        $this->actingAs($this->superadmin)
            ->get(route('reports.cash-flow'))
            ->assertOk()
            ->assertSee($bill->bill_number);
    }

    public function test_configured_ppdb_accounts_are_used(): void
    {
        $bank = Account::query()->where('code', '1103')->firstOrFail();
        $revenue = Account::query()->where('code', '4102')->firstOrFail();

        AppSetting::set('ppdb_cash_account_id', (string) $bank->id);
        AppSetting::set('ppdb_revenue_account_id', (string) $revenue->id);

        $bill = $this->bill(90000);
        app(AdmissionPaymentService::class)->markPaid($bill, $this->superadmin);

        $cash = CashTransaction::query()->where('admission_bill_id', $bill->id)->firstOrFail();
        $this->assertSame($bank->id, $cash->account_id);

        $journal = Journal::query()->where('reference_number', $bill->bill_number)->firstOrFail();
        $credit = $journal->details()->where('credit', '>', 0)->firstOrFail();
        $this->assertSame($revenue->id, $credit->account_id);
    }
}
