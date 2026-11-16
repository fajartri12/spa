<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_statistics_provide_master_data_counts_for_dashboard_cards(): void
    {
        $service = app(DashboardService::class);
        $before = $service->statistics();

        foreach (['units', 'classes', 'years', 'students', 'staff', 'students_account'] as $key) {
            $this->assertIsInt($before[$key], "statistics[{$key}] harus berupa integer");
        }

        // Akun siswa dihitung dari siswa yang sudah memiliki kata sandi portal,
        // bukan angka nol statis seperti sebelumnya.
        $this->assertSame(0, $before['students_account']);

        User::factory()->create(['status' => 'inactive']);

        $withoutRole = User::factory()->create(['status' => 'active']);
        $this->assertSame($before['staff'], $service->statistics()['staff']);

        $withoutRole->assignRole(RoleName::Verifier->value);
        $this->assertSame($before['staff'] + 1, $service->statistics()['staff']);
    }

    public function test_students_account_counts_students_having_portal_password(): void
    {
        $service = app(DashboardService::class);
        $unit = Unit::query()->firstOrFail();

        $student = Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'DSH-001',
            'name' => 'Siswa Tanpa Portal',
            'gender' => 'male',
            'status' => 'active',
        ]);

        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'DSH-002',
            'name' => 'Siswa Berportal',
            'gender' => 'female',
            'status' => 'active',
            'portal_password' => 'rahasia-123',
            'portal_password_set_at' => now(),
        ]);

        $this->assertSame(1, $service->statistics()['students_account']);
        $this->assertSame(2, $service->statistics()['students']);

        $student->update(['portal_password' => 'rahasia-456', 'portal_password_set_at' => now()]);

        $this->assertSame(2, $service->statistics()['students_account']);
    }

    public function test_statistics_respect_unit_filter(): void
    {
        $service = app(DashboardService::class);

        $unit = Unit::query()->firstOrFail();
        $otherUnit = Unit::query()->whereKeyNot($unit->id)->firstOrFail();

        foreach (range(1, 2) as $index) {
            Student::query()->create([
                'unit_id' => $unit->id,
                'nis' => 'UNIT-A-'.$index,
                'name' => 'Siswa Unit A '.$index,
                'gender' => 'male',
                'status' => 'active',
            ]);
        }

        foreach (range(1, 3) as $index) {
            Student::query()->create([
                'unit_id' => $otherUnit->id,
                'nis' => 'UNIT-B-'.$index,
                'name' => 'Siswa Unit B '.$index,
                'gender' => 'female',
                'status' => 'active',
            ]);
        }

        $this->assertSame(2, $service->statistics(null, $unit->id)['students']);
        $this->assertSame(3, $service->statistics(null, $otherUnit->id)['students']);
        $this->assertSame(5, $service->statistics()['students']);
    }

    public function test_monthly_payments_are_grouped_by_selected_range(): void
    {
        $service = app(DashboardService::class);

        $monthly = $service->monthlyPayments(
            null,
            null,
            Carbon::parse('2026-01-01'),
            Carbon::parse('2026-03-31'),
        );

        $this->assertCount(3, $monthly['labels']);
        $this->assertCount(3, $monthly['values']);
        $this->assertSame(['Jan', 'Feb', 'Mar'], $monthly['labels']);
    }
}
