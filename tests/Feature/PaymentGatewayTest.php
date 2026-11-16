<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BillStatus;
use App\Enums\GatewayProvider;
use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\PaymentIntent;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use App\Services\Gateway\FakeGateway;
use App\Services\PaymentGatewayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * Alur tagihan digital: penerbitan VA/QRIS, webhook, dan pembentukan pembayaran.
 */
class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    private StudentBill $studentBill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        Config::set('payment_gateway.providers.fake.enabled', true);
        Config::set('payment_gateway.default', GatewayProvider::Fake->value);

        $unit = Unit::query()->create(['code' => 'PG', 'name' => 'Unit Gateway']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'X-PG',
        ]);
        $category = PaymentCategory::query()->create([
            'code' => 'PG-TEST',
            'name' => 'Gateway Test',
            'type' => PaymentCategoryType::Monthly,
            'default_amount' => 250000,
            'due_day' => 10,
            'is_active' => true,
        ]);
        $this->student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'PG-001',
            'nisn' => 'PG-NISN-001',
            'name' => 'Gateway Student',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
        $this->studentBill = app(BillingService::class)->create([
            'student_id' => $this->student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => '2026-11',
            'amount' => 250000,
        ]);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_intent_is_created_with_reference_and_pay_code(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $this->assertSame(PaymentIntentStatus::Pending, $intent->status);
        $this->assertSame('250000.00', (string) $intent->amount);
        $this->assertStringStartsWith('PG', $intent->reference);
        $this->assertNotNull($intent->pay_code);
        $this->assertNotNull($intent->expires_at);
    }

    public function test_repeated_request_returns_the_same_open_intent(): void
    {
        $service = app(PaymentGatewayService::class);

        $first = $service->createIntent($this->studentBill, GatewayProvider::Fake, 'va');
        $second = $service->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PaymentIntent::query()->count());
    }

    public function test_paid_bill_cannot_issue_intent(): void
    {
        $this->studentBill->forceFill([
            'paid_amount' => 250000,
            'remaining_amount' => 0,
            'status' => BillStatus::Paid,
        ])->save();

        $this->expectException(\InvalidArgumentException::class);

        app(PaymentGatewayService::class)
            ->createIntent($this->studentBill->refresh(), GatewayProvider::Fake, 'va');
    }

    public function test_webhook_with_valid_signature_creates_approved_payment(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $signature = app(FakeGateway::class)->sign($intent->reference);

        $response = $this->postJson('/webhook/pembayaran/fake', [
            'reference' => $intent->reference,
            'status' => 'paid',
            'amount' => 250000,
        ], ['X-Simulator-Signature' => $signature]);

        $response->assertOk()->assertJsonPath('status', 'paid');

        $intent->refresh();
        $this->assertSame(PaymentIntentStatus::Paid, $intent->status);
        $this->assertNotNull($intent->payment_id);

        $payment = Payment::query()->findOrFail($intent->payment_id);
        $this->assertSame(PaymentStatus::Approved, $payment->status);
        $this->assertSame('250000.00', (string) $payment->amount);

        $this->studentBill->refresh();
        $this->assertSame('250000.00', (string) $this->studentBill->paid_amount);
        $this->assertSame('0.00', (string) $this->studentBill->remaining_amount);
        $this->assertSame(BillStatus::Paid, $this->studentBill->status);
    }

    public function test_webhook_with_invalid_signature_is_rejected(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $this->postJson('/webhook/pembayaran/fake', [
            'reference' => $intent->reference,
            'status' => 'paid',
            'amount' => 250000,
        ], ['X-Simulator-Signature' => 'tanda-tangan-palsu'])
            ->assertStatus(403);

        $this->assertSame(PaymentIntentStatus::Pending, $intent->refresh()->status);
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_webhook_is_idempotent(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $signature = app(FakeGateway::class)->sign($intent->reference);
        $payload = ['reference' => $intent->reference, 'status' => 'paid', 'amount' => 250000];

        $this->postJson('/webhook/pembayaran/fake', $payload, ['X-Simulator-Signature' => $signature])->assertOk();
        $this->postJson('/webhook/pembayaran/fake', $payload, ['X-Simulator-Signature' => $signature])->assertOk();

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame('250000.00', (string) $this->studentBill->refresh()->paid_amount);
    }

    public function test_amount_mismatch_does_not_create_payment(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $signature = app(FakeGateway::class)->sign($intent->reference);

        $this->postJson('/webhook/pembayaran/fake', [
            'reference' => $intent->reference,
            'status' => 'paid',
            'amount' => 100000,
        ], ['X-Simulator-Signature' => $signature])->assertOk();

        $intent->refresh();
        $this->assertSame(PaymentIntentStatus::Paid, $intent->status);
        $this->assertNull($intent->payment_id);
        $this->assertSame(0, Payment::query()->count());
        $this->assertSame('0.00', (string) $this->studentBill->refresh()->paid_amount);
    }

    public function test_unknown_reference_returns_unprocessable(): void
    {
        $signature = app(FakeGateway::class)->sign('PG-TIDAK-ADA');

        $this->postJson('/webhook/pembayaran/fake', [
            'reference' => 'PG-TIDAK-ADA',
            'status' => 'paid',
            'amount' => 250000,
        ], ['X-Simulator-Signature' => $signature])->assertStatus(422);
    }

    public function test_cancel_marks_intent_cancelled(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        app(PaymentGatewayService::class)->cancel($intent, 'Tagihan dibuat ulang', $this->superadmin());

        $this->assertSame(PaymentIntentStatus::Cancelled, $intent->refresh()->status);
    }

    public function test_expire_overdue_marks_pending_intents_expired(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $intent->forceFill(['expires_at' => now()->subHour()])->save();

        $count = app(PaymentGatewayService::class)->expireOverdue();

        $this->assertSame(1, $count);
        $this->assertSame(PaymentIntentStatus::Expired, $intent->refresh()->status);
    }

    public function test_expire_command_marks_overdue_intents(): void
    {
        $intent = app(PaymentGatewayService::class)
            ->createIntent($this->studentBill, GatewayProvider::Fake, 'va');

        $intent->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('gateway:expire-intents')->assertSuccessful();

        $this->assertSame(PaymentIntentStatus::Expired, $intent->refresh()->status);
    }

    public function test_bill_show_component_issues_intent(): void
    {
        $this->actingAs($this->superadmin());

        \Livewire\Livewire::test(\App\Livewire\Bills\BillShow::class, ['studentBill' => $this->studentBill])
            ->call('startIntent')
            ->assertSet('showIntentModal', true)
            ->set('gatewayChannel', 'va')
            ->call('issueIntent')
            ->assertHasNoErrors()
            ->assertSet('showIntentModal', false);

        $this->assertSame(1, PaymentIntent::query()->count());
    }

    public function test_unit_gateway_settings_can_be_saved(): void
    {
        $this->actingAs($this->superadmin());

        $unit = Unit::query()->create(['code' => 'PGSET', 'name' => 'Unit Setting']);

        \Livewire\Livewire::test(\App\Livewire\Master\UnitIndex::class)
            ->call('edit', $unit->id)
            ->set('payment_gateway_enabled', true)
            ->set('payment_provider', GatewayProvider::Tripay->value)
            ->set('payment_channel', 'BRIVA')
            ->call('save')
            ->assertHasNoErrors();

        $unit->refresh();

        $this->assertTrue($unit->payment_gateway_enabled);
        $this->assertSame(GatewayProvider::Tripay, $unit->payment_provider);
        $this->assertSame('BRIVA', $unit->payment_channel);
        $this->assertSame(GatewayProvider::Tripay, $unit->gatewayProvider());
    }

    public function test_unit_gateway_requires_provider_when_enabled(): void
    {
        $this->actingAs($this->superadmin());

        $unit = Unit::query()->create(['code' => 'PGSET2', 'name' => 'Unit Setting 2']);

        \Livewire\Livewire::test(\App\Livewire\Master\UnitIndex::class)
            ->call('edit', $unit->id)
            ->set('payment_gateway_enabled', true)
            ->set('payment_provider', '')
            ->call('save')
            ->assertHasErrors(['payment_provider']);
    }

    public function test_intent_index_requires_permission(): void
    {
        $officer = User::query()->where('email', 'petugas@man7jombang.sch.id')->firstOrFail();

        $this->actingAs($officer);

        \Livewire\Livewire::test(\App\Livewire\Payments\Intents\PaymentIntentIndex::class)
            ->assertOk();
    }
}
