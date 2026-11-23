<?php

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStudentAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_can_see_login_page(): void
    {
        $response = $this->get('/portal/login');

        $response->assertStatus(200);
        $response->assertViewIs('public.student.login');
    }

    public function test_student_without_portal_password_cannot_authenticate(): void
    {
        $this->createStudent();

        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'default123',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_shared_password_no_longer_authenticates_student_with_portal_password(): void
    {
        $this->createStudentWithPortalPassword();

        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'komite2026',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_valid_nisn_and_per_student_password_redirects_to_portal(): void
    {
        $student = $this->createStudentWithPortalPassword();

        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'per-student-password',
        ]);

        $response->assertRedirect('/portal/siswa');
        $response->assertSessionHas('public_student_id', $student->id);
    }

    public function test_student_login_ignores_stale_intended_url(): void
    {
        $student = $this->createStudentWithPortalPassword();

        session(['url.intended' => '/masuk']);

        $response = $this->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'per-student-password',
        ]);

        $response->assertRedirect('/portal/siswa');
        $this->assertNull(session('url.intended'));
    }

    public function test_invalid_nisn_returns_error(): void
    {
        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => 'invalid-nisn',
            'password' => 'default123',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_wrong_password_returns_error(): void
    {
        $this->createStudentWithPortalPassword();

        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_rate_limiting_works(): void
    {
        $this->createStudentWithPortalPassword();

        // Coba 5 kali gagal
        for ($i = 0; $i < 5; $i++) {
            $this->from('/portal/login')->post('/portal/authenticate', [
                'nisn' => '1234567890123456789',
                'password' => 'wrong-password',
            ])->assertRedirect('/portal/login');
        }

        // Ke-6 kali harus gagal karena rate limit
        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(429);
    }

    public function test_inactive_or_graduated_student_cannot_authenticate(): void
    {
        $student = $this->createStudentWithPortalPassword();
        $student->update(['status' => 'graduated']);

        $response = $this->from('/portal/login')->post('/portal/authenticate', [
            'nisn' => '1234567890123456789',
            'password' => 'per-student-password',
        ]);

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHasErrors(['nisn']);
    }

    public function test_student_portal_renders_content_inside_public_layout(): void
    {
        $student = $this->createStudent();

        $response = $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa');

        $response->assertOk()
            ->assertSee('Portal Siswa')
            ->assertSee('Profil Siswa')
            ->assertSee($student->name)
            ->assertSee('Rincian Tagihan')
            ->assertSee('Saldo Tabungan')
            ->assertSee('KOMITE MAN 7 JOMBANG');
    }

    public function test_middleware_invalidates_session_if_student_is_no_longer_active(): void
    {
        $student = $this->createStudent();

        // Siswa aktif bisa mengakses portal siswa
        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertStatus(200);

        // Setelah siswa berstatus tidak aktif, akses ditolak dan session di-clear
        $student->update(['status' => 'graduated']);

        $response = $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa');

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
        $response->assertSessionHas('error');
    }

    public function test_middleware_invalidates_session_if_student_is_soft_deleted(): void
    {
        $student = $this->createStudent();

        $student->delete();

        $response = $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa');

        $response->assertRedirect('/portal/login');
        $response->assertSessionMissing('public_student_id');
    }

    private function createStudent(): Student
    {
        return Student::query()->create([
            'unit_id' => Unit::query()->firstOrCreate(
                ['code' => 'TEST'],
                ['name' => 'Test Unit', 'level' => 'Test', 'sort_order' => 99, 'is_active' => true]
            )->id,
            'school_class_id' => null,
            'nis' => '2024001',
            'nisn' => '1234567890123456789',
            'name' => 'Test Student',
            'gender' => 'male',
            'birth_place' => 'Jombang',
            'birth_date' => '2008-01-01',
            'religion' => 'Islam',
            'address' => 'Jl. Test No. 1',
            'phone' => '081234567890',
            'email' => 'test@student.com',
            'parent_name' => 'Parent Name',
            'parent_phone' => '081234567891',
            'parent_relation' => 'Ayah',
            'entry_year' => 2024,
            'status' => 'active',
        ]);
    }

    private function createStudentWithPortalPassword(): Student
    {
        $student = $this->createStudent();

        $student->forceFill([
            'portal_password' => bcrypt('per-student-password'),
            'portal_password_set_at' => now(),
        ])->save();

        return $student;
    }
}
