<?php

namespace Tests\Feature;

use App\Livewire\Master\StudentIndex;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentPortalPasswordTest extends TestCase
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

    public function test_reset_portal_password_updates_timestamp_and_logs(): void
    {
        $student = Student::query()->create([
            'unit_id' => \App\Models\Unit::query()->firstOrFail()->id,
            'nis' => '2025901',
            'name' => 'Siswa Portal',
            'gender' => \App\Enums\Gender::Male,
            'status' => \App\Enums\StudentStatus::Active,
            'entry_year' => 2025,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StudentIndex::class)
            ->call('resetPortalPassword', $student->id)
            ->assertDispatched('toast');

        $this->assertNotNull($student->fresh()->portal_password_set_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'students.portal-password-reset']);
    }
}
