<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\Unit;
use App\Services\BillingService;
use App\Services\GuardianService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kartu visual matriks SPP dan cetak rekap tahunan (PDF).
 */
class SppRecapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guardian_portal_shows_spp_matrix_card(): void
    {
        $guardian = $this->makeGuardian();
        $student = $this->makeStudent('2026601', 'Anak Matriks');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Kartu SPP Tahunan')
            ->assertSee('Cetak Rekap SPP (PDF)');
    }

    public function test_student_portal_shows_spp_matrix_card_with_paid_month(): void
    {
        $student = $this->makeStudent('2026602', 'Siswa Matriks');
        $bill = $this->makeSppBill($student, '2026-01', 350000.0);

        app(PaymentService::class)->create(
            ['student_id' => $student->id, 'amount' => 350000.0],
            [['student_bill_id' => $bill->id, 'amount' => 350000.0]],
        );

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertSee('Kartu SPP Tahunan')
            ->assertSee('Januari');
    }

    public function test_guardian_can_download_spp_recap_pdf_for_own_child(): void
    {
        $guardian = $this->makeGuardian();
        $student = $this->makeStudent('2026603', 'Anak PDF');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);
        $this->makeSppBill($student, '2026-02', 350000.0);

        $response = $this->withSession(['public_guardian_id' => $guardian->id])
            ->get(route('public.spp-recap', $student));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_guardian_cannot_download_recap_of_other_student(): void
    {
        $guardian = $this->makeGuardian();
        $own = $this->makeStudent('2026604', 'Anak Sendiri');
        $other = $this->makeStudent('2026605', 'Anak Lain');
        app(GuardianService::class)->syncStudents($guardian, [$own->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get(route('public.spp-recap', $other))
            ->assertForbidden();
    }

    public function test_spp_recap_requires_authentication(): void
    {
        $student = $this->makeStudent('2026606', 'Siswa Tanpa Sesi');

        $this->get(route('public.spp-recap', $student))->assertForbidden();
    }

    private function makeSppBill(Student $student, string $period, float $amount): \App\Models\StudentBill
    {
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $category = PaymentCategory::query()->firstOrCreate(
            ['code' => 'SPP'],
            [
                'name' => 'Sumbangan Pembinaan Pendidikan',
                'type' => PaymentCategoryType::Monthly,
                'default_amount' => 350000,
                'due_day' => 10,
                'is_active' => true,
            ],
        );

        return app(BillingService::class)->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => $period,
            'amount' => $amount,
        ]);
    }

    private function makeGuardian(): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Bapak Wali',
            'relation' => 'father',
            'phone' => '081200000001',
            'is_active' => true,
        ]);
        $guardian->forceFill(['password' => 'wali-password', 'password_set_at' => now()])->save();

        return $guardian;
    }

    private function makeStudent(string $nis, string $name): Student
    {
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'TS'],
            ['name' => 'Unit SPP', 'level' => 'Test', 'sort_order' => 90, 'is_active' => true],
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
}