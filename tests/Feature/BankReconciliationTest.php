<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\ReconciliationMatchStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\BankStatementImport;
use App\Models\BankStatementLine;
use App\Models\BankStatementProfile;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\BankReconciliationService;
use App\Services\BillingService;
use App\Services\PaymentService;
use Database\Seeders\BankStatementProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Import mutasi rekening koran dan pencocokan otomatis dengan pembayaran.
 */
class BankReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private BankStatementProfile $profile;

    private Student $student;

    private StudentBill $studentBill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->seed(BankStatementProfileSeeder::class);

        Config::set('payment_gateway.reconciliation.unique_amount_enabled', true);
        Config::set('payment_gateway.reconciliation.date_tolerance_days', 7);

        $this->profile = BankStatementProfile::query()->where('code', 'bsi')->firstOrFail();

        $unit = Unit::query()->create(['code' => 'RK', 'name' => 'Unit Rekonsiliasi']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-RK',
        ]);
        $category = PaymentCategory::query()->create([
            'code' => 'RK-TEST',
            'name' => 'Rekonsiliasi Test',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 300000,
            'due_day' => 10,
            'is_active' => true,
        ]);
        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'RK-001',
            'nisn' => 'RK-NISN-001',
            'name' => 'Rekonsiliasi Student',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
        $this->studentBill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => '2026-11',
            'amount' => 300000,
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function csv(array $rows): UploadedFile
    {
        $header = 'Tanggal,Keterangan,No. Referensi,Debit,Kredit,Saldo';
        $content = $header."\n".implode("\n", $rows)."\n";

        return UploadedFile::fake()->createWithContent('mutasi.csv', $content);
    }

    private function makePayment(float $amount, string $number): Payment
    {
        return app(PaymentService::class)->create(
            [
                'student_id' => $this->student->id,
                'transaction_number' => $number,
                'amount' => $amount,
                'paid_at' => now(),
            ],
            [['student_bill_id' => $this->studentBill->id, 'amount' => $amount]],
        );
    }

    public function test_import_parses_rows_and_normalises_indonesian_numbers(): void
    {
        $file = $this->csv([
            '01/11/2026,TRANSFER SPP AN. REKONSILIASI,TRX-1,0,"300.000,00","1.300.000,00"',
            '02/11/2026,BIAYA ADMIN,ADM-1,"6.500,00",0,"1.293.500,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $this->assertSame(2, $import->total_rows);
        $this->assertSame(0, $import->matched_rows);

        $credit = BankStatementLine::query()->where('reference', 'TRX-1')->firstOrFail();
        $this->assertSame('300000.00', (string) $credit->credit);
        $this->assertSame('2026-11-01', $credit->transacted_at->toDateString());

        $debit = BankStatementLine::query()->where('reference', 'ADM-1')->firstOrFail();
        $this->assertSame('6500.00', (string) $debit->debit);
    }

    public function test_duplicate_file_is_rejected(): void
    {
        $file = $this->csv(['01/11/2026,TRANSFER,TRX-1,0,"300.000,00","1.300.000,00"']);

        $service = app(BankReconciliationService::class);
        $service->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $this->expectException(InvalidArgumentException::class);

        $service->import($this->profile, $this->csv(['01/11/2026,TRANSFER,TRX-1,0,"300.000,00","1.300.000,00"']), [], $this->superadmin());
    }

    public function test_auto_match_by_reference(): void
    {
        $payment = $this->makePayment(300000, 'PAY-20261101-000001');

        $file = $this->csv([
            '01/11/2026,TRANSFER SPP PAY-20261101-000001,PAY-20261101-000001,0,"300.000,00","1.300.000,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, [], $this->superadmin());

        $this->assertSame(1, $import->matched_rows);

        $line = BankStatementLine::query()->firstOrFail();
        $this->assertSame(ReconciliationMatchStatus::Matched, $line->match_status);
        $this->assertSame('reference', $line->match_rule);
        $this->assertSame($payment->id, $line->payment_id);
    }

    public function test_auto_match_by_unique_amount(): void
    {
        $payment = $this->makePayment(300000, 'PAY-UNIQUE-1');

        $file = $this->csv([
            '01/11/2026,TRANSFER MASUK,TANPA-REF,0,"300.000,00","1.300.000,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, [], $this->superadmin());

        $this->assertSame(1, $import->matched_rows);

        $line = BankStatementLine::query()->firstOrFail();
        $this->assertSame($payment->id, $line->payment_id);
        $this->assertSame('unique_amount', $line->match_rule);
    }

    public function test_ambiguous_amount_is_left_unmatched(): void
    {
        $this->makePayment(300000, 'PAY-AMB-1');
        $this->makePayment(300000, 'PAY-AMB-2');

        $file = $this->csv([
            '01/11/2026,TRANSFER MASUK,TANPA-REF,0,"300.000,00","1.300.000,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, [], $this->superadmin());

        $this->assertSame(0, $import->matched_rows);
        $this->assertSame(ReconciliationMatchStatus::Unmatched, BankStatementLine::query()->firstOrFail()->match_status);
    }

    public function test_manual_match_and_unmatch(): void
    {
        $payment = $this->makePayment(300000, 'PAY-MANUAL-1');

        $file = $this->csv([
            '01/11/2026,TRANSFER MASUK,TANPA-REF,0,"300.000,00","1.300.000,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $line = BankStatementLine::query()->firstOrFail();
        $service = app(BankReconciliationService::class);

        $service->match($line, $payment, $this->superadmin());
        $this->assertSame(ReconciliationMatchStatus::Matched, $line->refresh()->match_status);
        $this->assertSame('manual', $line->match_rule);

        $service->unmatch($line, $this->superadmin());
        $this->assertSame(ReconciliationMatchStatus::Unmatched, $line->refresh()->match_status);
        $this->assertNull($line->payment_id);
    }

    public function test_payment_cannot_be_matched_twice(): void
    {
        $payment = $this->makePayment(300000, 'PAY-DOUBLE-1');

        $file = $this->csv([
            '01/11/2026,TRANSFER A,TANPA-REF,0,"300.000,00","1.300.000,00"',
            '02/11/2026,TRANSFER B,TANPA-REF-2,0,"300.000,00","1.600.000,00"',
        ]);

        $import = app(BankReconciliationService::class)
            ->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $lines = BankStatementLine::query()->orderBy('id')->get();
        $service = app(BankReconciliationService::class);

        $service->match($lines[0], $payment, $this->superadmin());

        $this->expectException(InvalidArgumentException::class);

        $service->match($lines[1], $payment, $this->superadmin());
    }

    public function test_debit_line_cannot_be_matched(): void
    {
        $payment = $this->makePayment(300000, 'PAY-DEBIT-1');

        $file = $this->csv([
            '01/11/2026,BIAYA ADMIN,ADM-1,"6.500,00",0,"1.293.500,00"',
        ]);

        app(BankReconciliationService::class)
            ->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $line = BankStatementLine::query()->firstOrFail();

        $this->expectException(InvalidArgumentException::class);

        app(BankReconciliationService::class)->match($line, $payment, $this->superadmin());
    }

    public function test_ignore_marks_line_ignored(): void
    {
        $file = $this->csv([
            '01/11/2026,BUNGA BANK,BUNGA-1,0,"1.250,00","1.301.250,00"',
        ]);

        app(BankReconciliationService::class)
            ->import($this->profile, $file, ['auto_match' => false], $this->superadmin());

        $line = BankStatementLine::query()->firstOrFail();
        app(BankReconciliationService::class)->ignore($line, $this->superadmin(), 'Bunga bank');

        $this->assertSame(ReconciliationMatchStatus::Ignored, $line->refresh()->match_status);
    }

    public function test_component_imports_file_and_updates_summary(): void
    {
        $this->makePayment(300000, 'PAY-COMP-1');

        $this->actingAs($this->superadmin());

        \Livewire\Livewire::test(\App\Livewire\Finance\BankReconciliationIndex::class)
            ->call('openImport')
            ->set('profileId', $this->profile->id)
            ->set('file', $this->csv(['01/11/2026,TRANSFER PAY-COMP-1,PAY-COMP-1,0,"300.000,00","1.300.000,00"']))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('showImportModal', false);

        $import = BankStatementImport::query()->latest('id')->firstOrFail();
        $this->assertSame(1, $import->matched_rows);
        $this->assertSame(1, $import->total_rows);
    }

    public function test_component_rejects_unsupported_file_type(): void
    {
        $this->actingAs($this->superadmin());

        \Livewire\Livewire::test(\App\Livewire\Finance\BankReconciliationIndex::class)
            ->call('openImport')
            ->set('profileId', $this->profile->id)
            ->set('file', UploadedFile::fake()->create('mutasi.pdf', 100, 'application/pdf'))
            ->call('import')
            ->assertHasErrors(['file']);
    }
}
