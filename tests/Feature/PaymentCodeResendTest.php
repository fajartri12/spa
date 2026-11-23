<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\GatewayProvider;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\StudentStatus;
use App\Enums\UserStatus;
use App\Livewire\Payments\Intents\PaymentIntentIndex;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\NotificationLog;
use App\Models\PaymentCategory;
use App\Models\PaymentIntent;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\GuardianService;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Kirim ulang kode bayar (VA/QRIS) ke WhatsApp wali.
 */
class PaymentCodeResendTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        Config::set('services.whatsapp.token', 'test-token');
        Config::set('services.whatsapp.endpoint', 'https://wa.test/api/send');
    }

    public function test_resend_sends_pay_code_to_primary_guardian_whatsapp(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true])]);

        [$intent, $guardian] = $this->makeIntentWithGuardian();

        Livewire::actingAs($this->userWith('payment-gateways.view'))
            ->test(PaymentIntentIndex::class)
            ->call('resendWhatsApp', $intent->id)
            ->assertHasNoErrors();

        Http::assertSent(function ($request) use ($guardian, $intent) {
            $body = (string) $request['body'];

            return $request['to'] === $guardian->phone
                && str_contains($body, (string) $intent->pay_code)
                && str_contains($body, 'Siswa Kode Bayar');
        });

        $log = NotificationLog::query()->latest('id')->firstOrFail();
        $this->assertSame('whatsapp', $log->channel);
        $this->assertSame('sent', $log->status);
        $this->assertSame($guardian->phone, $log->recipient);
    }

    public function test_resend_falls_back_to_student_parent_phone(): void
    {
        Http::fake(['wa.test/*' => Http::response(['success' => true])]);

        $intent = $this->makeIntent(withGuardian: false, parentPhone: '081277778888');

        Livewire::actingAs($this->userWith('payment-gateways.view'))
            ->test(PaymentIntentIndex::class)
            ->call('resendWhatsApp', $intent->id)
            ->assertHasNoErrors();

        Http::assertSent(fn ($request) => $request['to'] === '081277778888');
        $this->assertSame('sent', NotificationLog::query()->latest('id')->value('status'));
    }

    public function test_resend_rejects_closed_intent(): void
    {
        Http::fake();

        [$intent] = $this->makeIntentWithGuardian();
        $intent->forceFill(['status' => \App\Enums\PaymentIntentStatus::Paid])->save();

        Livewire::actingAs($this->userWith('payment-gateways.view'))
            ->test(PaymentIntentIndex::class)
            ->call('resendWhatsApp', $intent->id)
            ->assertDispatched('toast', type: 'error');

        Http::assertNothingSent();
        $this->assertSame(0, NotificationLog::query()->count());
    }

    public function test_resend_requires_permission(): void
    {
        Http::fake();

        [$intent] = $this->makeIntentWithGuardian();

        Livewire::actingAs($this->userWith('payments.view'))
            ->test(PaymentIntentIndex::class)
            ->assertForbidden();

        Http::assertNothingSent();
        $this->assertTrue($intent->fresh()->status->isOpen());
        $this->assertSame(0, NotificationLog::query()->count());
    }

    /**
     * @return array{0: PaymentIntent, 1: Guardian}
     */
    private function makeIntentWithGuardian(): array
    {
        $guardian = Guardian::query()->create([
            'name' => 'Wali Kode',
            'relation' => 'father',
            'phone' => '081255556666',
            'is_active' => true,
        ]);

        $intent = $this->makeIntent(withGuardian: false);

        app(GuardianService::class)->syncStudents($guardian, [$intent->student_id]);

        return [$intent, $guardian];
    }

    private function makeIntent(bool $withGuardian = true, ?string $parentPhone = null): PaymentIntent
    {
        Config::set('payment_gateway.providers.fake.enabled', true);

        $unit = Unit::query()->firstOrCreate(
            ['code' => 'TPC'],
            ['name' => 'Unit Kode', 'level' => 'Test', 'sort_order' => 91, 'is_active' => true],
        );

        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $student = Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => '2026701',
            'name' => 'Siswa Kode Bayar',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
            'parent_name' => 'Bapak Kode',
            'parent_phone' => $parentPhone,
        ]);

        $category = PaymentCategory::query()->create([
            'code' => 'PC-'.uniqid(),
            'name' => 'Tagihan Kode',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 300000,
            'due_day' => 10,
            'is_active' => true,
        ]);

        $bill = app(BillingService::class)->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => '2026-11',
            'amount' => 300000,
        ]);

        return app(PaymentGatewayService::class)->createIntent($bill, GatewayProvider::Fake, 'va');
    }

    private function userWith(string $permission): User
    {
        $user = User::query()->firstOrCreate(
            ['email' => 'kode@example.test'],
            ['name' => 'Petugas Kode', 'password' => bcrypt('secret'), 'status' => UserStatus::Active],
        );
        $user->givePermissionTo($permission);

        return $user;
    }
}