<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\PaymentCategoryType;
use App\Enums\PaymentStatus;
use App\Enums\StudentStatus;
use App\Livewire\Public\GuardianPortal;
use App\Livewire\Public\StudentPortal;
use App\Models\AcademicYear;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\PaymentCategory;
use App\Models\PaymentProof;
use App\Models\Student;
use App\Models\StudentBill;
use App\Models\Unit;
use App\Services\BillingService;
use App\Services\GuardianService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Unggah bukti transfer manual dari portal untuk diverifikasi admin.
 */
class ManualTransferProofTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');
    }

    public function test_guardian_can_upload_transfer_proof_creating_pending_payment(): void
    {
        $guardian = $this->makeGuardian('081211112222');
        $student = $this->makeStudent('2026801', 'Anak Bukti');
        app(GuardianService::class)->syncStudents($guardian, [$student->id]);
        $bill = $this->makeBill($student);

        $this->withSession(['public_guardian_id' => $guardian->id])->get('/portal/orang-tua');

        Livewire::withQueryParams(['anak' => $student->id])
            ->test(GuardianPortal::class)
            ->set('selectedStudentId', (string) $student->id)
            ->call('openProofModal', $bill->id)
            ->assertSet('showProofModal', true)
            ->assertSet('proofAmount', '300000')
            ->set('proofAmount', '300000')
            ->set('proofNote', 'Transfer BCA')
            ->set('proofFile', UploadedFile::fake()->create('bukti.pdf', 120, 'application/pdf'))
            ->call('submitProof')
            ->assertHasNoErrors()
            ->assertSet('showProofModal', false);

        $payment = Payment::query()->latest('id')->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertEquals(300000.0, (float) $payment->amount);

        $proof = PaymentProof::query()->where('payment_id', $payment->id)->firstOrFail();
        Storage::disk('local')->assertExists($proof->file_path);

        // Sisa tagihan belum berkurang sebelum disetujui.
        $this->assertEquals(300000.0, (float) $bill->fresh()->remaining_amount);
    }

    public function test_student_can_upload_partial_transfer_proof(): void
    {
        $student = $this->makeStudent('2026802', 'Siswa Bukti');
        $bill = $this->makeBill($student);

        $this->withSession(['public_student_id' => $student->id]);

        Livewire::test(StudentPortal::class)
            ->call('openProofModal', $bill->id)
            ->set('proofAmount', '150000')
            ->set('proofFile', UploadedFile::fake()->image('bukti.jpg'))
            ->call('submitProof')
            ->assertHasNoErrors();

        $this->assertEquals(150000.0, (float) Payment::query()->latest('id')->value('amount'));
        $this->assertSame(1, PaymentProof::query()->count());
    }

    public function test_cannot_upload_second_proof_while_pending(): void
    {
        $student = $this->makeStudent('2026803', 'Siswa Ganda');
        $bill = $this->makeBill($student);

        $this->withSession(['public_student_id' => $student->id]);

        foreach (['bukti-1.pdf', 'bukti-2.pdf'] as $name) {
            Livewire::test(StudentPortal::class)
                ->call('openProofModal', $bill->id)
                ->set('proofAmount', '100000')
                ->set('proofFile', UploadedFile::fake()->create($name, 60, 'application/pdf'))
                ->call('submitProof');
        }

        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, PaymentProof::query()->count());
    }

    public function test_cannot_upload_proof_exceeding_remaining_amount(): void
    {
        $student = $this->makeStudent('2026804', 'Siswa Berlebih');
        $bill = $this->makeBill($student, 100000.0);

        $this->withSession(['public_student_id' => $student->id]);

        Livewire::test(StudentPortal::class)
            ->call('openProofModal', $bill->id)
            ->set('proofAmount', '250000')
            ->set('proofFile', UploadedFile::fake()->create('bukti.pdf', 60, 'application/pdf'))
            ->call('submitProof')
            ->assertHasErrors(['proofFile']);

        $this->assertSame(0, Payment::query()->count());
    }

    public function test_guardian_cannot_upload_proof_for_other_student_bill(): void
    {
        $guardian = $this->makeGuardian('081233334444');
        $own = $this->makeStudent('2026805', 'Anak Milik');
        $other = $this->makeStudent('2026806', 'Anak Orang');
        app(GuardianService::class)->syncStudents($guardian, [$own->id]);

        $otherBill = $this->makeBill($other, 100000.0, '2026-10');

        $this->withSession(['public_guardian_id' => $guardian->id]);

        Livewire::test(GuardianPortal::class)
            ->call('openProofModal', $otherBill->id)
            ->assertStatus(403);
    }

    public function test_rejects_invalid_file_type(): void
    {
        $student = $this->makeStudent('2026807', 'Siswa Jenis Berkas');
        $bill = $this->makeBill($student);

        $this->withSession(['public_student_id' => $student->id]);

        Livewire::test(StudentPortal::class)
            ->call('openProofModal', $bill->id)
            ->set('proofAmount', '100000')
            ->set('proofFile', UploadedFile::fake()->create('bukti.exe', 10, 'application/octet-stream'))
            ->call('submitProof')
            ->assertHasErrors(['proofFile']);

        $this->assertSame(0, Payment::query()->count());
    }

    private function makeBill(Student $student, float $amount = 300000.0, string $period = '2026-11'): StudentBill
    {
        $year = AcademicYear::query()->where('is_active', true)->firstOrFail();

        $category = PaymentCategory::query()->firstOrCreate(
            ['code' => 'TRF-'.$period],
            [
                'name' => 'Tagihan Transfer',
                'type' => PaymentCategoryType::Monthly,
                'default_amount' => $amount,
                'due_day' => 10,
                'is_active' => true,
            ],
        );

        return app(BillingService::class)->create([
            'student_id' => $student->id,
            'payment_category_id' => $category->id,
            'academic_year_id' => $year->id,
            'period' => $period,
            'amount' => $amount,
        ]);
    }

    private function makeGuardian(string $phone): Guardian
    {
        $guardian = Guardian::query()->create([
            'name' => 'Bapak Bukti',
            'relation' => 'father',
            'phone' => $phone,
            'is_active' => true,
        ]);
        $guardian->forceFill(['password' => 'wali-password', 'password_set_at' => now()])->save();

        return $guardian;
    }

    private function makeStudent(string $nis, string $name): Student
    {
        $unit = Unit::query()->firstOrCreate(
            ['code' => 'TMT'],
            ['name' => 'Unit Transfer', 'level' => 'Test', 'sort_order' => 92, 'is_active' => true],
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