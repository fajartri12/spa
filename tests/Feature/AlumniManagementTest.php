<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AlumniStatus;
use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Livewire\Master\AlumniIndex;
use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\AlumniService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class AlumniManagementTest extends TestCase
{
    use RefreshDatabase;

    private Unit $unit;

    private SchoolClass $class;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->unit = Unit::query()->firstOrFail();

        $this->class = SchoolClass::query()->create([
            'unit_id' => $this->unit->id,
            'academic_year_id' => AcademicYear::query()->firstOrFail()->id,
            'name' => 'XII-ALUMNI',
            'level' => 'XII',
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function makeStudent(string $nis, StudentStatus $status = StudentStatus::Graduated): Student
    {
        return Student::query()->create([
            'unit_id' => $this->unit->id,
            'school_class_id' => $this->class->id,
            'nis' => $nis,
            'name' => 'Siswa '.$nis,
            'gender' => Gender::Male,
            'entry_year' => 2023,
            'status' => $status,
        ]);
    }

    public function test_alumni_page_requires_permission(): void
    {
        $this->get(route('alumni.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('alumni.index'))
            ->assertForbidden();
    }

    public function test_authorized_user_can_view_alumni_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('alumni.index'))
            ->assertOk()
            ->assertSee('Alumni');
    }

    public function test_can_create_alumni_manually(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('create')
            ->set('unit_id', $this->unit->id)
            ->set('name', 'Alumni Manual')
            ->set('gender', Gender::Female->value)
            ->set('graduation_year', 2020)
            ->set('class_name', 'XII-IPA-1')
            ->set('status', AlumniStatus::Working->value)
            ->set('occupation', 'Karyawan')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('alumni', [
            'name' => 'Alumni Manual',
            'unit_id' => $this->unit->id,
            'graduation_year' => 2020,
            'status' => AlumniStatus::Working->value,
        ]);
    }

    public function test_manual_alumni_links_to_student_with_same_nis(): void
    {
        $student = $this->makeStudent('AL-001');

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('create')
            ->set('unit_id', $this->unit->id)
            ->set('nis', 'AL-001')
            ->set('name', 'Alumni Tertaut')
            ->set('gender', Gender::Male->value)
            ->set('graduation_year', 2026)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('alumni', [
            'nis' => 'AL-001',
            'student_id' => $student->id,
        ]);
    }

    public function test_manual_alumni_ignores_nis_from_another_unit(): void
    {
        $otherUnit = Unit::query()->whereKeyNot($this->unit->id)->firstOrFail();

        $otherStudent = Student::query()->create([
            'unit_id' => $otherUnit->id,
            'nis' => 'SAME-01',
            'name' => 'Siswa Unit Lain',
            'gender' => Gender::Male,
            'status' => StudentStatus::Graduated,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('create')
            ->set('unit_id', $this->unit->id)
            ->set('nis', 'SAME-01')
            ->set('name', 'Alumni Unit Ini')
            ->set('gender', Gender::Male->value)
            ->set('graduation_year', 2026)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('alumni', [
            'nis' => 'SAME-01',
            'unit_id' => $this->unit->id,
            'student_id' => null,
        ]);
        $this->assertDatabaseMissing('alumni', ['student_id' => $otherStudent->id]);
    }

    public function test_can_update_alumni(): void
    {
        $alumni = Alumni::query()->create([
            'unit_id' => $this->unit->id,
            'name' => 'Nama Lama',
            'gender' => Gender::Male,
            'graduation_year' => 2021,
            'status' => AlumniStatus::Unknown,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('edit', $alumni->id)
            ->set('name', 'Nama Baru')
            ->set('status', AlumniStatus::FurtherStudy->value)
            ->set('higher_education', 'UIN Malang')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama Baru', $alumni->fresh()->name);
        $this->assertSame(AlumniStatus::FurtherStudy, $alumni->fresh()->status);
        $this->assertSame('UIN Malang', $alumni->fresh()->higher_education);
    }

    public function test_can_soft_delete_alumni(): void
    {
        $alumni = Alumni::query()->create([
            'unit_id' => $this->unit->id,
            'name' => 'Akan Dihapus',
            'gender' => Gender::Male,
            'graduation_year' => 2021,
            'status' => AlumniStatus::Unknown,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('confirmDelete', $alumni->id)
            ->call('delete');

        $this->assertSoftDeleted('alumni', ['id' => $alumni->id]);
    }

    public function test_search_and_filters_apply_to_list(): void
    {
        Alumni::query()->create([
            'unit_id' => $this->unit->id, 'name' => 'Ahmad Fauzi', 'gender' => Gender::Male,
            'graduation_year' => 2020, 'status' => AlumniStatus::Working,
        ]);
        Alumni::query()->create([
            'unit_id' => $this->unit->id, 'name' => 'Budi Santoso', 'gender' => Gender::Male,
            'graduation_year' => 2021, 'status' => AlumniStatus::FurtherStudy,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->set('search', 'Ahmad')
            ->assertSee('Ahmad Fauzi')
            ->assertDontSee('Budi Santoso');

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->set('yearFilter', '2021')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Ahmad Fauzi');

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->set('statusFilter', AlumniStatus::Working->value)
            ->assertSee('Ahmad Fauzi')
            ->assertDontSee('Budi Santoso');
    }

    public function test_service_pulls_graduated_student(): void
    {
        $student = $this->makeStudent('AL-100');

        $alumni = app(AlumniService::class)->pullFromStudent($student, $this->superadmin(), 2026);

        $this->assertSame($student->id, $alumni->student_id);
        $this->assertSame($student->nis, $alumni->nis);
        $this->assertSame($student->name, $alumni->name);
        $this->assertSame($this->unit->id, $alumni->unit_id);
        $this->assertSame('XII-ALUMNI', $alumni->class_name);
        $this->assertSame(2026, $alumni->graduation_year);
        $this->assertDatabaseHas('activity_logs', ['event' => 'alumni.created']);
    }

    public function test_service_rejects_student_who_has_not_graduated(): void
    {
        $student = $this->makeStudent('AL-101', StudentStatus::Active);

        $this->expectException(InvalidArgumentException::class);

        app(AlumniService::class)->pullFromStudent($student, $this->superadmin());
    }

    public function test_service_rejects_duplicate_pull(): void
    {
        $student = $this->makeStudent('AL-102');
        $service = app(AlumniService::class);

        $service->pullFromStudent($student, $this->superadmin());

        $this->expectException(InvalidArgumentException::class);

        $service->pullFromStudent($student, $this->superadmin());
    }

    public function test_candidates_only_lists_graduated_students(): void
    {
        $graduated = $this->makeStudent('AL-200');
        $active = $this->makeStudent('AL-201', StudentStatus::Active);

        $candidates = app(AlumniService::class)->candidates();

        $this->assertTrue($candidates->contains('id', $graduated->id));
        $this->assertFalse($candidates->contains('id', $active->id));
    }

    public function test_candidates_excludes_students_already_pulled(): void
    {
        $student = $this->makeStudent('AL-300');
        $service = app(AlumniService::class);

        $service->pullFromStudent($student, $this->superadmin());

        $this->assertFalse($service->candidates()->contains('id', $student->id));
    }

    public function test_modal_pulls_selected_graduated_students(): void
    {
        $first = $this->makeStudent('AL-400');
        $second = $this->makeStudent('AL-401');

        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('openPull')
            ->set('pull_graduation_year', 2026)
            ->set('selectedStudents', [(string) $first->id, (string) $second->id])
            ->call('pullFromGraduates')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseCount('alumni', 2);
        $this->assertDatabaseHas('alumni', ['student_id' => $first->id, 'graduation_year' => 2026]);
        $this->assertDatabaseHas('alumni', ['student_id' => $second->id, 'graduation_year' => 2026]);
    }

    public function test_modal_requires_at_least_one_student(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AlumniIndex::class)
            ->call('openPull')
            ->set('selectedStudents', [])
            ->call('pullFromGraduates')
            ->assertHasErrors(['selectedStudents']);
    }

    public function test_export_streams_csv_with_filtered_rows(): void
    {
        Alumni::query()->create([
            'unit_id' => $this->unit->id, 'name' => 'Alumni Ekspor', 'gender' => Gender::Male,
            'graduation_year' => 2026, 'status' => AlumniStatus::Working, 'nis' => 'EXP-1',
        ]);

        $response = $this->actingAs($this->superadmin())
            ->get(route('alumni.export', ['tahun-lulus' => 2026]));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();
        $this->assertStringContainsString('EXP-1', $content);
        $this->assertStringContainsString('Alumni Ekspor', $content);
    }

    public function test_export_requires_permission(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('alumni.view');

        $this->actingAs($user)->get(route('alumni.export'))->assertForbidden();
    }

    public function test_alumni_permissions_are_registered(): void
    {
        foreach (['alumni.view', 'alumni.create', 'alumni.update', 'alumni.delete', 'alumni.export'] as $permission) {
            $this->assertDatabaseHas('permissions', ['name' => $permission]);
        }

        $this->assertTrue($this->superadmin()->can('alumni.delete'));
    }

    public function test_graduation_year_falls_back_to_entry_year_plus_three(): void
    {
        $student = Student::query()->create([
            'unit_id' => $this->unit->id,
            'nis' => 'AL-500',
            'name' => 'Siswa Tanpa Kelas',
            'gender' => Gender::Male,
            'entry_year' => 2020,
            'status' => StudentStatus::Graduated,
        ]);

        $alumni = app(AlumniService::class)->pullFromStudent($student, $this->superadmin());

        $this->assertSame(2023, $alumni->graduation_year);
    }
}
