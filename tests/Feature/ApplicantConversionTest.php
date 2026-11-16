<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Livewire\Admissions\ApplicantShow;
use App\Models\AcademicYear;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentClassHistory;
use App\Models\Unit;
use App\Models\User;
use App\Services\ApplicantConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicantConversionTest extends TestCase
{
    use RefreshDatabase;

    private Applicant $applicant;

    private Unit $unit;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $period = AdmissionPeriod::query()->create([
            'name' => 'PPDB 2027',
            'slug' => 'ppdb-2027',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-03-31',
        ]);

        $this->applicant = Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-2027-00042',
            'name' => 'Fatimah Az Zahra',
            'email' => 'fatimah@example.test',
            'phone' => '081200000001',
            'gender' => 'female',
            'birth_place' => 'Jombang',
            'birth_date' => '2012-05-17',
            'address' => 'Jl. Pendidikan No. 1',
            'parent_name' => 'Ahmad',
            'parent_phone' => '081200000002',
            'status' => 'verified',
        ]);

        $this->unit = Unit::query()->orderBy('code')->firstOrFail();
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $this->class = SchoolClass::query()->create([
            'unit_id' => $this->unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas VII A',
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function admissionOperator(): User
    {
        return User::query()->where('email', 'ppdb@man7jombang.sch.id')->firstOrFail();
    }

    public function test_conversion_creates_student_with_applicant_data(): void
    {
        $student = app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => $this->class->id,
            'nis' => '2027001',
            'entry_year' => 2027,
        ], $this->superadmin());

        $this->assertSame('Fatimah Az Zahra', $student->name);
        $this->assertSame('2027001', $student->nis);
        $this->assertSame($this->unit->id, $student->unit_id);
        $this->assertSame($this->class->id, $student->school_class_id);
        $this->assertSame(Gender::Female, $student->gender);
        $this->assertSame(StudentStatus::Active, $student->status);
        $this->assertSame(2027, $student->entry_year);
        $this->assertSame('Ahmad', $student->parent_name);
        $this->assertSame('081200000001', $student->phone);
        $this->assertSame('2012-05-17', $student->birth_date?->toDateString());
    }

    public function test_conversion_records_initial_class_history(): void
    {
        $student = app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => $this->class->id,
            'nis' => '2027002',
            'entry_year' => 2027,
        ], $this->superadmin());

        $history = StudentClassHistory::query()->where('student_id', $student->id)->firstOrFail();

        $this->assertSame($this->class->id, $history->school_class_id);
        $this->assertSame($this->class->academic_year_id, $history->academic_year_id);
        $this->assertSame($this->unit->id, $history->unit_id);
    }

    public function test_conversion_links_applicant_to_student_and_marks_passed(): void
    {
        $student = app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027003',
            'entry_year' => 2027,
        ], $this->superadmin());

        $this->applicant->refresh();

        $this->assertSame($student->id, $this->applicant->student_id);
        $this->assertSame('passed', $this->applicant->status);
        $this->assertNotNull($this->applicant->converted_at);
        $this->assertTrue($this->applicant->isConverted());
    }

    public function test_conversion_without_class_places_student_unassigned(): void
    {
        $student = app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027004',
            'entry_year' => 2027,
        ], $this->superadmin());

        $this->assertNull($student->school_class_id);
        $this->assertDatabaseMissing('student_class_histories', ['student_id' => $student->id]);
    }

    public function test_conversion_rejects_duplicate_nis(): void
    {
        Student::query()->create([
            'unit_id' => $this->unit->id,
            'nis' => '2027005',
            'name' => 'Siswa Lama',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027005',
            'entry_year' => 2027,
        ], $this->superadmin());
    }

    public function test_conversion_rejects_already_converted_applicant(): void
    {
        app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027006',
            'entry_year' => 2027,
        ], $this->superadmin());

        $this->expectException(InvalidArgumentException::class);

        app(ApplicantConversionService::class)->convert($this->applicant->refresh(), [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027007',
            'entry_year' => 2027,
        ], $this->superadmin());
    }

    public function test_conversion_rejects_class_from_other_unit(): void
    {
        $otherUnit = Unit::query()->create(['code' => 'OTH', 'name' => 'Unit Lain']);
        $otherClass = SchoolClass::query()->create([
            'unit_id' => $otherUnit->id,
            'academic_year_id' => $this->class->academic_year_id,
            'name' => 'Kelas Lain',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => $otherClass->id,
            'nis' => '2027008',
            'entry_year' => 2027,
        ], $this->superadmin());
    }

    public function test_component_converts_applicant_from_modal(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('openConvert')
            ->assertSet('showConvertModal', true)
            ->set('convert_unit_id', $this->unit->id)
            ->set('convert_class_id', $this->class->id)
            ->set('convert_nis', '2027009')
            ->set('convert_entry_year', '2027')
            ->call('convert')
            ->assertHasNoErrors()
            ->assertSet('showConvertModal', false);

        $student = Student::query()->where('nis', '2027009')->firstOrFail();

        $this->assertSame('Fatimah Az Zahra', $student->name);
        $this->assertSame($student->id, $this->applicant->refresh()->student_id);
    }

    public function test_component_rejects_duplicate_nis_from_validation(): void
    {
        Student::query()->create([
            'unit_id' => $this->unit->id,
            'nis' => '2027010',
            'name' => 'Siswa Lama',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('openConvert')
            ->set('convert_unit_id', $this->unit->id)
            ->set('convert_nis', '2027010')
            ->call('convert')
            ->assertHasErrors(['convert_nis']);

        $this->assertNull($this->applicant->refresh()->student_id);
    }

    public function test_component_hides_convert_action_after_conversion(): void
    {
        app(ApplicantConversionService::class)->convert($this->applicant, [
            'unit_id' => $this->unit->id,
            'school_class_id' => null,
            'nis' => '2027011',
            'entry_year' => 2027,
        ], $this->superadmin());

        Livewire::actingAs($this->superadmin())
            ->test(ApplicantShow::class, ['applicant' => $this->applicant->refresh()])
            ->assertSee('Sudah Menjadi Siswa')
            ->call('openConvert')
            ->assertSet('showConvertModal', false)
            ->assertDispatched('toast', type: 'error');
    }

    public function test_admission_operator_with_update_permission_can_convert(): void
    {
        $operator = $this->admissionOperator();

        $this->assertTrue($operator->can('admissions.update'));

        Livewire::actingAs($operator)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('openConvert')
            ->set('convert_unit_id', $this->unit->id)
            ->set('convert_nis', '2027012')
            ->set('convert_entry_year', '2027')
            ->call('convert')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', ['nis' => '2027012']);
    }
}
