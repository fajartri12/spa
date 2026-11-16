<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Support\Csv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Regresi untuk temuan audit keamanan (H-01, H-02, M-01..M-04, L-02..L-09).
 */
class SecurityRemediationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        return $user;
    }

    // H-01: tidak ada password portal bersama

    public function test_portal_password_config_no_longer_exists(): void
    {
        $this->assertNull(config('services.student_portal.default_password'));
    }

    // M-01: editor role

    public function test_actor_cannot_edit_own_role_permissions(): void
    {
        $role = Role::query()->where('name', RoleName::Administrator->value)->firstOrFail();

        $actor = User::factory()->create();
        $actor->assignRole($role->name);
        // Beri kemampuan mengelola role agar yang diuji adalah guard kepemilikan role.
        $actor->givePermissionTo([PermissionName::RolesView->value, PermissionName::RolesUpdate->value]);

        Livewire::actingAs($actor)
            ->test('settings.role-index')
            ->call('edit', $role->id)
            ->assertSet('editingId', null);
    }

    public function test_actor_cannot_grant_permissions_they_do_not_hold(): void
    {
        $target = Role::query()->where('name', RoleName::Headmaster->value)->firstOrFail();

        $actor = User::factory()->create();
        $actor->givePermissionTo([
            PermissionName::RolesView->value,
            PermissionName::RolesUpdate->value,
            PermissionName::DashboardView->value,
        ]);

        Livewire::actingAs($actor)
            ->test('settings.role-index')
            ->call('edit', $target->id)
            ->set('selected', [PermissionName::UsersDelete->value])
            ->call('save');

        $this->assertFalse(
            $target->fresh()->hasPermissionTo(PermissionName::UsersDelete->value),
            'Aktor tidak boleh mendelegasikan permission yang tidak dimilikinya.',
        );
    }

    public function test_non_super_admin_cannot_edit_super_administrator_role(): void
    {
        $role = Role::query()->where('name', RoleName::SuperAdministrator->value)->firstOrFail();

        $actor = User::factory()->create();
        $actor->givePermissionTo([PermissionName::RolesView->value, PermissionName::RolesUpdate->value]);

        Livewire::actingAs($actor)
            ->test('settings.role-index')
            ->call('edit', $role->id)
            ->assertSet('editingId', null);
    }

    // M-02: manajemen pengguna

    public function test_non_super_admin_cannot_assign_privileged_role(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::Treasurer->value);
        $actor->givePermissionTo([PermissionName::UsersView->value, PermissionName::UsersCreate->value]);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('create')
            ->set('name', 'Penyusup')
            ->set('email', 'penyusup@example.test')
            ->set('status', UserStatus::Active->value)
            ->set('role', RoleName::SuperAdministrator->value)
            ->set('password', 'rahasia12345')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'penyusup@example.test']);
    }

    public function test_non_super_admin_cannot_manage_super_admin_account(): void
    {
        $super = $this->superAdmin();

        $actor = User::factory()->create();
        $actor->assignRole(RoleName::Treasurer->value);
        $actor->givePermissionTo([
            PermissionName::UsersView->value,
            PermissionName::UsersUpdate->value,
            PermissionName::UsersDelete->value,
        ]);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('edit', $super->id)
            ->assertSet('editingId', null);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('confirmDelete', $super->id)
            ->assertSet('deletingId', null);

        $this->assertNotSoftDeleted($super);
    }

    public function test_actor_cannot_change_own_role(): void
    {
        $actor = $this->superAdmin();

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('edit', $actor->id)
            ->set('role', RoleName::PaymentOfficer->value)
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertTrue($actor->fresh()->hasRole(RoleName::SuperAdministrator->value));
    }

    // M-03: CSV formula injection

    public function test_csv_helper_neutralises_formula_prefixes(): void
    {
        $this->assertSame("'=cmd", Csv::safe('=cmd'));
        $this->assertSame("'+123", Csv::safe('+123'));
        $this->assertSame("'-123", Csv::safe('-123'));
        $this->assertSame("'@SUM(A1)", Csv::safe('@SUM(A1)'));
        $this->assertSame('aman', Csv::safe('aman'));
        $this->assertNull(Csv::safe(null));
    }

    public function test_student_export_escapes_formula_injection(): void
    {
        $unit = Unit::query()->firstOrFail();

        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'CSV-INJECT-1',
            'name' => '=HYPERLINK("http://evil.test")',
            'gender' => 'male',
            'status' => 'active',
            'entry_year' => 2026,
        ]);

        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::StudentsExport->value);

        $content = $this->actingAs($user)
            ->get(route('students.export'))
            ->streamedContent();

        $this->assertStringContainsString('\'=HYPERLINK', $content);
        $this->assertStringNotContainsString("\"=HYPERLINK", $content);
    }

    // M-04: Livewire persistent middleware + Gate untuk akun nonaktif

    public function test_active_middleware_is_persistent_for_livewire_requests(): void
    {
        $this->assertContains(
            \App\Http\Middleware\EnsureUserIsActive::class,
            app('livewire')->getPersistentMiddleware(),
        );
    }

    public function test_inactive_user_is_logged_out_by_active_middleware(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Inactive]);
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_gate_denies_every_ability_for_inactive_user(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Inactive]);
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->assertFalse(Gate::forUser($user)->allows(PermissionName::UsersView->value));
    }

    public function test_gate_allows_active_user_permissions(): void
    {
        $user = $this->superAdmin();

        $this->assertTrue(Gate::forUser($user)->allows(PermissionName::UsersView->value));
    }

    // L-03: permission hapus backup terpisah

    public function test_backup_delete_requires_its_own_permission(): void
    {
        $this->assertNotNull(Permission::findByName(PermissionName::BackupDelete->value, 'web'));

        $actor = User::factory()->create();
        $actor->givePermissionTo([PermissionName::BackupCreate->value]);

        // Tanpa `backup.delete`, tombol hapus tidak dirender.
        $this->actingAs($actor)
            ->get(route('backups.index'))
            ->assertOk()
            ->assertDontSee('Hapus');

        Livewire::actingAs($actor)
            ->test('tools.backup-index')
            ->call('confirmDelete', 1)
            ->assertForbidden();
    }

    // L-04: rincian server

    public function test_server_details_are_limited_for_settings_view_holders(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::SettingsView->value);

        $this->actingAs($user)
            ->get(route('server-info.index'))
            ->assertOk()
            ->assertDontSee('Versi PHP')
            ->assertDontSee(PHP_VERSION)
            ->assertDontSee('Versi Laravel');
    }

    // L-05: scoping ekspor siswa memakai StaffProfile

    public function test_student_export_is_scoped_to_staff_unit(): void
    {
        $units = Unit::query()->orderBy('id')->take(2)->get();

        if ($units->count() < 2) {
            $this->markTestSkipped('Butuh minimal dua unit untuk menguji scoping.');
        }

        Student::query()->create(['unit_id' => $units[0]->id, 'nis' => 'SCOPE-1', 'name' => 'Siswa Unit Satu', 'gender' => 'male', 'status' => 'active', 'entry_year' => 2026]);
        Student::query()->create(['unit_id' => $units[1]->id, 'nis' => 'SCOPE-2', 'name' => 'Siswa Unit Dua', 'gender' => 'male', 'status' => 'active', 'entry_year' => 2026]);

        $user = User::factory()->create();
        $user->assignRole(RoleName::Treasurer->value);
        $user->givePermissionTo(PermissionName::StudentsExport->value);

        \App\Models\StaffProfile::query()->create([
            'user_id' => $user->id,
            'unit_id' => $units[0]->id,
            'name' => $user->name,
            'position' => \App\Enums\StaffPosition::AdministrativeStaff->value,
            'is_active' => true,
        ]);

        $content = $this->actingAs($user)
            ->get(route('students.export'))
            ->streamedContent();

        $this->assertStringContainsString('SCOPE-1', $content);
        $this->assertStringNotContainsString('SCOPE-2', $content);
    }

    // L-08: masking penerima notifikasi

    public function test_notification_log_masks_recipient(): void
    {
        $this->assertSame('0812****7890', \App\Models\NotificationLog::mask('081234567890'));
        $this->assertSame('pe*****@example.com', \App\Models\NotificationLog::mask('petugas@example.com'));
    }

    public function test_logs_are_prunable(): void
    {
        $old = \App\Models\ActivityLog::query()->create([
            'event' => 'test.old',
            'description' => 'Log lama',
            'created_at' => now(),
        ]);
        \App\Models\ActivityLog::query()->whereKey($old->id)->update(['created_at' => now()->subDays(400)]);

        \App\Models\ActivityLog::query()->create([
            'event' => 'test.new',
            'description' => 'Log baru',
            'created_at' => now(),
        ]);

        $this->artisan('model:prune', ['--model' => [\App\Models\ActivityLog::class]])->assertSuccessful();

        $this->assertDatabaseMissing('activity_logs', ['event' => 'test.old']);
        $this->assertDatabaseHas('activity_logs', ['event' => 'test.new']);
    }

    // L-09: whitelist warna tema

    public function test_theme_colour_is_ignored_when_not_a_hex_value(): void
    {
        \App\Models\AppSetting::set('theme_primary_color', 'red; } body { display: none');
        \App\Models\AppSetting::set('theme_primary_dark', 'expression(alert(1))');

        $this->actingAs($this->superAdmin())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-primary: #2872c8', false)
            ->assertDontSee('expression(alert(1))', false);
    }

    // L-07: header keamanan

    public function test_security_headers_are_present_on_responses(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }

    // L-02: filter cetak divalidasi dan diteruskan

    public function test_print_download_rejects_unknown_unit_filter(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::ReportsExport->value);

        $this->actingAs($user)
            ->get(route('reports.print.download', ['type' => 'outstanding_bills', 'unit_id' => 999999]))
            ->assertSessionHasErrors('unit_id');
    }

    public function test_print_page_ignores_all_marker_filters(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo([PermissionName::ReportsView->value, PermissionName::ReportsExport->value]);

        $this->actingAs($user)
            ->get(route('reports.print'))
            ->assertOk()
            ->assertDontSee('unit_id=all', false)
            ->assertDontSee('status=all', false);
    }
}
