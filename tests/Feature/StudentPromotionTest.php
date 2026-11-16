<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Livewire\Students\StudentPromotion;
use App\Models\AcademicYear;
use App\Models\PaymentCategory;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\ClearanceService;
use App\Services\PromotionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPromotionTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private AcademicYear $currentYear;

    private AcademicYear $nextYear;

    private SchoolClass $sourceClass;

    private SchoolClass $targetClass;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->unit = Unit::query()->firstOrFail();
        $this->currentYear = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $this->nextYear = AcademicYear::query()->create([
            'name' => '2099/2100',
            'start_date' => '2099-07-01',
            'end_date' => '2100-06-30',
            'is_active' => false,
        ]);

        $this->sourceClass = SchoolClass::query()->create([
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->currentYear->id,
            'name' => 'X-PROMO',
            'level' => '10',
        ]);

        $this->targetClass = SchoolClass::query()->create([
            'unit_id' => $this->unit->id,
            'academic_year_id' => $this->nextYear->id,
            'name' => 'XI-PROMO',
            'level' => '11',
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    /**
     * @return array<int, Student>
     */
    private function students(int $count = 2): array
    {
        $students = [];

        foreach (range(1, $count) as $index) {
            $students[] = Student::query()->create([
                'unit_id' => $this->unit->id,
                'school_class_id' => $this->sourceClass->id,
                'nis' => 'PROMO-'.$index,
                'name' => 'Siswa Promosi '.$index,
                'gender' => Gender::Male,
                'status' => StudentStatus::Active,
            ]);
        }

        return $students;
    }

    public function test_promotion_moves_students_and_records_history(): void
    {
        $students = $this->students();

        $result = app(PromotionService::class)->promote(
            array_map(fn (Student $student) => $student->id, $students),
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Active,
            'Kenaikan tahun ajaran',
            $this->superadmin(),
        );

        $this->assertSame(2, $result['promoted']);
        $this->assertSame(0, $result['skipped']);

        foreach ($students as $student) {
            $this->assertSame($this->targetClass->id, $student->fresh()->school_class_id);
        }

        $this->assertDatabaseCount('student_class_histories', 2);
        $this->assertDatabaseHas('student_class_histories', [
            'student_id' => $students[0]->id,
            'school_class_id' => $this->targetClass->id,
            'academic_year_id' => $this->nextYear->id,
            'class_name' => 'XI-PROMO',
        ]);
        $this->assertDatabaseHas('activity_logs', ['event' => 'students.promoted']);
    }

    public function test_promotion_can_mark_final_year_students_as_graduated(): void
    {
        $students = $this->students(1);

        app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Graduated,
            'Lulus',
            $this->superadmin(),
        );

        $this->assertSame(StudentStatus::Graduated, $students[0]->fresh()->status);
    }

    public function test_promotion_skips_students_already_in_target_class(): void
    {
        $students = $this->students(1);

        $service = app(PromotionService::class);
        $service->promote([$students[0]->id], $this->targetClass->id, $this->nextYear->id, StudentStatus::Active, null, $this->superadmin());
        $second = $service->promote([$students[0]->id], $this->targetClass->id, $this->nextYear->id, StudentStatus::Active, null, $this->superadmin());

        $this->assertSame(0, $second['promoted']);
        $this->assertSame(1, $second['skipped']);
        $this->assertDatabaseCount('student_class_histories', 1);
    }

    public function test_promotion_rejects_class_from_another_unit(): void
    {
        $students = $this->students(1);
        $otherUnit = Unit::query()->whereKeyNot($this->unit->id)->firstOrFail();

        $foreignClass = SchoolClass::query()->create([
            'unit_id' => $otherUnit->id,
            'academic_year_id' => $this->nextYear->id,
            'name' => 'XI-LAIN',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PromotionService::class)->promote(
            [$students[0]->id],
            $foreignClass->id,
            $this->nextYear->id,
            StudentStatus::Active,
            null,
            $this->superadmin(),
        );
    }

    public function test_promotion_rejects_class_outside_selected_academic_year(): void
    {
        $students = $this->students(1);

        $this->expectException(InvalidArgumentException::class);

        // Kelas tujuan berada pada tahun ajaran berikutnya, tetapi tahun yang
        // dikirim adalah tahun berjalan.
        app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->currentYear->id,
            StudentStatus::Active,
            null,
            $this->superadmin(),
        );
    }

    public function test_wizard_page_requires_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('students.promotion'))->assertForbidden();
        Livewire::actingAs($user)->test(StudentPromotion::class)->assertForbidden();
    }

    public function test_wizard_moves_selected_students(): void
    {
        $students = $this->students();

        Livewire::actingAs($this->superadmin())
            ->test(StudentPromotion::class)
            ->set('target_unit_id', $this->unit->id)
            ->set('target_year_id', $this->nextYear->id)
            ->set('target_class_id', $this->targetClass->id)
            ->set('selected', array_map(fn (Student $student) => (string) $student->id, $students))
            ->set('target_status', StudentStatus::Active->value)
            ->set('confirmed', true)
            ->call('promote')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('student_class_histories', 2);

        foreach ($students as $student) {
            $this->assertSame($this->targetClass->id, $student->fresh()->school_class_id);
        }
    }

    public function test_wizard_requires_at_least_one_student(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(StudentPromotion::class)
            ->set('target_unit_id', $this->unit->id)
            ->set('target_year_id', $this->nextYear->id)
            ->set('target_class_id', $this->targetClass->id)
            ->set('selected', [])
            ->set('confirmed', true)
            ->call('promote')
            ->assertHasErrors(['selected']);
    }

    public function test_graduation_is_blocked_when_student_still_has_outstanding_bill(): void
    {
        $students = $this->students(1);
        $this->createOutstandingBill($students[0]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('masih ada tanggungan');

        app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Graduated,
            'Lulus',
            $this->superadmin(),
        );
    }

    public function test_graduation_is_blocked_until_clearance_is_checked(): void
    {
        $students = $this->students(1);

        // Tanpa tagihan pun status belum diketahui sebelum diperiksa; pemeriksaan
        // otomatis dijalankan oleh PromotionService sehingga kelulusan lolos.
        app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Graduated,
            'Lulus',
            $this->superadmin(),
        );

        $this->assertSame(StudentStatus::Graduated, $students[0]->fresh()->status);
        $this->assertDatabaseHas('graduation_clearances', [
            'student_id' => $students[0]->id,
            'status' => 'cleared',
        ]);
    }

    public function test_graduation_allowed_after_clearance_override(): void
    {
        $students = $this->students(1);
        $this->createOutstandingBill($students[0]);

        app(ClearanceService::class)->override($students[0], 'Kebijakan madrasah', $this->superadmin());

        app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Graduated,
            'Lulus',
            $this->superadmin(),
        );

        $this->assertSame(StudentStatus::Graduated, $students[0]->fresh()->status);
    }

    public function test_promotion_to_next_level_does_not_require_clearance(): void
    {
        $students = $this->students(1);
        $this->createOutstandingBill($students[0]);

        $result = app(PromotionService::class)->promote(
            [$students[0]->id],
            $this->targetClass->id,
            $this->nextYear->id,
            StudentStatus::Active,
            'Naik kelas',
            $this->superadmin(),
        );

        $this->assertSame(1, $result['promoted']);
        $this->assertDatabaseMissing('graduation_clearances', ['student_id' => $students[0]->id]);
    }

    public function test_wizard_lists_students_blocked_by_outstanding_bills(): void
    {
        $students = $this->students(1);
        $this->createOutstandingBill($students[0]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentPromotion::class)
            ->set('target_unit_id', $this->unit->id)
            ->set('target_year_id', $this->nextYear->id)
            ->set('target_class_id', $this->targetClass->id)
            ->set('selected', [(string) $students[0]->id])
            ->set('target_status', StudentStatus::Graduated->value)
            ->assertSee('Belum bebas tanggungan')
            ->assertSee($students[0]->name);
    }

    private function createOutstandingBill(Student $student): void
    {
        $category = PaymentCategory::query()->create([
            'code' => 'PROMO-'.Str::upper(Str::random(6)),
            'name' => 'Tagihan Promosi',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 200000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        app(BillingService::class)->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $this->currentYear->id,
            'period' => '2026-11',
            'amount' => 200000,
        ]);
    }
}
