<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan kedua portal benar-benar terpisah.
 *
 * Sesi siswa tidak boleh membuka portal orang tua dan sebaliknya, karena
 * keduanya memakai penanda sesi yang berbeda.
 */
class PortalSeparationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_student_session_does_not_grant_guardian_portal(): void
    {
        $student = $this->createStudent('2026301', 'Siswa Sesi');

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/orang-tua')
            ->assertRedirect('/portal/orang-tua/login');
    }

    public function test_guardian_session_does_not_grant_student_portal(): void
    {
        $guardian = $this->createGuardian();

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/siswa')
            ->assertRedirect('/portal/login');
    }

    public function test_student_portal_shows_only_one_student_without_child_switcher(): void
    {
        $guardian = $this->createGuardian();
        $first = $this->createStudent('2026302', 'Anak Satu');
        $second = $this->createStudent('2026303', 'Anak Dua');

        app(GuardianService::class)->syncStudents($guardian, [$first->id, $second->id]);

        $this->withSession(['public_student_id' => $first->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertSee('Anak Satu')
            ->assertDontSee('Anak Dua')
            ->assertDontSee('Pilih Anak');
    }

    public function test_guardian_portal_shows_child_switcher(): void
    {
        $guardian = $this->createGuardian();
        $first = $this->createStudent('2026304', 'Pilih Satu');
        $second = $this->createStudent('2026305', 'Pilih Dua');

        app(GuardianService::class)->syncStudents($guardian, [$first->id, $second->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Pilih Anak')
            ->assertSee('Pilih Satu')
            ->assertSee('Pilih Dua');
    }

    public function test_guardian_portal_excludes_inactive_children(): void
    {
        $guardian = $this->createGuardian();
        $active = $this->createStudent('2026306', 'Anak Aktif');
        $graduated = $this->createStudent('2026307', 'Anak Lulus');

        app(GuardianService::class)->syncStudents($guardian, [$active->id, $graduated->id]);

        $graduated->update(['status' => StudentStatus::Graduated]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Anak Aktif')
            ->assertDontSee('Anak Lulus');
    }

    private function createGuardian(): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Wali Pemisah',
            'relation' => 'father',
            'phone' => '081205050505',
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
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
    }
}
