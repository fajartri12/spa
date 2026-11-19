<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\StudentSavingsAccount;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SavingsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    private function setupData(): array
    {
        $unit = Unit::query()->create(['code' => 'SAV', 'name' => 'Tabungan']);
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $class = SchoolClass::query()->create([
            'unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas Tabungan',
        ]);
        $student = Student::query()->create([
            'unit_id' => $unit->id,
            'school_class_id' => $class->id,
            'nis' => 'SAV-1',
            'nisn' => 'SAV-1',
            'name' => 'Siswa Tabungan',
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
        $account = StudentSavingsAccount::query()->create([
            'student_id' => $student->id,
            'balance' => 0,
        ]);

        return [$student, $account];
    }

    public function test_deposit_updates_balance_and_creates_ledger(): void
    {
        [$student, $account] = $this->setupData();
        $user = User::query()->where('email', 'bendahara@email.com')->firstOrFail();

        Livewire::actingAs($user)->test(\App\Livewire\Savings\SavingsTransaction::class, ['student' => $student->id])
            ->set('type', 'deposit')
            ->set('amount', 50000)
            ->set('description', 'Simpanan awal')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Setoran tabungan berhasil diproses.');

        $account->refresh();
        $this->assertEquals(50000, $account->balance);
        $this->assertDatabaseHas('student_savings_transactions', [
            'student_savings_account_id' => $account->id,
            'type' => 'deposit',
            'amount' => 50000,
            'balance_after' => 50000,
        ]);
    }

    public function test_modal_deposit_on_savings_index_updates_balance(): void
    {
        [$student, $account] = $this->setupData();
        $user = User::query()->where('email', 'bendahara@email.com')->firstOrFail();

        Livewire::actingAs($user)->test(\App\Livewire\Savings\SavingsIndex::class)
            ->call('openTransaction', $student->id)
            ->assertSet('showTransactionModal', true)
            ->assertSet('student_id', $student->id)
            ->set('type', 'deposit')
            ->set('amount', 75000)
            ->set('description', 'Setoran tabungan via modal')
            ->call('saveTransaction')
            ->assertHasNoErrors()
            ->assertSet('showTransactionModal', false)
            ->assertDispatched('toast', type: 'success', message: 'Setoran tabungan berhasil diproses.');

        $account->refresh();
        $this->assertEquals(75000, $account->balance);
        $this->assertDatabaseHas('student_savings_transactions', [
            'student_savings_account_id' => $account->id,
            'type' => 'deposit',
            'amount' => 75000,
            'balance_after' => 75000,
        ]);
    }

    public function test_modal_withdrawal_fails_when_insufficient_balance(): void
    {
        [$student, $account] = $this->setupData();
        $user = User::query()->where('email', 'bendahara@email.com')->firstOrFail();

        Livewire::actingAs($user)->test(\App\Livewire\Savings\SavingsIndex::class)
            ->call('openTransaction', $student->id)
            ->set('type', 'withdrawal')
            ->set('amount', 5000)
            ->call('saveTransaction')
            ->assertHasErrors(['amount']);

        $account->refresh();
        $this->assertEquals(0, $account->balance);
    }

    public function test_filter_by_class_shows_only_students_in_that_class(): void
    {
        [$student1, $account1] = $this->setupData();
        $user = User::query()->where('email', 'bendahara@email.com')->firstOrFail();

        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();
        $otherClass = SchoolClass::query()->create([
            'unit_id' => $student1->unit_id,
            'academic_year_id' => $year->id,
            'name' => 'Kelas Lain',
        ]);
        $student2 = Student::query()->create([
            'unit_id' => $student1->unit_id,
            'school_class_id' => $otherClass->id,
            'nis' => 'SAV-2',
            'nisn' => 'SAV-2',
            'name' => 'Siswa Kelas Lain',
            'gender' => Gender::Female,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);

        Livewire::actingAs($user)->test(\App\Livewire\Savings\SavingsIndex::class)
            ->assertSee($student1->name)
            ->assertSee($student2->name)
            ->set('classFilter', (string) $student1->school_class_id)
            ->assertSee($student1->name)
            ->assertDontSee($student2->name)
            ->call('resetFilters')
            ->assertSet('classFilter', '')
            ->assertSee($student1->name)
            ->assertSee($student2->name);
    }
}

