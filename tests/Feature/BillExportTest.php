<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Bill;
use App\Models\PaymentCategory;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\BillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_export_streams_csv_and_requires_permission(): void
    {
        $unit = Unit::query()->create(['code' => 'T', 'name' => 'Unit T']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $category = PaymentCategory::query()->create([
            'code' => 'P', 'name' => 'SPP', 'type' => \App\Enums\PaymentCategoryType::Monthly,
            'default_amount' => 100000, 'due_day' => 10, 'is_active' => true,
        ]);
        $student = Student::query()->create([
            'unit_id' => $unit->id, 'nis' => '001', 'nisn' => '001', 'name' => 'Budi',
            'gender' => \App\Enums\Gender::Male, 'status' => \App\Enums\StudentStatus::Active, 'entry_year' => 2026,
        ]);

        $studentBill = app(BillingService::class)->create([
            'student_id' => $student->id, 'payment_category_id' => $category->id,
            'academic_year_id' => $year->id, 'period' => '2026-11', 'amount' => 100000,
        ]);

        $superAdmin = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
        $response = $this->actingAs($superAdmin)->get(route('bills.export'))
            ->assertStatus(200);

        $csv = $response->streamedContent();
        $this->assertStringContainsString('bill_number', $csv);
        $this->assertStringContainsString($studentBill->bill_number, $csv);
        $this->assertStringContainsString('Budi', $csv);

        $outsider = User::query()->create([
            'name' => 'Outsider', 'email' => 'outsider@example.test',
            'password' => bcrypt('secret'), 'status' => \App\Enums\UserStatus::Active,
        ]);
        $this->actingAs($outsider)->get(route('bills.export'))->assertStatus(403);
    }
}
