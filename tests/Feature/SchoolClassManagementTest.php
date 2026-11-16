<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Master\SchoolClassIndex;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SchoolClassManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_school_classes_page(): void
    {
        $this->get(route('school-classes.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_school_classes(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('school-classes.index'))
            ->assertOk()
            ->assertSee('Kelas');
    }

    public function test_user_without_permission_cannot_view_school_classes(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('school-classes.index'))
            ->assertForbidden();
    }

    public function test_can_create_school_class(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        Livewire::actingAs($user)
            ->test(SchoolClassIndex::class)
            ->call('create')
            ->set('unit_id', $unit->id)
            ->set('academic_year_id', $year->id)
            ->set('name', 'X-IPA-1')
            ->set('level', 'X')
            ->set('homeroom_teacher', 'Budi Santoso')
            ->set('capacity', 32)
            ->set('sort_order', 1)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('school_classes', [
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-IPA-1',
            'capacity' => 32,
        ]);
    }

    public function test_duplicate_name_within_same_unit_and_year_fails(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-IPA-1',
        ]);

        Livewire::actingAs($user)
            ->test(SchoolClassIndex::class)
            ->call('create')
            ->set('unit_id', $unit->id)
            ->set('academic_year_id', $year->id)
            ->set('name', 'X-IPA-1')
            ->set('capacity', 30)
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_same_name_allowed_in_different_academic_year(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-IPA-1',
        ]);

        $nextYear = AcademicYear::query()->create([
            'name' => '2096/2097',
            'start_date' => '2096-07-01',
            'end_date' => '2097-06-30',
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(SchoolClassIndex::class)
            ->call('create')
            ->set('unit_id', $unit->id)
            ->set('academic_year_id', $nextYear->id)
            ->set('name', 'X-IPA-1')
            ->set('capacity', 30)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(2, SchoolClass::query()->where('name', 'X-IPA-1')->count());
    }

    public function test_can_update_school_class(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-IPA-1',
            'capacity' => 30,
        ]);

        Livewire::actingAs($user)
            ->test(SchoolClassIndex::class)
            ->call('edit', $class->id)
            ->set('homeroom_teacher', 'Siti Aminah')
            ->set('capacity', 36)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('school_classes', [
            'id' => $class->id,
            'homeroom_teacher' => 'Siti Aminah',
            'capacity' => 36,
        ]);
    }

    public function test_can_delete_unused_school_class(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'XI-IPS-2',
        ]);

        Livewire::actingAs($user)
            ->test(SchoolClassIndex::class)
            ->call('confirmDelete', $class->id)
            ->call('delete');

        $this->assertSoftDeleted('school_classes', ['id' => $class->id]);
    }
}
