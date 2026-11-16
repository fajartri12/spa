<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function setupData(): array
    {
        $unit = Unit::query()->create(['code' => 'T', 'name' => 'T']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create(['unit_id' => $unit->id, 'academic_year_id' => $year->id, 'name' => 'X']);
        $category = PaymentCategory::query()->create([
            'code' => 'P', 'name' => 'P', 'type' => \App\Enums\PaymentCategoryType::Monthly,
            'default_amount' => 100000, 'due_day' => 10, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'unit_id' => $unit->id, 'school_class_id' => $class->id,
            'nis' => '1', 'nisn' => '1', 'name' => 'S', 'gender' => \App\Enums\Gender::Male, 'status' => \App\Enums\StudentStatus::Active, 'entry_year' => 2026,
        ]);
        $studentBill = app(BillingService::class)->create([
            'student_id' => $student->id, 'payment_category_id' => $category->id,
            'academic_year_id' => $year->id, 'period' => '2026-11', 'amount' => 100000,
        ]);

        return [$student, $studentBill];
    }

    public function test_receipt_can_be_printed_by_authorized_user(): void
    {
        [$student, $studentBill] = $this->setupData();
        $payment = app(PaymentService::class)->create(
            ['student_id' => $student->id, 'transaction_number' => 'P-1', 'amount' => 100000],
            [['student_bill_id' => $studentBill->id, 'amount' => 100000]],
        );
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)->get(route('payments.receipt', $payment))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_proof_can_be_uploaded_and_downloaded(): void
    {
        [$student, $studentBill] = $this->setupData();
        $payment = app(PaymentService::class)->create(
            ['student_id' => $student->id, 'transaction_number' => 'P-2', 'amount' => 50000],
            [['student_bill_id' => $studentBill->id, 'amount' => 50000]],
        );
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        Livewire::actingAs($user)->test(\App\Livewire\Payments\PaymentShow::class, ['payment' => $payment])
            ->set('proof', UploadedFile::fake()->create('bukti.pdf', 100, 'application/pdf'))
            ->call('uploadProof')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Bukti pembayaran berhasil diunggah.');

        $proof = $payment->proofs()->firstOrFail();
        $this->actingAs($user)->get(route('payment-proofs.download', $proof))
            ->assertStatus(200);

        Livewire::actingAs($user)->test(\App\Livewire\Payments\PaymentShow::class, ['payment' => $payment])
            ->call('deleteProof', $proof->id)
            ->assertDispatched('toast', type: 'success', message: 'Bukti pembayaran dihapus.');

        $this->assertDatabaseMissing('payment_proofs', ['id' => $proof->id]);
    }

    public function test_proof_download_requires_permission(): void
    {
        [$student, $studentBill] = $this->setupData();
        $payment = app(PaymentService::class)->create(
            ['student_id' => $student->id, 'transaction_number' => 'P-3', 'amount' => 50000],
            [['student_bill_id' => $studentBill->id, 'amount' => 50000]],
        );
        $proof = $payment->proofs()->create([
            'file_path' => 'payment-proofs/x.pdf',
            'original_name' => 'x.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'uploaded_by' => null,
        ]);

        $outsider = User::query()->create([
            'name' => 'Outsider', 'email' => 'outsider@example.test', 'password' => bcrypt('secret'),
            'status' => \App\Enums\UserStatus::Active,
        ]);

        $this->actingAs($outsider)->get(route('payment-proofs.download', $proof))
            ->assertStatus(403);
    }
}
