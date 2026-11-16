<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StaffPosition;
use App\Livewire\Master\StaffIndex;
use App\Models\StaffProfile;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffManagementTest extends TestCase
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

    public function test_guest_cannot_access_staff_page(): void
    {
        $this->get(route('staff.index'))->assertRedirect(route('login'));
    }

    public function test_authorized_user_can_view_staff_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get(route('staff.index'))
            ->assertOk()
            ->assertSee('Petugas');
    }

    public function test_user_without_permission_cannot_view_staff_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('staff.index'))
            ->assertForbidden();
    }

    public function test_can_create_staff_profile(): void
    {
        $unit = Unit::query()->firstOrFail();

        Livewire::actingAs($this->superadmin())
            ->test(StaffIndex::class)
            ->call('create')
            ->set('name', 'Ahmad Fauzi')
            ->set('nip', '197800112005011001')
            ->set('gender', Gender::Male->value)
            ->set('position', StaffPosition::Treasurer->value)
            ->set('unit_id', $unit->id)
            ->set('phone', '081234567890')
            ->set('email', 'ahmad.fauzi@man7jombang.sch.id')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $this->assertDatabaseHas('staff_profiles', [
            'name' => 'Ahmad Fauzi',
            'nip' => '197800112005011001',
            'position' => StaffPosition::Treasurer->value,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_duplicate_nip_fails_validation(): void
    {
        StaffProfile::query()->create([
            'name' => 'Petugas Lama',
            'nip' => '198001012006041002',
            'gender' => Gender::Male,
            'position' => StaffPosition::Teacher,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StaffIndex::class)
            ->call('create')
            ->set('name', 'Petugas Baru')
            ->set('nip', '198001012006041002')
            ->set('gender', Gender::Female->value)
            ->set('position', StaffPosition::Verifier->value)
            ->call('save')
            ->assertHasErrors(['nip']);
    }

    public function test_can_update_staff_profile(): void
    {
        $staff = StaffProfile::query()->create([
            'name' => 'Nama Awal',
            'gender' => Gender::Male,
            'position' => StaffPosition::Teacher,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StaffIndex::class)
            ->call('edit', $staff->id)
            ->set('name', 'Nama Diperbarui')
            ->set('position', StaffPosition::Headmaster->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('staff_profiles', [
            'id' => $staff->id,
            'name' => 'Nama Diperbarui',
            'position' => StaffPosition::Headmaster->value,
        ]);
    }

    public function test_can_soft_delete_staff_profile(): void
    {
        $staff = StaffProfile::query()->create([
            'name' => 'Petugas Sementara',
            'gender' => Gender::Female,
            'position' => StaffPosition::AdministrativeStaff,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StaffIndex::class)
            ->call('confirmDelete', $staff->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('staff_profiles', ['id' => $staff->id]);
    }

    public function test_search_filters_staff_by_name(): void
    {
        StaffProfile::query()->create([
            'name' => 'Siti Rahmawati',
            'gender' => Gender::Female,
            'position' => StaffPosition::PaymentOfficer,
            'is_active' => true,
        ]);

        StaffProfile::query()->create([
            'name' => 'Budi Hartono',
            'gender' => Gender::Male,
            'position' => StaffPosition::Teacher,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(StaffIndex::class)
            ->set('search', 'Rahmawati')
            ->assertSee('Siti Rahmawati')
            ->assertDontSee('Budi Hartono');
    }
}
