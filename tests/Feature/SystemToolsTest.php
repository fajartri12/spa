<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_activity_log_page_lists_entries_for_authorized_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        ActivityLog::query()->create([
            'user_id' => $user->id,
            'event' => 'units.created',
            'description' => 'Menambahkan unit MAN 7 Jombang',
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('activity-logs.index'))
            ->assertOk()
            ->assertSee('units.created')
            ->assertSee('Menambahkan unit MAN 7 Jombang');
    }

    public function test_activity_log_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('activity-logs.index'))->assertForbidden();
    }

    public function test_backup_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('backups.index'))->assertForbidden();
    }

    public function test_backup_page_is_accessible_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('backups.index'))->assertOk();
    }
}
