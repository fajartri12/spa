<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Otorisasi unduhan kwitansi dari portal publik.
 *
 * Kwitansi berisi nominal dan identitas siswa, jadi endpoint ini harus menolak
 * pengguna yang tidak terkait meskipun nomor transaksinya benar.
 */
class PortalReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_can_download_own_approved_receipt(): void
    {
        $student = $this->createStudent('2026101', 'Siswa Kwitansi');
        $payment = $this->createPayment($student);

        $response = $this->withSession(['public_student_id' => $student->id])
            ->get("/portal/kwitansi/{$payment->id}");

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_student_cannot_download_another_students_receipt(): void
    {
        $owner = $this->createStudent('2026102', 'Pemilik');
        $intruder = $this->createStudent('2026103', 'Penyusup');
        $payment = $this->createPayment($owner);

        $this->withSession(['public_student_id' => $intruder->id])
            ->get("/portal/kwitansi/{$payment->id}")
            ->assertForbidden();
    }

    public function test_guardian_can_download_receipt_of_linked_child(): void
    {
        $guardian = $this->createGuardian();
        $student = $this->createStudent('2026104', 'Anak Wali');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        $payment = $this->createPayment($student);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get("/portal/kwitansi/{$payment->id}")
            ->assertOk();
    }

    public function test_guardian_cannot_download_receipt_of_unlinked_student(): void
    {
        $guardian = $this->createGuardian();
        $ownChild = $this->createStudent('2026105', 'Anak Sendiri');
        $otherChild = $this->createStudent('2026106', 'Anak Lain');

        app(GuardianService::class)->syncStudents($guardian, [$ownChild->id]);

        $payment = $this->createPayment($otherChild);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get("/portal/kwitansi/{$payment->id}")
            ->assertForbidden();
    }

    public function test_receipt_is_forbidden_without_any_portal_session(): void
    {
        $student = $this->createStudent('2026107', 'Tanpa Sesi');
        $payment = $this->createPayment($student);

        $this->get("/portal/kwitansi/{$payment->id}")->assertForbidden();
    }

    public function test_receipt_requires_approved_payment(): void
    {
        $student = $this->createStudent('2026108', 'Belum Disetujui');
        $payment = $this->createPendingPayment($student);

        $this->withSession(['public_student_id' => $student->id])
            ->get("/portal/kwitansi/{$payment->id}")
            ->assertForbidden();
    }

    public function test_inactive_guardian_cannot_download_receipt(): void
    {
        $guardian = $this->createGuardian();
        $student = $this->createStudent('2026109', 'Anak Nonaktif');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        $payment = $this->createPayment($student);
        $guardian->update(['is_active' => false]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get("/portal/kwitansi/{$payment->id}")
            ->assertForbidden();
    }

    private function createGuardian(): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Wali Kwitansi',
            'relation' => 'mother',
            'phone' => '081200001111',
            'is_active' => true,
        ]);

        $guardian->forceFill(['password' => 'wali-password', 'password_set_at' => now()])->save();

        return $guardian;
    }

    private function createStudent(string $nis, string $name): Student
    {
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Test Unit', 'level' => 'Test', 'sort_order' => 99, 'is_active' => true],
        );

        return Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => $nis,
            'name' => $name,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
    }

    private function createPayment(Student $student): Payment
    {
        return $this->makePayment($student, PaymentStatus::Approved, 'TRX-'.$student->nis);
    }

    private function createPendingPayment(Student $student): Payment
    {
        return $this->makePayment($student, PaymentStatus::Pending, 'TRX-P-'.$student->nis);
    }

    private function makePayment(Student $student, PaymentStatus $status, string $number): Payment
    {
        $category = PaymentCategory::query()->firstOrCreate(
            ['code' => 'SPP'],
            ['name' => 'SPP', 'type' => 'monthly', 'default_amount' => 250000, 'is_active' => true],
        );

        return Payment::query()->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => AcademicYear::query()->value('id'),
            'transaction_number' => $number,
            'paid_at' => now(),
            'amount' => 250000,
            'status' => $status,
        ]);
    }
}
