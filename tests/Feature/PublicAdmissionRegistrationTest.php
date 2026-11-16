<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicAdmissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function activePeriod(array $attributes = []): AdmissionPeriod
    {
        return AdmissionPeriod::query()->create(array_merge([
            'name' => 'PPDB 2026/2027',
            'slug' => 'ppdb-2026-2027',
            'starts_at' => now()->subDays(7)->toDateString(),
            'ends_at' => now()->addDays(7)->toDateString(),
            'status' => 'open',
        ], $attributes));
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Ahmad Fauzi',
            'email' => 'ahmad@example.com',
            'phone' => '081234567890',
            'gender' => 'male',
            'birth_place' => 'Jombang',
            'birth_date' => '2010-05-12',
            'address' => 'Jl. Pendidikan No. 1, Jombang',
            'parent_name' => 'Budi Santoso',
            'parent_phone' => '081298765432',
        ], $overrides);
    }

    public function test_guest_can_view_public_registration_form_when_period_is_active(): void
    {
        $period = $this->activePeriod();

        $this->get(route('ppdb.register'))
            ->assertOk()
            ->assertSee($period->name)
            ->assertSee('Nama Lengkap');
    }

    public function test_guest_is_redirected_to_closed_page_when_no_active_period(): void
    {
        $this->get(route('ppdb.register'))
            ->assertRedirect(route('ppdb.register.closed'));

        $this->get(route('ppdb.register.closed'))
            ->assertOk()
            ->assertSee('Pendaftaran Belum Dibuka');
    }

    public function test_period_outside_date_range_is_not_considered_active(): void
    {
        $this->activePeriod([
            'starts_at' => now()->subDays(30)->toDateString(),
            'ends_at' => now()->subDays(10)->toDateString(),
        ]);

        $this->get(route('ppdb.register'))->assertRedirect(route('ppdb.register.closed'));
        $this->post(route('ppdb.register.store'), $this->payload())->assertRedirect(route('ppdb.register.closed'));
        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_draft_period_is_not_considered_active(): void
    {
        $this->activePeriod(['status' => 'draft']);

        $this->get(route('ppdb.register'))->assertRedirect(route('ppdb.register.closed'));
    }

    public function test_guest_can_submit_registration_and_receives_registration_number(): void
    {
        $period = $this->activePeriod();

        $response = $this->post(route('ppdb.register.store'), $this->payload());

        $applicant = Applicant::query()->where('email', 'ahmad@example.com')->firstOrFail();

        $this->assertSame($period->id, $applicant->admission_period_id);
        $this->assertSame('registered', $applicant->status);
        $this->assertSame('Ahmad Fauzi', $applicant->name);
        $this->assertMatchesRegularExpression('/^PPDB-\d{4}-[A-Z0-9]{6}$/', $applicant->registration_number);

        $response->assertRedirect();
        $this->followingRedirects()
            ->get($response->headers->get('Location'))
            ->assertOk()
            ->assertSee($applicant->registration_number);
    }

    public function test_registration_numbers_are_unique_across_submissions(): void
    {
        $this->activePeriod();

        $this->post(route('ppdb.register.store'), $this->payload(['email' => 'satu@example.com']))->assertRedirect();
        $this->post(route('ppdb.register.store'), $this->payload(['email' => 'dua@example.com']))->assertRedirect();

        $numbers = Applicant::query()->pluck('registration_number');

        $this->assertCount(2, $numbers);
        $this->assertCount(2, $numbers->unique());
    }

    public function test_registration_validation_errors_are_returned(): void
    {
        $this->activePeriod();

        $this->from(route('ppdb.register'))
            ->post(route('ppdb.register.store'), [
                'name' => '',
                'gender' => 'other',
                'phone' => '',
                'address' => '',
                'parent_name' => '',
                'parent_phone' => '',
                'birth_date' => now()->addDay()->toDateString(),
                'email' => 'not-an-email',
            ])
            ->assertRedirect(route('ppdb.register'))
            ->assertSessionHasErrors(['name', 'gender', 'phone', 'address', 'parent_name', 'parent_phone', 'birth_date', 'email']);

        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_submission_fails_when_period_is_not_active(): void
    {
        $this->post(route('ppdb.register.store'), $this->payload())
            ->assertRedirect(route('ppdb.register.closed'));

        $this->assertDatabaseCount('applicants', 0);
    }

    public function test_success_page_requires_signed_url(): void
    {
        $period = $this->activePeriod();
        $applicant = Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-2026-ABCDEF',
            'name' => 'Siswa Uji',
            'status' => 'registered',
        ]);

        $this->get(route('ppdb.register.success', $applicant))->assertForbidden();

        $signed = URL::temporarySignedRoute('ppdb.register.success', now()->addHour(), ['applicant' => $applicant->id]);

        $this->get($signed)
            ->assertOk()
            ->assertSee('PPDB-2026-ABCDEF')
            ->assertSee('Siswa Uji');
    }

    public function test_admin_admissions_page_remains_protected_from_guests(): void
    {
        $this->get(route('admissions.index'))->assertRedirect(route('login'));
    }

    public function test_guest_can_access_ppdb_public_portal(): void
    {
        $period = $this->activePeriod();

        $this->get(route('ppdb.portal'))
            ->assertOk()
            ->assertSee($period->name)
            ->assertSee('Penerimaan Peserta Didik Baru')
            ->assertSee(route('ppdb.register'));

        $this->get('/PPDB')
            ->assertRedirect('/ppdb');
    }
}
