<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Guardian;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ubah password portal orang tua mandiri lewat OTP WhatsApp.
 */
class GuardianPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('services.whatsapp.token', 'test-token');
        Config::set('services.whatsapp.endpoint', 'https://wa.test/api/send');
    }

    public function test_forgot_password_pages_are_public(): void
    {
        $this->get('/portal/orang-tua/lupa-password')
            ->assertOk()
            ->assertSee('Lupa Password');

        $this->get('/portal/orang-tua/lupa-password/verifikasi')
            ->assertOk()
            ->assertSee('Verifikasi OTP');
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get('/portal/orang-tua/login')
            ->assertSee(route('public.guardian.forgot'), false);
    }

    public function test_otp_is_sent_and_used_to_change_password(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true])]);

        $guardian = $this->makeGuardian('081299990001');

        $this->post('/portal/orang-tua/lupa-password', ['phone' => $guardian->phone])
            ->assertRedirect(route('public.guardian.forgot.verify'))
            ->assertSessionHas('status');

        // OTP tidak boleh tersimpan sebagai teks biasa di log notifikasi.
        $log = NotificationLog::query()->latest('id')->firstOrFail();
        $this->assertSame('sent', $log->status);
        $this->assertNull($log->message);

        $otp = $this->extractOtpFromSentRequests();
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);

        $this->post('/portal/orang-tua/lupa-password/verifikasi', [
            'phone' => $guardian->phone,
            'otp' => $otp,
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertRedirect(route('public.guardian.login'));

        $guardian->refresh();
        $this->assertTrue(Hash::check('password-baru-123', (string) $guardian->password));

        // Password baru benar-benar dapat dipakai masuk portal.
        $this->post('/portal/orang-tua/authenticate', [
            'phone' => $guardian->phone,
            'password' => 'password-baru-123',
        ])->assertRedirect('/portal/orang-tua');
    }

    public function test_wrong_otp_is_rejected_and_password_unchanged(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true])]);

        $guardian = $this->makeGuardian('081299990002');
        $this->post('/portal/orang-tua/lupa-password', ['phone' => $guardian->phone]);

        $this->post('/portal/orang-tua/lupa-password/verifikasi', [
            'phone' => $guardian->phone,
            'otp' => '000000',
            'password' => 'password-baru-123',
            'password_confirmation' => 'password-baru-123',
        ])->assertSessionHasErrors(['otp']);

        $this->assertTrue(Hash::check('lama-password', (string) $guardian->fresh()->password));
    }

    public function test_unknown_phone_gets_generic_response_without_sending_message(): void
    {
        Http::fake();

        $this->post('/portal/orang-tua/lupa-password', ['phone' => '081200000099'])
            ->assertRedirect(route('public.guardian.forgot.verify'))
            ->assertSessionHas('status');

        Http::assertNothingSent();
        $this->assertSame(0, NotificationLog::query()->count());
    }

    public function test_reset_rejects_short_password(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true])]);

        $guardian = $this->makeGuardian('081299990003');
        $this->post('/portal/orang-tua/lupa-password', ['phone' => $guardian->phone]);

        $this->post('/portal/orang-tua/lupa-password/verifikasi', [
            'phone' => $guardian->phone,
            'otp' => '123456',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors(['password']);
    }

    private function extractOtpFromSentRequests(): string
    {
        $otp = null;

        Http::assertSent(function ($request) use (&$otp) {
            if (preg_match('/\*(\d{6})\*/', (string) $request['body'], $matches) === 1) {
                $otp = $matches[1];
            }

            return true;
        });

        return (string) $otp;
    }

    private function makeGuardian(string $phone): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Wali OTP',
            'relation' => 'mother',
            'phone' => $phone,
            'is_active' => true,
        ]);
        $guardian->forceFill(['password' => 'lama-password', 'password_set_at' => now()])->save();

        return $guardian;
    }
}