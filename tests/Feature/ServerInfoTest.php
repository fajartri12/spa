<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServerInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_server_info_is_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::PaymentOfficer->value);

        $this->actingAs($user)->get(route('server-info.index'))->assertForbidden();
    }

    public function test_server_info_shows_runtime_details_for_super_admin(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $this->actingAs($user)
            ->get(route('server-info.index'))
            ->assertOk()
            ->assertSee('Versi PHP')
            ->assertSee(PHP_VERSION);
    }

    public function test_import_skips_rows_with_unknown_unit(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleName::SuperAdministrator->value);

        $unit = Unit::query()->firstOrFail();
        Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => 'DUP-0001',
            'name' => 'Siswa Duplikat',
            'gender' => Gender::Male,
            'entry_year' => 2026,
            'status' => StudentStatus::Active,
        ]);

        $csv = implode("\n", [
            'unit_code,nis,nisn,name,gender,birth_place,birth_date,religion,address,phone,email,parent_name,parent_phone,parent_relation,entry_year,status',
            $unit->code.',IMPORT-0001,,Siswa Baru,male,Jombang,2010-01-01,Islam,,,,'.',,,,,2026,active',
            $unit->code.',DUP-0001,,Siswa Duplikat,male,Jombang,2010-01-01,Islam,,,,'.',,,,,2026,active',
            'UNKNOWN,IMPORT-0002,,Unit Salah,male,Jombang,2010-01-01,Islam,,,,'.',,,,,2026,active',
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent('siswa.csv', $csv);

        $this->actingAs($user)
            ->post(route('students.import'), ['file' => $file])
            ->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', ['nis' => 'IMPORT-0001']);
        $this->assertDatabaseMissing('students', ['nis' => 'IMPORT-0002']);
        $this->assertSame(1, Student::query()->where('nis', 'DUP-0001')->count());
    }
}
