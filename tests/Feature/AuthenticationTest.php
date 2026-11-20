<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_login_page_can_be_rendered(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Masuk ke Aplikasi');
    }

    public function test_login_page_shows_demo_accounts_for_every_role(): void
    {
        $response = $this->get(route('login'))
            ->assertOk()
            ->assertSee('Akun Demo')
            ->assertSee('password');

        foreach (RoleName::cases() as $role) {
            $response->assertSee($role->label());
        }

        $this->assertSame(7, \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)->instance()->demoAccounts()->count());
    }

    public function test_demo_account_selection_fills_login_form(): void
    {
        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)
            ->call('useDemoAccount', 'bendahara@email.com')
            ->assertSet('email', 'bendahara@email.com')
            ->assertSet('password', 'password');
    }

    public function test_root_redirects_to_dashboard(): void
    {
        $this->get('/')->assertRedirect('/beranda');
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', 'superadmin@email.com')
            ->set('password', 'salah-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create([
            'email' => 'nonaktif@man7jombang.sch.id',
            'password' => 'password',
            'status' => \App\Enums\UserStatus::Inactive,
        ]);

        \Livewire\Livewire::test(\App\Livewire\Auth\Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_authenticated_user_can_view_dashboard(): void
    {
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Selamat Datang!')
            ->assertSee('Data Unit')
            ->assertSee('Data Kelas')
            ->assertSee('Data Tahun')
            ->assertSee('Data Siswa')
            ->assertSee('Akun Petugas')
            ->assertSee('Akun Siswa');
    }

    public function test_dashboard_requires_permission(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_user_can_logout(): void
    {
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_seven_roles_are_seeded(): void
    {
        $this->assertSame(7, Role::query()->count());

        foreach (RoleName::cases() as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role->value]);
        }
    }

    public function test_super_admin_has_all_permissions(): void
    {
        $user = User::query()->where('email', 'superadmin@email.com')->firstOrFail();

        $this->assertTrue($user->hasRole(RoleName::SuperAdministrator->value));
        $this->assertSame(
            \Spatie\Permission\Models\Permission::query()->count(),
            $user->getAllPermissions()->count(),
        );
    }
}
