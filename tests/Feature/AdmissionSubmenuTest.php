<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionSubmenuTest extends TestCase
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

    public function test_superadmin_can_access_all_ppdb_pages(): void
    {
        $this->actingAs($this->superadmin());

        $this->get(route('admissions.applicants'))->assertOk();
        $this->get(route('admissions.bills'))->assertOk();
        $this->get(route('admissions.periods'))->assertOk();
        $this->get(route('admissions.contents'))->assertOk();
        $this->get(route('admissions.settings', ['section' => 'umum']))->assertOk();
        $this->get(route('admissions.portal'))->assertOk();
    }

    public function test_user_without_permission_cannot_access_ppdb_pages(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->get(route('admissions.applicants'))->assertForbidden();
        $this->get(route('admissions.bills'))->assertForbidden();
        $this->get(route('admissions.periods'))->assertForbidden();
        $this->get(route('admissions.contents'))->assertForbidden();
        $this->get(route('admissions.settings', ['section' => 'umum']))->assertForbidden();
        $this->get(route('admissions.portal'))->assertForbidden();
    }

    public function test_setting_index_persists_settings(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\SettingIndex::class, ['section' => 'umum'])
            ->set('values.nama_lembaga', 'MAN 7 Jombang')
            ->set('values.kuota', '320')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('admission_settings', [
            'key' => 'ppdb.umum.nama_lembaga',
            'value' => 'MAN 7 Jombang',
        ]);
        $this->assertDatabaseHas('admission_settings', [
            'key' => 'ppdb.umum.kuota',
            'value' => '320',
        ]);
    }

    public function test_period_index_can_create_edit_and_delete(): void
    {
        $component = Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\PeriodIndex::class)
            ->call('create')
            ->set('name', 'PPDB 2028/2029')
            ->set('starts_at', '2028-01-01')
            ->set('ends_at', '2028-03-31')
            ->set('status', 'open')
            ->call('save')
            ->assertHasNoErrors();

        $period = AdmissionPeriod::query()->where('name', 'PPDB 2028/2029')->firstOrFail();

        $component->call('edit', $period->id)
            ->set('status', 'closed')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('closed', $period->fresh()->status);

        $component->call('delete', $period->id);
        $this->assertSoftDeleted('admission_periods', ['id' => $period->id]);
    }

    public function test_period_with_applicants_cannot_be_deleted(): void
    {
        $period = $this->makePeriod();

        Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-TEST-1',
            'name' => 'Pendaftar Uji',
            'status' => 'registered',
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\PeriodIndex::class)
            ->call('delete', $period->id);

        $this->assertDatabaseHas('admission_periods', ['id' => $period->id]);
    }

    public function test_applicant_index_can_register_applicant(): void
    {
        $period = $this->makePeriod();

        Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\ApplicantIndex::class)
            ->call('create')
            ->set('admission_period_id', $period->id)
            ->set('applicantName', 'Siti Aminah')
            ->set('phone', '08123456789')
            ->call('save')
            ->assertHasNoErrors();

        $applicant = Applicant::query()->where('name', 'Siti Aminah')->firstOrFail();

        $this->assertSame($period->id, $applicant->admission_period_id);
        $this->assertNotEmpty($applicant->registration_number);
    }

    public function test_admission_bill_index_can_issue_bill_and_change_status(): void
    {
        $period = $this->makePeriod();
        $applicant = Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-TEST-2',
            'name' => 'Budi Santoso',
            'status' => 'registered',
        ]);

        $component = Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\AdmissionBillIndex::class)
            ->call('create')
            ->set('applicant_id', $applicant->id)
            ->set('bill_title', 'Biaya Formulir')
            ->set('bill_amount', '150000')
            ->set('bill_due_date', '2027-02-01')
            ->call('save')
            ->assertHasNoErrors();

        $bill = \App\Models\AdmissionBill::query()->where('title', 'Biaya Formulir')->firstOrFail();

        $this->assertSame('unpaid', $bill->status);
        $this->assertSame('150000.00', $bill->amount);

        $component->call('updateStatus', $bill->id, 'paid');
        $this->assertSame('paid', $bill->fresh()->status);

        $component->call('updateStatus', $bill->id, 'unknown');
        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_content_index_supports_crud_and_publish_toggle(): void
    {
        $component = Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\ContentIndex::class)
            ->call('create')
            ->set('contentTitle', 'Pengumuman Kelulusan')
            ->set('contentBody', 'Hasil seleksi diumumkan 1 April.')
            ->set('contentPublished', true)
            ->call('save')
            ->assertHasNoErrors();

        $content = \App\Models\AdmissionContent::query()->where('title', 'Pengumuman Kelulusan')->firstOrFail();
        $this->assertSame('pengumuman-kelulusan', $content->slug);
        $this->assertTrue($content->is_published);

        $component->call('togglePublished', $content->id);
        $this->assertFalse($content->fresh()->is_published);

        $component->call('edit', $content->id)
            ->set('contentTitle', 'Pengumuman Kelulusan Ulang')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('Pengumuman Kelulusan Ulang', $content->fresh()->title);

        $component->call('delete', $content->id);
        $this->assertDatabaseMissing('admission_contents', ['id' => $content->id]);
    }

    public function test_content_slug_stays_unique(): void
    {
        $component = Livewire::actingAs($this->superadmin())->test(\App\Livewire\Admissions\ContentIndex::class);

        foreach (['Info', 'Info'] as $title) {
            $component->call('create')
                ->set('contentTitle', $title)
                ->set('contentBody', 'Isi pengumuman')
                ->call('save')
                ->assertHasNoErrors();
        }

        $this->assertEqualsCanonicalizing(
            ['info', 'info-2'],
            \App\Models\AdmissionContent::query()->pluck('slug')->all(),
        );
    }

    public function test_portal_access_page_shows_registration_url(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(\App\Livewire\Admissions\PortalAccess::class)
            ->assertSee(route('ppdb.register'));
    }

    private function makePeriod(): AdmissionPeriod
    {
        return AdmissionPeriod::query()->create([
            'name' => 'PPDB Uji',
            'slug' => 'ppdb-uji',
            'starts_at' => '2027-01-01',
            'ends_at' => '2027-03-31',
            'status' => 'open',
        ]);
    }
}