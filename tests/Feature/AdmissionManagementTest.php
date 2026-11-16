<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Livewire\Admissions\AdmissionIndex;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdmissionManagementTest extends TestCase
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

    public function test_superadmin_can_create_admission_period(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AdmissionIndex::class)
            ->call('createPeriod')
            ->set('name', 'PPDB 2027/2028')
            ->set('starts_at', '2027-01-01')
            ->set('ends_at', '2027-03-31')
            ->set('status', 'open')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('admission_periods', ['name' => 'PPDB 2027/2028', 'status' => 'open']);
    }

    public function test_applicant_registration_stores_relation_and_registration_number(): void
    {
        $period = AdmissionPeriod::query()->create([
            'name' => 'PPDB 2027', 'slug' => 'ppdb-2027',
            'starts_at' => '2027-01-01', 'ends_at' => '2027-03-31',
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(AdmissionIndex::class)
            ->call('createApplicant')
            ->set('admission_period_id', $period->id)
            ->set('applicantName', 'Calon Siswa')
            ->set('email', 'calon@example.com')
            ->set('gender', 'male')
            ->call('save')
            ->assertHasNoErrors();

        $applicant = Applicant::query()->where('name', 'Calon Siswa')->firstOrFail();

        $this->assertSame($period->id, $applicant->admission_period_id);
        $this->assertSame('registered', $applicant->status);
        $this->assertNotEmpty($applicant->registration_number);
    }

    public function test_applicant_requires_existing_period_and_name(): void
    {
        Livewire::actingAs($this->superadmin())
            ->test(AdmissionIndex::class)
            ->call('createApplicant')
            ->set('admission_period_id', 999)
            ->set('applicantName', '')
            ->call('save')
            ->assertHasErrors(['admission_period_id', 'applicantName']);
    }

    public function test_user_without_permission_cannot_access_admissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admissions.index'))->assertForbidden();
        Livewire::actingAs($user)->test(AdmissionIndex::class)->assertForbidden();
    }

    public function test_admission_operator_can_access_admissions_page(): void
    {
        $operator = User::query()->get()->first(
            fn (User $user): bool => $user->hasRole(RoleName::AdmissionOperator->value)
        );

        if ($operator === null) {
            $this->markTestSkipped('Operator PPDB seed user tidak tersedia.');
        }

        $this->actingAs($operator)->get(route('admissions.index'))->assertOk();
    }

    public function test_content_can_be_created_edited_published_and_deleted(): void
    {
        $component = Livewire::actingAs($this->superadmin())
            ->test(AdmissionIndex::class)
            ->call('createContent')
            ->set('contentTitle', 'Syarat Pendaftaran')
            ->set('contentBody', 'Membawa fotokopi rapor.')
            ->call('saveContent')
            ->assertHasNoErrors();

        $content = \App\Models\AdmissionContent::query()->firstOrFail();
        $this->assertSame('syarat-pendaftaran', $content->slug);
        $this->assertFalse($content->is_published);

        $component->call('editContent', $content->id)
            ->set('contentTitle', 'Syarat Pendaftaran Ulang')
            ->set('contentPublished', true)
            ->call('saveContent')
            ->assertHasNoErrors();

        $content->refresh();
        $this->assertSame('Syarat Pendaftaran Ulang', $content->title);
        $this->assertTrue($content->is_published);

        $component->call('toggleContentPublished', $content->id);
        $this->assertFalse($content->fresh()->is_published);

        $component->call('deleteContent', $content->id);
        $this->assertDatabaseMissing('admission_contents', ['id' => $content->id]);
    }

    public function test_content_slugs_are_unique(): void
    {
        $component = Livewire::actingAs($this->superadmin())->test(AdmissionIndex::class);

        foreach (['Info', 'Info'] as $title) {
            $component->call('createContent')
                ->set('contentTitle', $title)
                ->set('contentBody', 'Isi')
                ->call('saveContent')
                ->assertHasNoErrors();
        }

        $this->assertEqualsCanonicalizing(
            ['info', 'info-2'],
            \App\Models\AdmissionContent::query()->pluck('slug')->all(),
        );
    }
}
