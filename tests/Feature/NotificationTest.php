<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Jobs\SendFeeReminder;
use App\Models\Bill;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\PaymentCategory;
use App\Models\AcademicYear;
use App\Models\ReminderSchedule;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_reminder_command_queues_jobs_for_bills_due_in_n_days(): void
    {
        Queue::fake();

        $template = NotificationTemplate::query()->where('channel', 'whatsapp')->firstOrFail();
        $schedule = ReminderSchedule::query()->where('notification_template_id', $template->id)->firstOrFail();
        $schedule->update(['days_before_due' => 3, 'is_active' => true]);

        $student = $this->makeStudent('081234567890');

        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $category = PaymentCategory::query()->create([
            'code' => 'NOTIF-CAT',
            'name' => 'Kategori Notifikasi',
            'type' => \App\Enums\PaymentCategoryType::Monthly,
            'default_amount' => 200000,
            'due_day' => 10,
            'is_active' => true,
        ]);
        $bill = Bill::query()->create([
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'bill_number' => 'BILL-NOTIF',
            'title' => 'SPP November',
            'period' => '2026-11',
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 200000,
            'original_amount' => 200000,
            'total_amount' => 200000,
            'status' => 'unpaid',
        ]);

        StudentBill::query()->create([
            'bill_id' => $bill->id,
            'student_id' => $student->id,
            'bill_number' => 'TAG-NOTIF',
            'original_amount' => 200000,
            'final_amount' => 200000,
            'paid_amount' => 0,
            'remaining_amount' => 200000,
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'unpaid',
        ]);

        $this->artisan('reminders:send')->assertSuccessful();

        Queue::assertPushed(SendFeeReminder::class, static fn (SendFeeReminder $job): bool => $job->recipient === '081234567890');
    }

    public function test_reminder_command_sends_one_message_per_student_and_skips_rerun_same_day(): void
    {
        Queue::fake();

        $template = NotificationTemplate::query()->where('channel', 'whatsapp')->firstOrFail();
        $schedule = ReminderSchedule::query()->where('notification_template_id', $template->id)->firstOrFail();
        $schedule->update(['days_before_due' => 3, 'is_active' => true, 'last_run_at' => null]);

        ReminderSchedule::query()->where('id', '!=', $schedule->id)->update(['is_active' => false]);

        $student = $this->makeStudent('081200000001');
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        foreach (['A', 'B', 'C'] as $suffix) {
            $category = PaymentCategory::query()->create([
                'code' => 'NOTIF-'.$suffix,
                'name' => 'Kategori '.$suffix,
                'type' => \App\Enums\PaymentCategoryType::Monthly,
                'default_amount' => 100000,
                'due_day' => 10,
                'is_active' => true,
            ]);

            $bill = Bill::query()->create([
                'payment_category_id' => $category->id,
                'academic_year_id' => $year->id,
                'bill_number' => 'BILL-NOTIF-'.$suffix,
                'title' => 'Tagihan '.$suffix,
                'period' => '2026-11',
                'due_date' => now()->addDays(3)->toDateString(),
                'amount' => 100000,
                'original_amount' => 100000,
                'total_amount' => 100000,
                'status' => 'unpaid',
            ]);

            StudentBill::query()->create([
                'bill_id' => $bill->id,
                'student_id' => $student->id,
                'bill_number' => 'TAG-NOTIF-'.$suffix,
                'original_amount' => 100000,
                'final_amount' => 100000,
                'paid_amount' => 0,
                'remaining_amount' => 100000,
                'due_date' => now()->addDays(3)->toDateString(),
                'status' => 'unpaid',
            ]);
        }

        $this->artisan('reminders:send')->assertSuccessful();

        // Tiga tagihan pada siswa yang sama harus menjadi satu pesan ringkasan.
        Queue::assertPushed(SendFeeReminder::class, 1);

        Queue::assertPushed(SendFeeReminder::class, function (SendFeeReminder $job): bool {
            return ($job->replacements['nominal'] ?? null) === '300.000'
                && str_contains((string) ($job->replacements['tagihan'] ?? ''), 'Tagihan A');
        });

        // Dijalankan ulang di hari yang sama tidak boleh mengirim lagi.
        $this->artisan('reminders:send')->assertSuccessful();
        Queue::assertPushed(SendFeeReminder::class, 1);
    }

    public function test_notification_service_logs_failure_when_whatsapp_gateway_is_missing(): void
    {
        config(['services.whatsapp.endpoint' => '', 'services.whatsapp.token' => '']);

        $template = NotificationTemplate::query()->where('channel', 'whatsapp')->firstOrFail();
        $student = $this->makeStudent('081298765432');

        $log = app(NotificationService::class)->send($template, $student, '081298765432', [
            'nama' => $student->name,
            'tagihan' => 'SPP November',
            'nominal' => '200.000',
            'jatuh_tempo' => '10 November 2026',
            'sekolah' => 'MAN 7 Jombang',
        ]);

        $this->assertSame('failed', $log->status);
        $this->assertStringContainsString('Gateway WhatsApp belum dikonfigurasi', (string) $log->error);
        $this->assertStringContainsString('SPP November', (string) $log->message);
        $this->assertSame(1, NotificationLog::query()->count());
    }

    public function test_notification_page_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('notifications.index'))->assertForbidden();
    }

    public function test_notification_page_is_accessible_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Notifikasi');
    }

    public function test_notification_page_renders_all_tabs(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        \Livewire\Livewire::actingAs($user)
            ->test(\App\Livewire\Tools\NotificationIndex::class)
            ->assertSee('Riwayat Notifikasi')
            ->set('tab', 'templates')
            ->assertSee('Pengingat Tagihan (WhatsApp)')
            ->set('tab', 'schedules')
            ->assertSee('Pengingat H-3 WhatsApp');
    }

    private function makeStudent(string $parentPhone): Student
    {
        $unit = Unit::query()->firstOrFail();

        return Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'NOTIF-'.fake()->unique()->numerify('####'),
            'name' => 'Siswa Notifikasi',
            'gender' => Gender::Male,
            'parent_phone' => $parentPhone,
            'email' => 'wali@example.test',
            'entry_year' => 2026,
            'status' => StudentStatus::Active,
        ]);
    }
}
