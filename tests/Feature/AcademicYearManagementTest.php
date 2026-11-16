<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Master\AcademicYearIndex;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AcademicYearManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_academic_years_page(): void
    {
        $this->get(route('academic-years.index'))
            ->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_academic_years(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('academic-years.index'))
            ->assertOk()
            ->assertSee('Tahun Ajaran')
            ->assertSee(AcademicYear::query()->firstOrFail()->name);
    }

    public function test_user_without_permission_cannot_view_academic_years(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('academic-years.index'))
            ->assertForbidden();
    }

    public function test_can_create_academic_year(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->set('name', '2099/2100')
            ->set('start_date', '2099-07-01')
            ->set('end_date', '2100-06-30')
            ->set('is_active', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('academic_years', [
            'name' => '2099/2100',
            'is_active' => false,
        ]);
    }

    public function test_duplicate_academic_year_name_fails(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $existing = AcademicYear::query()->firstOrFail();

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->set('name', $existing->name)
            ->set('start_date', '2025-07-01')
            ->set('end_date', '2026-06-30')
            ->call('save')
            ->assertHasErrors(['name']);
    }

    public function test_end_date_must_be_after_start_date(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->set('name', '2097/2098')
            ->set('start_date', '2097-07-01')
            ->set('end_date', '2097-06-01') // before start_date
            ->call('save')
            ->assertHasErrors(['end_date']);
    }

    public function test_activating_academic_year_deactivates_others(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $activeOriginal = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $newYear = AcademicYear::query()->create([
            'name' => '2098/2099',
            'start_date' => '2098-07-01',
            'end_date' => '2099-06-30',
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->call('activate', $newYear->id)
            ->assertDispatched('toast');

        $this->assertTrue((bool) $newYear->fresh()->is_active);
        $this->assertFalse((bool) $activeOriginal->fresh()->is_active);
    }

    public function test_can_delete_unused_academic_year(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $year = AcademicYear::query()->create([
            'name' => '2000/2001',
            'start_date' => '2000-07-01',
            'end_date' => '2001-06-30',
            'is_active' => false,
        ]);

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->call('confirmDelete', $year->id)
            ->call('delete');

        $this->assertSoftDeleted('academic_years', ['id' => $year->id]);
    }

    public function test_cannot_delete_academic_year_with_classes(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'XII-IPA-1',
        ]);

        Livewire::actingAs($user)
            ->test(AcademicYearIndex::class)
            ->call('confirmDelete', $year->id)
            ->call('delete')
            ->assertDispatched('toast', fn ($e, $p) => ($p['type'] ?? null) === 'error');

        $this->assertDatabaseHas('academic_years', ['id' => $year->id, 'deleted_at' => null]);
    }
}
