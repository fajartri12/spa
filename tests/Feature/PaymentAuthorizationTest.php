<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_payment_pages_require_view_permission(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('payments.index'))
            ->assertForbidden();
    }

    public function test_payment_create_actions_require_their_permissions(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('payments.create'))
            ->assertForbidden();
    }

    public function test_payment_create_filters_students_by_class(): void
    {
        $admin = User::firstWhere('email', 'superadmin@email.com');
        $unit = \App\Models\Unit::query()->first();
        $year = \App\Models\AcademicYear::query()->first();

        $classes = collect(['X-1', 'X-2'])->map(fn ($name) => \App\Models\SchoolClass::create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => $name,
            'level' => 'X',
            'is_active' => true,
        ]));

        $students = $classes->map(fn ($class) => \App\Models\Student::create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'T' . $class->id,
            'name' => 'Siswa Kelas ' . $class->name,
            'status' => 'active',
        ]));

        \Livewire\Livewire::actingAs($admin)
            ->test(\App\Livewire\Payments\PaymentCreate::class)
            ->assertSee('Total Tunggakan')
            ->assertSee('Total Pembayaran')
            ->assertSee('Pembayaran Tunai')
            ->assertSee('Pembayaran Non Tunai')
            ->assertSee($students[0]->name)
            ->assertSee($students[1]->name)
            ->set('classFilter', (string) $classes[0]->id)
            ->assertSee($students[0]->name)
            ->assertDontSee($students[1]->name);
    }
}
