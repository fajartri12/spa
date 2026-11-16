<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Livewire\Settings\GatewayIndex;
use App\Models\AppSetting;
use App\Models\NotificationTemplate;
use App\Models\User;
use App\Livewire\Settings\NotificationTemplateIndex;
use App\Support\SettingsConfigOverlay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SettingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('settings.index'))->assertForbidden();
    }

    public function test_super_admin_can_update_general_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('settings.index'))->assertOk()->assertSee('Pengaturan Umum');

        Livewire::actingAs($user)
            ->test('settings.setting-index')
            ->set('app_name', 'KOMITE MAN 7 JOMBANG BARU')
            ->set('school_name', 'MAN 7 JOMBANG')
            ->set('currency_symbol', 'Rp')
            ->set('date_format', 'd F Y')
            ->set('rows_per_page', 25)
            ->set('footer_text', '© KOMITE MAN 7 JOMBANG')
            ->set('payment_prefix', 'TRX')
            ->set('bill_prefix', 'TGH')
            ->set('studentSavingsEnabled', false)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('KOMITE MAN 7 JOMBANG BARU', AppSetting::get('app_name'));
        $this->assertSame('0', AppSetting::get('feature_student_savings_enabled'));
        $this->assertSame('25', AppSetting::get('rows_per_page'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_settings_validation_rejects_invalid_email(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test('settings.setting-index')
            ->set('school_email', 'bukan-email')
            ->call('save')
            ->assertHasErrors(['school_email']);
    }

    public function test_users_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
    }

    public function test_super_admin_can_create_user_with_role(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('create')
            ->set('name', 'Petugas Baru')
            ->set('email', 'petugas.baru@example.test')
            ->set('username', 'petugasbaru')
            ->set('status', UserStatus::Active->value)
            ->set('role', RoleName::PaymentOfficer->value)
            ->set('password', 'rahasia12345')
            ->call('save')
            ->assertHasNoErrors();

        $created = User::query()->where('email', 'petugas.baru@example.test')->firstOrFail();

        $this->assertTrue($created->hasRole(RoleName::PaymentOfficer->value));
        $this->assertSame(UserStatus::Active, $created->status);
        $this->assertDatabaseHas('activity_logs', ['event' => 'users.created']);
    }

    public function test_user_cannot_delete_own_account(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('confirmDelete', $actor->id)
            ->call('delete');

        $this->assertNotSoftDeleted($actor);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::SuperAdministrator->value);

        $existing = User::factory()->create(['email' => 'dipakai@example.test']);

        Livewire::actingAs($actor)
            ->test('settings.user-index')
            ->call('create')
            ->set('name', 'Duplikat')
            ->set('email', $existing->email)
            ->set('status', UserStatus::Active->value)
            ->set('role', RoleName::Administrator->value)
            ->set('password', 'rahasia12345')
            ->call('save')
            ->assertHasErrors(['email']);
    }

    public function test_roles_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::Verifier->value);

        $this->actingAs($user)->get(route('roles.index'))->assertForbidden();
    }

    public function test_role_permissions_can_be_updated(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::SuperAdministrator->value);

        $role = Role::query()->where('name', RoleName::Headmaster->value)->firstOrFail();

        Livewire::actingAs($actor)
            ->test('settings.role-index')
            ->call('edit', $role->id)
            ->call('toggle', PermissionName::ReportsExport->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($role->fresh()->hasPermissionTo(PermissionName::ReportsExport->value));
        $this->assertDatabaseHas('activity_logs', ['event' => 'roles.updated']);
    }

    public function test_super_administrator_role_keeps_all_permissions(): void
    {
        $actor = User::factory()->create();
        $actor->assignRole(RoleName::SuperAdministrator->value);

        $role = Role::query()->where('name', RoleName::SuperAdministrator->value)->firstOrFail();

        Livewire::actingAs($actor)
            ->test('settings.role-index')
            ->call('edit', $role->id)
            ->call('clearAll')
            ->call('save');

        $this->assertSame(
            count(PermissionName::values()),
            $role->fresh()->permissions()->count(),
        );
    }

    public function test_system_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('settings.system'))->assertForbidden();
    }

    public function test_super_admin_can_update_system_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('settings.system'))->assertOk()->assertSee('Pengaturan Sistem');

        Livewire::actingAs($user)
            ->test('settings.system-index')
            ->set('app_name', 'Aplikasi Pengelolaan Pembayaran Tagihan')
            ->set('app_url', 'http://127.0.0.1:8123')
            ->set('service_fee', 0)
            ->set('max_bill_items', 6)
            ->set('bill_due_day', 10)
            ->set('min_category_amount', 0)
            ->set('max_category_amount', 10000000)
            ->set('min_payment_amount', 1000)
            ->set('min_installment_amount', 1000)
            ->set('min_savings_deposit', 1000)
            ->set('max_savings_deposit', 1000000)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Aplikasi Pengelolaan Pembayaran Tagihan', AppSetting::get('app_name'));
        $this->assertSame(6, (int) AppSetting::get('max_bill_items'));
        $this->assertSame(10, (int) AppSetting::get('bill_due_day'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_system_settings_validates_min_max_ranges(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test('settings.system-index')
            ->set('min_category_amount', 50000)
            ->set('max_category_amount', 10000) // max < min
            ->call('save')
            ->assertHasErrors(['max_category_amount']);
    }

    public function test_notification_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('settings.notifications'))->assertForbidden();
    }

    public function test_super_admin_can_view_and_update_notification_templates(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('settings.notifications'))
            ->assertOk()
            ->assertSee('Pengaturan Notifikasi')
            ->assertSee('Pesan Notifikasi Proses Pembayaran')
            ->assertSee('NOMOR_INVOICE');

        Livewire::actingAs($user)
            ->test(NotificationTemplateIndex::class)
            ->call('insertVariable', 'NOMOR_INVOICE')
            ->set('forms.PAYMENT_PROCESSED.body', "Halo [NAMA_SISWA], pembayaran sukses invoice [NOMOR_INVOICE].")
            ->call('save')
            ->assertHasNoErrors();

        $template = NotificationTemplate::query()->where('code', 'PAYMENT_PROCESSED')->firstOrFail();
        $this->assertStringContainsString('Halo [NAMA_SISWA]', $template->body);
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_super_admin_can_reset_notification_template(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(NotificationTemplateIndex::class)
            ->set('forms.PAYMENT_PROCESSED.body', 'Kustom')
            ->call('resetTemplate')
            ->assertSet('forms.PAYMENT_PROCESSED.body', NotificationTemplateIndex::TEMPLATES['PAYMENT_PROCESSED']['body']);
    }

    public function test_appearance_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('settings.appearance'))->assertForbidden();
    }

    public function test_super_admin_can_update_appearance_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('settings.appearance'))
            ->assertOk()
            ->assertSee('Pengaturan Tampilan');

        Livewire::actingAs($user)
            ->test('settings.appearance-index')
            ->call('selectPreset', 'emerald')
            ->set('theme_radius_style', 'rounded')
            ->set('theme_header_style', 'dark')
            ->set('theme_nav_style', 'dark')
            ->set('theme_nav_sticky', false)
            ->set('theme_nav_icons', 'text_only')
            ->set('theme_login_style', 'centered')
            ->set('theme_login_bg', 'gradient-emerald')
            ->set('theme_login_title', 'Masuk Aplikasi Komite')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('#107c41', AppSetting::get('theme_primary_color'));
        $this->assertSame('rounded', AppSetting::get('theme_radius_style'));
        $this->assertSame('dark', AppSetting::get('theme_nav_style'));
        $this->assertSame('0', AppSetting::get('theme_nav_sticky'));
        $this->assertSame('centered', AppSetting::get('theme_login_style'));
        $this->assertSame('Masuk Aplikasi Komite', AppSetting::get('theme_login_title'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_theme_variables_are_rendered_in_app_layout(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        AppSetting::set('theme_primary_color', '#991b1b');
        AppSetting::set('theme_radius_style', 'sharp');
        AppSetting::set('theme_login_style', 'centered');
        AppSetting::set('theme_login_title', 'Judul Login Kustom');

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('--color-primary: #991b1b', false)
            ->assertSee('--radius-card: 2px', false);

        auth()->logout();

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Judul Login Kustom');
    }

    public function test_appearance_settings_validation_rejects_invalid_color(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test('settings.appearance-index')
            ->set('theme_primary_color', 'not-a-color')
            ->call('save')
            ->assertHasErrors(['theme_primary_color']);
    }

    public function test_gateway_settings_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('settings.gateway'))->assertForbidden();
    }

    public function test_super_admin_can_view_gateway_settings_page(): void
    {
        Config::set('payment_gateway.providers.tripay.api_key', 'tripay-rahasia-api-key');
        Config::set('payment_gateway.providers.tripay.enabled', true);

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('settings.gateway'))
            ->assertOk()
            ->assertSee('Pengaturan Pembayaran')
            ->assertSee('Tripay')
            ->assertSee('Midtrans')
            ->assertSee('Simulator (Pengujian)')
            ->assertSee(route('webhooks.payment', ['provider' => 'tripay']))
            ->assertDontSee('tripay-rahasia-api-key');
    }

    public function test_gateway_settings_page_hides_simulator_from_default_options(): void
    {
        Config::set('payment_gateway.providers.fake.enabled', false);

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->assertViewHas('providerOptions', fn (array $options): bool => ! array_key_exists('fake', $options));
    }

    public function test_super_admin_can_update_gateway_settings(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_default', 'tripay')
            ->set('payment_gateway_expiry_hours', 12)
            ->set('reconciliation_unique_amount_enabled', true)
            ->set('reconciliation_date_tolerance_days', 14)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('tripay', AppSetting::get('payment_gateway_default'));
        $this->assertSame('12', AppSetting::get('payment_gateway_expiry_hours'));
        $this->assertSame('1', AppSetting::get('reconciliation_unique_amount_enabled'));
        $this->assertSame('14', AppSetting::get('reconciliation_date_tolerance_days'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_gateway_settings_allows_clearing_default_provider(): void
    {
        AppSetting::set('payment_gateway_default', 'tripay');

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_default', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('', AppSetting::get('payment_gateway_default'));
    }

    public function test_gateway_settings_validation_rejects_out_of_range_values(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_expiry_hours', 0)
            ->set('reconciliation_date_tolerance_days', 91)
            ->call('save')
            ->assertHasErrors(['payment_gateway_expiry_hours', 'reconciliation_date_tolerance_days']);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_expiry_hours', 25)
            ->set('reconciliation_date_tolerance_days', 0)
            ->call('save')
            ->assertHasErrors(['payment_gateway_expiry_hours', 'reconciliation_date_tolerance_days']);
    }

    public function test_gateway_settings_rejects_unknown_or_disabled_provider(): void
    {
        Config::set('payment_gateway.providers.fake.enabled', false);

        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_default', 'fake')
            ->call('save')
            ->assertHasErrors(['payment_gateway_default']);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->set('payment_gateway_default', 'tidak-dikenal')
            ->call('save')
            ->assertHasErrors(['payment_gateway_default']);
    }

    public function test_toggling_unique_amount_saves_immediately(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        Livewire::actingAs($user)
            ->test(GatewayIndex::class)
            ->assertSet('reconciliation_unique_amount_enabled', false)
            ->set('reconciliation_unique_amount_enabled', true)
            ->assertHasNoErrors();

        $this->assertSame('1', AppSetting::get('reconciliation_unique_amount_enabled'));
        $this->assertDatabaseHas('activity_logs', ['event' => 'settings.updated']);
    }

    public function test_settings_overlay_applies_gateway_values_to_config(): void
    {
        Config::set('payment_gateway.default', '');
        Config::set('payment_gateway.expiry_hours', 24);
        Config::set('payment_gateway.reconciliation.unique_amount_enabled', false);
        Config::set('payment_gateway.reconciliation.date_tolerance_days', 7);

        AppSetting::set('payment_gateway_default', 'midtrans');
        AppSetting::set('payment_gateway_expiry_hours', '6');
        AppSetting::set('reconciliation_unique_amount_enabled', '1');
        AppSetting::set('reconciliation_date_tolerance_days', '21');

        SettingsConfigOverlay::applyPaymentGateway();

        $this->assertSame('midtrans', config('payment_gateway.default'));
        $this->assertSame(6, config('payment_gateway.expiry_hours'));
        $this->assertTrue(config('payment_gateway.reconciliation.unique_amount_enabled'));
        $this->assertSame(21, config('payment_gateway.reconciliation.date_tolerance_days'));
    }

    public function test_settings_overlay_ignores_missing_rows(): void
    {
        AppSetting::query()->whereIn('key', [
            'payment_gateway_default',
            'payment_gateway_expiry_hours',
            'reconciliation_unique_amount_enabled',
            'reconciliation_date_tolerance_days',
        ])->delete();
        Cache::forget(AppSetting::CACHE_KEY);

        Config::set('payment_gateway.expiry_hours', 24);
        Config::set('payment_gateway.reconciliation.unique_amount_enabled', false);

        SettingsConfigOverlay::applyPaymentGateway();

        $this->assertSame(24, config('payment_gateway.expiry_hours'));
        $this->assertFalse(config('payment_gateway.reconciliation.unique_amount_enabled'));
        $this->assertSame('', AppSetting::get('payment_gateway_default', ''));
    }
}
