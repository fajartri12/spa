<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentCsvTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'unit_code,nis,nisn,name,gender,birth_place,birth_date,religion,address,phone,email,parent_name,parent_phone,parent_relation,entry_year,status';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo($permissions);

        return $user;
    }

    private function csv(string ...$rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('siswa.csv', implode("\n", [self::HEADER, ...$rows]));
    }

    public function test_export_streams_csv_with_header(): void
    {
        $unit = Unit::query()->firstOrFail();
        Student::query()->create(['unit_id' => $unit->id, 'nis' => 'CSV-EXPORT-1', 'name' => 'Siswa Ekspor', 'gender' => 'male', 'status' => 'active']);

        $response = $this->actingAs($this->userWith('students.export'))->get(route('students.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringStartsWith(self::HEADER, $content);
        $this->assertStringContainsString('CSV-EXPORT-1', $content);
    }

    public function test_import_creates_students_and_skips_duplicate_nis(): void
    {
        $unit = Unit::query()->firstOrFail();
        Student::query()->create(['unit_id' => $unit->id, 'nis' => 'CSV-DUP', 'name' => 'Siswa Lama', 'gender' => 'male', 'status' => 'active']);

        $file = $this->csv(
            "{$unit->code},CSV-NEW-1,,Siswa Baru Satu,female,Jombang,2010-05-12,Islam,Jl. Baru,0812,a@example.com,Ayah,0813,Ayah,2026,active",
            "{$unit->code},CSV-DUP,,Siswa Duplikat,male,,,Islam,,,,,,,2026,active",
        );

        $response = $this->actingAs($this->userWith('students.import'))->post(route('students.import'), ['file' => $file]);

        $response->assertRedirect(route('students.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('students', ['nis' => 'CSV-NEW-1', 'name' => 'Siswa Baru Satu', 'unit_id' => $unit->id]);
        $this->assertSame(1, Student::query()->where('nis', 'CSV-DUP')->count());
    }

    public function test_import_reports_unknown_unit_code(): void
    {
        $file = $this->csv('UNKNOWN,CSV-BAD-UNIT,,Siswa Unit Salah,male,,,Islam,,,,,,,2026,active');

        $response = $this->actingAs($this->userWith('students.import'))->post(route('students.import'), ['file' => $file]);

        $response->assertRedirect(route('students.index'))->assertSessionHas('import_errors');
        $this->assertStringContainsString('UNKNOWN', session('import_errors')[0]);
        $this->assertDatabaseMissing('students', ['nis' => 'CSV-BAD-UNIT']);
    }

    public function test_import_rejects_wrong_header_order(): void
    {
        $file = UploadedFile::fake()->createWithContent('siswa.csv', "nis,unit_code,name,male\n1,MAN7,Salah Urutan,male");

        $this->actingAs($this->userWith('students.import'))
            ->post(route('students.import'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_import_and_export_require_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('students.export'))->assertForbidden();
        $this->actingAs($user)->post(route('students.import'), ['file' => $this->csv()])->assertForbidden();
    }
}
