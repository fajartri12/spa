<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Livewire\Master\StudentIndex;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentManagementTest extends TestCase
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

    private function makeClass(Unit $unit): SchoolClass
    {
        return SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => AcademicYear::query()->firstOrFail()->id,
            'name' => 'X-IPA-1',
        ]);
    }

    public function test_guest_cannot_access_students_page(): void
    {
        $this->get(route('students.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_students_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('students.index'))
            ->assertOk()
            ->assertSee('Siswa');
    }

    public function test_user_without_permission_cannot_view_students_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('students.index'))
            ->assertForbidden();
    }

    public function test_can_create_student(): void
    {
        $unit = Unit::query()->firstOrFail();
        $class = $this->makeClass($unit);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('create')
            ->set('unit_id', $unit->id)
            ->set('school_class_id', $class->id)
            ->set('nis', '2025001')
            ->set('nisn', '0099887766')
            ->set('name', 'Muhammad Rizky')
            ->set('gender', Gender::Male->value)
            ->set('birth_place', 'Jombang')
            ->set('birth_date', '2010-05-12')
            ->set('religion', 'Islam')
            ->set('parent_name', 'Abdullah')
            ->set('parent_phone', '081234567890')
            ->set('parent_relation', 'Ayah')
            ->set('entry_year', 2025)
            ->set('status', StudentStatus::Active->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('students', [
            'nis' => '2025001',
            'name' => 'Muhammad Rizky',
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'status' => StudentStatus::Active->value,
        ]);
    }

    public function test_duplicate_nis_fails_validation(): void
    {
        $unit = Unit::query()->firstOrFail();

        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2025002',
            'name' => 'Siswa Lama',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('create')
            ->set('unit_id', $unit->id)
            ->set('nis', '2025002')
            ->set('name', 'Siswa Baru')
            ->set('gender', Gender::Male->value)
            ->set('entry_year', 2025)
            ->set('status', StudentStatus::Active->value)
            ->call('save')
            ->assertHasErrors(['nis']);
    }

    public function test_class_from_other_unit_is_rejected(): void
    {
        $units = Unit::query()->limit(2)->get();
        $this->assertCount(2, $units, 'Seeder harus menyediakan minimal dua unit.');

        $classInSecondUnit = $this->makeClass($units[1]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('create')
            ->set('unit_id', $units[0]->id)
            ->set('school_class_id', $classInSecondUnit->id)
            ->set('nis', '2025003')
            ->set('name', 'Siswa Salah Kelas')
            ->set('gender', Gender::Male->value)
            ->set('entry_year', 2025)
            ->set('status', StudentStatus::Active->value)
            ->call('save')
            ->assertHasErrors(['school_class_id']);
    }

    public function test_can_update_student(): void
    {
        $unit = Unit::query()->firstOrFail();

        $student = Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2025004',
            'name' => 'Nama Awal',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2025,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('edit', $student->id)
            ->set('name', 'Nama Setelah Diubah')
            ->set('status', StudentStatus::Graduated->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'name' => 'Nama Setelah Diubah',
            'status' => StudentStatus::Graduated->value,
        ]);
    }

    public function test_can_soft_delete_student(): void
    {
        $unit = Unit::query()->firstOrFail();

        $student = Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2025005',
            'name' => 'Siswa Dihapus',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'entry_year' => 2025,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('confirmDelete', $student->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }

    public function test_search_filters_students(): void
    {
        $unit = Unit::query()->firstOrFail();

        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2025010',
            'name' => 'Aisyah Nur',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'entry_year' => 2025,
        ]);

        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2025011',
            'name' => 'Zulkifli',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2025,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->set('search', 'Aisyah')
            ->assertSee('Aisyah Nur')
            ->assertDontSee('Zulkifli');
    }
}
