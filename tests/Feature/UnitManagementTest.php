<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_guest_cannot_access_units_page(): void
    {
        $response = $this->get(route('units.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_units(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('units.index'))
            ->assertOk()
            ->assertSee('Unit Pendidikan')
            ->assertSee('MAN 7 Jombang');
    }

    public function test_user_without_permission_cannot_view_units(): void
    {
        $user = User::factory()->create(); // No role, no permissions

        $this->actingAs($user)
            ->get(route('units.index'))
            ->assertForbidden();
    }

    public function test_can_create_new_unit(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Master\UnitIndex::class)
            ->set('code', 'SMK1')
            ->set('name', 'SMK Komite 1')
            ->set('level', 'SMK')
            ->set('description', 'Unit vokasi binaan')
            ->set('sort_order', 10)
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('units', [
            'code' => 'SMK1',
            'name' => 'SMK Komite 1',
        ]);
    }

    public function test_duplicate_code_validation_fails(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Master\UnitIndex::class)
            ->set('code', 'MAN7') // already exists from seed
            ->set('name', 'Duplikat Unit')
            ->set('sort_order', 1)
            ->set('is_active', true)
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_can_update_existing_unit(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->where('code', 'MAN7')->firstOrFail();

        Livewire::actingAs($user)
            ->test(\App\Livewire\Master\UnitIndex::class)
            ->call('edit', $unit->id)
            ->set('name', 'MAN 7 Jombang Unggulan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'MAN 7 Jombang Unggulan',
        ]);
    }

    public function test_can_delete_unused_unit(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->create([
            'code' => 'TEMP1',
            'name' => 'Unit Sementara',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Master\UnitIndex::class)
            ->call('confirmDelete', $unit->id)
            ->call('delete');

        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    }

    public function test_cannot_delete_unit_with_classes(): void
    {
        /** @var User $user */
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $unit = Unit::query()->where('code', 'MAN7')->firstOrFail();
        $year = AcademicYear::query()->firstOrFail();

        SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-IPA-1',
        ]);

        Livewire::actingAs($user)
            ->test(\App\Livewire\Master\UnitIndex::class)
            ->call('confirmDelete', $unit->id)
            ->call('delete')
            ->assertDispatched('toast', function (string $event, array $params): bool {
                return ($params['type'] ?? null) === 'error';
            });

        $this->assertDatabaseHas('units', ['id' => $unit->id, 'deleted_at' => null]);
    }
}
