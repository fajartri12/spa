<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GatewayProvider;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\PaymentCategory;
use App\Models\PaymentIntent;
use App\Models\Student;
use App\Models\Unit;
use App\Services\BillingService;
use App\Services\GuardianService;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pemisahan portal siswa dan portal orang tua.
 *
 * Test ini menjaga agar kedua portal tetap terpisah: siswa hanya melihat
 * dirinya sendiri, orang tua melihat anak-anaknya, dan kwitansi tidak dapat
 * diunduh oleh pihak yang tidak berhak.
 */
class GuardianPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guardian_login_page_is_separate_from_student_login(): void
    {
        $this->get('/portal/orang-tua/login')
            ->assertOk()
            ->assertViewIs('public.guardian.login')
            ->assertSee('Portal Orang Tua');

        $this->get('/portal/login')
            ->assertOk()
            ->assertViewIs('public.student.login')
            ->assertSee('Portal Siswa', false);
    }

    public function test_student_login_page_links_to_guardian_portal(): void
    {
        $this->get('/portal/login')
            ->assertSee(route('public.guardian.login'), false);
    }

    public function test_guardian_can_authenticate_with_phone_and_password(): void
    {
        $guardian = $this->createGuardian();

        $response = $this->from('/portal/orang-tua/login')->post('/portal/orang-tua/authenticate', [
            'phone' => $guardian->phone,
            'password' => 'wali-password',
        ]);

        $response->assertRedirect('/portal/orang-tua');
        $response->assertSessionHas('public_guardian_id', $guardian->id);
    }

    public function test_phone_is_normalised_so_formatted_numbers_still_log_in(): void
    {
        $guardian = $this->createGuardian();

        $this->post('/portal/orang-tua/authenticate', [
            'phone' => '+62 812-3456-7890',
            'password' => 'wali-password',
        ])->assertRedirect('/portal/orang-tua');

        $this->assertSame('081234567890', $guardian->fresh()->phone);
    }

    public function test_guardian_without_password_cannot_authenticate(): void
    {
        $guardian = $this->createGuardian(withPassword: false);

        $response = $this->from('/portal/orang-tua/login')->post('/portal/orang-tua/authenticate', [
            'phone' => $guardian->phone,
            'password' => 'apa-saja',
        ]);

        $response->assertRedirect('/portal/orang-tua/login');
        $response->assertSessionMissing('public_guardian_id');
        $response->assertSessionHasErrors(['phone']);
    }

    public function test_inactive_guardian_cannot_authenticate(): void
    {
        $guardian = $this->createGuardian();
        $guardian->update(['is_active' => false]);

        $response = $this->from('/portal/orang-tua/login')->post('/portal/orang-tua/authenticate', [
            'phone' => $guardian->phone,
            'password' => 'wali-password',
        ]);

        $response->assertRedirect('/portal/orang-tua/login');
        $response->assertSessionMissing('public_guardian_id');
        $response->assertSessionHasErrors(['phone']);
    }

    public function test_guardian_portal_requires_session(): void
    {
        $this->get('/portal/orang-tua')->assertRedirect('/portal/orang-tua/login');
    }

    public function test_guardian_portal_renders_inside_public_layout(): void
    {
        $guardian = $this->createGuardian();
        $student = $this->createStudent('2026005', 'Anak Layout');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        $response = $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk();

        // Portal publik tidak boleh memakai shell admin (navbar internal sekolah).
        $response->assertSee('Portal resmi sekolah');
        $response->assertDontSee('Navigasi utama');
        $response->assertDontSee('Aplikasi Pengelolaan Pembayaran Tagihan');
    }

    public function test_guardian_portal_switches_between_children(): void
    {
        $guardian = $this->createGuardian();

        $first = $this->createStudent('2026006', 'Anak Pertama');
        $second = $this->createStudent('2026007', 'Anak Kedua');

        app(GuardianService::class)->syncStudents($guardian, [$first->id, $second->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua?anak=' . $second->id)
            ->assertOk()
            ->assertSee('Rincian untuk')
            ->assertSee('Anak Kedua');
    }

    public function test_guardian_portal_lists_all_children(): void
    {
        $guardian = $this->createGuardian();

        $first = $this->createStudent('2026001', 'Anak Pertama');
        $second = $this->createStudent('2026002', 'Anak Kedua');

        app(GuardianService::class)->syncStudents($guardian, [$first->id, $second->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Portal Orang Tua')
            ->assertSee('Anak Pertama')
            ->assertSee('Anak Kedua')
            ->assertSee('Total Sisa Tagihan');
    }

    public function test_guardian_portal_only_shows_own_children(): void
    {
        $guardian = $this->createGuardian();
        $own = $this->createStudent('2026003', 'Anak Sendiri');
        $other = $this->createStudent('2026004', 'Anak Orang Lain');

        app(GuardianService::class)->syncStudents($guardian, [$own->id]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Anak Sendiri')
            ->assertDontSee('Anak Orang Lain');
    }

    public function test_guardian_portal_handles_account_without_children(): void
    {
        $guardian = $this->createGuardian();

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Belum ada siswa tertaut');
    }

    public function test_guardian_session_is_invalidated_when_account_is_deactivated(): void
    {
        $guardian = $this->createGuardian();

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk();

        $guardian->update(['is_active' => false]);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertRedirect('/portal/orang-tua/login')
            ->assertSessionMissing('public_guardian_id');
    }

    public function test_guardian_can_logout(): void
    {
        $guardian = $this->createGuardian();

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->post('/portal/orang-tua/keluar')
            ->assertRedirect('/portal/orang-tua/login')
            ->assertSessionMissing('public_guardian_id');
    }

    public function test_student_portal_no_longer_claims_to_serve_parents(): void
    {
        $student = $this->createStudent('2026005', 'Siswa Portal');
        $student->forceFill(['portal_password' => 'rahasia-siswa'])->save();

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertSee('Portal Siswa')
            ->assertDontSee('Portal Siswa & Orang Tua', false)
            ->assertDontSee('Pilih Anak');
    }

    public function test_student_portal_shows_parent_contact(): void
    {
        $student = $this->createStudent('2026006', 'Siswa Kontak');
        $student->forceFill(['parent_name' => 'Bapak Wali', 'parent_phone' => '081999888777'])->save();

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertSee('Kontak Orang Tua / Wali')
            ->assertSee('Bapak Wali');
    }

    public function test_student_portal_shows_virtual_account_code_for_unpaid_bill(): void
    {
        $student = $this->createStudent('2026008', 'Siswa VA');
        $intent = $this->issueIntent($student);

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertSee('Kode Bayar')
            ->assertSee($intent->pay_code)
            ->assertSee($intent->reference);
    }

    public function test_guardian_portal_shows_virtual_account_code_for_child_bill(): void
    {
        $guardian = $this->createGuardian();
        $student = $this->createStudent('2026009', 'Anak VA');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        $intent = $this->issueIntent($student);

        $this->withSession(['public_guardian_id' => $guardian->id])
            ->get('/portal/orang-tua')
            ->assertOk()
            ->assertSee('Kode Bayar')
            ->assertSee($intent->pay_code);
    }

    public function test_portal_does_not_show_expired_pay_code(): void
    {
        $student = $this->createStudent('2026010', 'Siswa Kedaluwarsa');
        $intent = $this->issueIntent($student);

        $intent->forceFill(['expires_at' => now()->subHour()])->save();

        $this->withSession(['public_student_id' => $student->id])
            ->get('/portal/siswa')
            ->assertOk()
            ->assertDontSee($intent->pay_code);
    }

    /**
     * Terbitkan tagihan beserta VA/QRIS untuk siswa uji.
     */
    private function issueIntent(Student $student): PaymentIntent
    {
        Config::set('payment_gateway.providers.fake.enabled', true);

        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $category = PaymentCategory::query()->create([
            'code' => 'PORTAL-'.Str::upper(Str::random(6)),
            'name' => 'Tagihan Portal',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 300000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => '2026-11',
            'amount' => 300000,
        ]);

        return app(PaymentGatewayService::class)->createIntent($bill, GatewayProvider::Fake, 'va');
    }

    private function createGuardian(bool $withPassword = true): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Bapak Wali',
            'relation' => 'father',
            'phone' => '081234567890',
            'is_active' => true,
        ]);

        if ($withPassword) {
            $guardian->forceFill(['password' => 'wali-password', 'password_set_at' => now()])->save();
        }

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
}
