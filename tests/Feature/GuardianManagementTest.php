<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Livewire\Master\GuardianIndex;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GuardianManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_page_requires_permission(): void
    {
        $user = User::query()->create([
            'name' => 'Tanpa Izin',
            'email' => 'noper@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/data-master/orang-tua')->assertForbidden();
    }

    public function test_authorized_user_can_view_page(): void
    {
        $this->actingAs($this->superadmin())
            ->get('/data-master/orang-tua')
            ->assertOk()
            ->assertSee('Orang Tua / Wali');
    }

    public function test_can_create_guardian_and_link_students(): void
    {
        $student = $this->createStudent('2026201', 'Anak Taut');

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('create')
            ->set('name', 'Ibu Siti')
            ->set('relation', 'mother')
            ->set('phone', '081277778888')
            ->set('email', 'siti@example.com')
            ->set('selectedStudents', [$student->id])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $guardian = Guardian::query()->where('phone', '081277778888')->firstOrFail();

        $this->assertSame('Ibu Siti', $guardian->name);
        $this->assertTrue($guardian->students()->whereKey($student->id)->exists());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        Guardian::query()->create([
            'name' => 'Wali Lama',
            'relation' => 'father',
            'phone' => '081255556666',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('create')
            ->set('name', 'Wali Baru')
            ->set('relation', 'father')
            ->set('phone', '081255556666')
            ->call('save')
            ->assertHasErrors(['phone']);
    }

    public function test_one_account_can_hold_several_children(): void
    {
        $first = $this->createStudent('2026202', 'Kakak');
        $second = $this->createStudent('2026203', 'Adik');

        $guardian = Guardian::query()->create([
            'name' => 'Bapak Dua Anak',
            'relation' => 'father',
            'phone' => '081244443333',
            'is_active' => true,
        ]);

        app(GuardianService::class)->syncStudents($guardian, [$first->id, $second->id]);

        $this->assertSame(2, $guardian->fresh()->activeStudents()->count());

        // Wali utama ditentukan per siswa: karena hanya ada satu wali,
        // keduanya otomatis menjadi wali utama masing-masing anak.
        $this->assertTrue(
            $guardian->fresh()->students()->whereKey($first->id)->first()->pivot->is_primary,
        );
        $this->assertTrue(
            $guardian->fresh()->students()->whereKey($second->id)->first()->pivot->is_primary,
        );
    }

    public function test_second_guardian_for_same_student_is_not_primary(): void
    {
        $student = $this->createStudent('2026208', 'Anak Dua Wali');

        $father = Guardian::query()->create([
            'name' => 'Ayah Kandung',
            'relation' => 'father',
            'phone' => '081200010001',
            'is_active' => true,
        ]);

        $mother = Guardian::query()->create([
            'name' => 'Ibu Kandung',
            'relation' => 'mother',
            'phone' => '081200010002',
            'is_active' => true,
        ]);

        app(GuardianService::class)->syncStudents($father, [$student->id]);
        app(GuardianService::class)->syncStudents($mother, [$student->id]);

        $pivots = $student->fresh()->guardians()->get()->mapWithKeys(
            fn (Guardian $guardian): array => [$guardian->id => (bool) $guardian->pivot->is_primary],
        );

        $this->assertTrue($pivots[$father->id]);
        $this->assertFalse($pivots[$mother->id]);
    }

    public function test_can_update_guardian(): void
    {
        $guardian = Guardian::query()->create([
            'name' => 'Nama Lama',
            'relation' => 'father',
            'phone' => '081299990000',
            'is_active' => true,
        ]);

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('edit', $guardian->id)
            ->set('name', 'Nama Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nama Baru', $guardian->fresh()->name);
    }

    public function test_issuing_password_stores_hash_and_returns_plain_once(): void
    {
        $guardian = Guardian::query()->create([
            'name' => 'Wali Password',
            'relation' => 'mother',
            'phone' => '081211112222',
            'is_active' => true,
        ]);

        $component = Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('issuePassword', $guardian->id);

        $plain = $component->get('generatedPassword');

        $this->assertIsString($plain);
        $this->assertNotEmpty($plain);
        $this->assertTrue($guardian->fresh()->hasPassword());
        $this->assertNotSame($plain, $guardian->fresh()->password);
        $this->assertNotNull($guardian->fresh()->password_set_at);
        $this->assertDatabaseHas('activity_logs', ['event' => 'guardians.password-issued']);
    }

    public function test_can_delete_guardian_and_detach_students(): void
    {
        $student = $this->createStudent('2026204', 'Anak Lepas');

        $guardian = Guardian::query()->create([
            'name' => 'Wali Hapus',
            'relation' => 'guardian',
            'phone' => '081233334444',
            'is_active' => true,
        ]);

        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('confirmDelete', $guardian->id)
            ->call('delete')
            ->assertDispatched('toast');

        $this->assertSoftDeleted('guardians', ['id' => $guardian->id]);
        $this->assertDatabaseMissing('guardian_student', ['guardian_id' => $guardian->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_search_matches_child_name(): void
    {
        $student = $this->createStudent('2026205', 'Cari Anak Unik');

        $guardian = Guardian::query()->create([
            'name' => 'Wali Biasa',
            'relation' => 'father',
            'phone' => '081266667777',
            'is_active' => true,
        ]);

        app(GuardianService::class)->syncStudents($guardian, [$student->id]);

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->set('search', 'Cari Anak Unik')
            ->assertSee('Wali Biasa');
    }

    public function test_export_streams_filtered_rows(): void
    {
        Guardian::query()->create([
            'name' => 'Wali Ekspor',
            'relation' => 'mother',
            'phone' => '081288889999',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin())
            ->get('/data-master/orang-tua/export');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Wali Ekspor', $content);
        $this->assertStringNotContainsString('password', $content);
    }

    public function test_export_requires_permission(): void
    {
        $user = User::query()->create([
            'name' => 'Tanpa Izin',
            'email' => 'noper2@example.com',
            'password' => 'password',
            'status' => 'active',
        ]);

        $this->actingAs($user)->get('/data-master/orang-tua/export')->assertForbidden();
    }

    public function test_guardian_permissions_are_registered(): void
    {
        foreach (['view', 'create', 'update', 'delete', 'export'] as $action) {
            $this->assertDatabaseHas('permissions', ['name' => "guardians.{$action}"]);
        }
    }

    public function test_import_from_student_creates_and_links_guardian(): void
    {
        $student = $this->createStudent('2026206', 'Anak Impor');
        $student->forceFill([
            'parent_name' => 'Bapak Impor',
            'parent_phone' => '081200002222',
            'parent_relation' => 'Ayah',
        ])->save();

        $guardian = app(GuardianService::class)->importFromStudent($student->fresh());

        $this->assertNotNull($guardian);
        $this->assertSame('Bapak Impor', $guardian->name);
        $this->assertSame('father', $guardian->relation->value);
        $this->assertTrue($guardian->students()->whereKey($student->id)->exists());
    }

    public function test_import_from_student_returns_null_without_parent_data(): void
    {
        $student = $this->createStudent('2026207', 'Anak Tanpa Data');

        $this->assertNull(app(GuardianService::class)->importFromStudent($student));
    }

    public function test_sync_from_students_creates_guardians_from_parent_data(): void
    {
        $student = $this->createStudent('2026209', 'Anak Sinkron');
        $student->forceFill([
            'parent_name' => 'Ibu Sinkron',
            'parent_phone' => '081200003333',
            'parent_relation' => 'Ibu',
        ])->save();

        Livewire::actingAs($this->superadmin())
            ->test(GuardianIndex::class)
            ->call('syncFromStudents')
            ->assertDispatched('toast');

        $guardian = Guardian::query()->where('phone', '081200003333')->firstOrFail();

        $this->assertSame('Ibu Sinkron', $guardian->name);
        $this->assertSame('mother', $guardian->relation->value);
        $this->assertTrue($guardian->students()->whereKey($student->id)->exists());
        $this->assertDatabaseHas('activity_logs', ['event' => 'guardians.synced']);
    }

    private function superadmin(): User
    {
        return User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    private function createStudent(string $nis, string $name): Student
    {
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'TEST'],
            ['name' => 'Test Unit', 'level' => 'Test', 'sort_order' => 99, 'is_active' => true],
        );

        return Student::query()->create([
            'unit_id' => $unit->id,
            'nis' => $nis,
            'name' => $name,
            'gender' => Gender::Male,
            'status' => StudentStatus::Active,
            'entry_year' => 2026,
        ]);
    }
}
