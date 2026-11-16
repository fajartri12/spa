<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\Admissions\ApplicantShow;
use App\Models\AdmissionBill;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ApplicantDetailTest extends TestCase
{
    use RefreshDatabase;

    private Applicant $applicant;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Storage::fake('local');

        $period = AdmissionPeriod::query()->create([
            'name' => 'PPDB 2027', 'slug' => 'ppdb-2027',
            'starts_at' => '2027-01-01', 'ends_at' => '2027-03-31',
        ]);

        $this->applicant = Applicant::query()->create([
            'admission_period_id' => $period->id,
            'registration_number' => 'PPDB-2027-00001',
            'name' => 'Calon Siswa',
            'status' => 'registered',
        ]);

        $this->superadmin = User::query()->where('email', 'superadmin@email.com')->firstOrFail();
    }

    public function test_document_upload_is_stored_on_private_disk(): void
    {
        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->set('document_type', 'family_card')
            ->set('document_file', UploadedFile::fake()->create('kk.pdf', 100, 'application/pdf'))
            ->call('uploadDocument')
            ->assertHasNoErrors()
            ->assertDispatched('toast', type: 'success', message: 'Dokumen berhasil diunggah.');

        $document = ApplicantDocument::query()->where('applicant_id', $this->applicant->id)->firstOrFail();

        $this->assertSame('private', $document->disk);
        $this->assertSame('pending', $document->status);
        Storage::disk('local')->assertExists($document->storage_path);
    }

    public function test_replacing_document_of_same_type_keeps_single_row(): void
    {
        foreach (['satu.pdf', 'dua.pdf'] as $name) {
            Livewire::actingAs($this->superadmin)
                ->test(ApplicantShow::class, ['applicant' => $this->applicant])
                ->set('document_type', 'photo')
                ->set('document_file', UploadedFile::fake()->create($name, 50, 'application/pdf'))
                ->call('uploadDocument')
                ->assertHasNoErrors();
        }

        $documents = ApplicantDocument::query()->where('applicant_id', $this->applicant->id)->get();

        $this->assertCount(1, $documents);
        $this->assertSame('dua.pdf', $documents->first()->original_name);
        Storage::disk('local')->assertExists($documents->first()->storage_path);
    }

    public function test_document_can_be_verified_then_deleted(): void
    {
        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->set('document_type', 'birth_certificate')
            ->set('document_file', UploadedFile::fake()->create('akta.pdf', 50, 'application/pdf'))
            ->call('uploadDocument');

        $document = ApplicantDocument::query()->firstOrFail();

        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('verifyDocument', $document->id, 'verified')
            ->assertDispatched('toast', type: 'success', message: 'Status dokumen diubah menjadi verified.');

        $this->assertSame('verified', $document->fresh()->status);

        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('deleteDocument', $document->id);

        $this->assertDatabaseMissing('applicant_documents', ['id' => $document->id]);
    }

    public function test_document_download_requires_permission(): void
    {
        $document = ApplicantDocument::query()->create([
            'applicant_id' => $this->applicant->id,
            'document_type' => 'other',
            'original_name' => 'berkas.pdf',
            'storage_path' => 'applicant-documents/berkas.pdf',
            'disk' => 'private',
            'mime_type' => 'application/pdf',
            'size' => 10,
            'status' => 'pending',
        ]);
        Storage::disk('local')->put($document->storage_path, 'isi');

        $this->actingAs($this->superadmin)
            ->get(route('applicant-documents.download', $document))
            ->assertOk();

        $this->actingAs(User::factory()->create())
            ->get(route('applicant-documents.download', $document))
            ->assertForbidden();
    }

    public function test_admission_bill_can_be_created_and_paid(): void
    {
        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->set('bill_title', 'Biaya Pendaftaran')
            ->set('bill_amount', '150000')
            ->call('createBill')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        $bill = AdmissionBill::query()->where('applicant_id', $this->applicant->id)->firstOrFail();

        $this->assertSame('unpaid', $bill->status);
        $this->assertEquals(150000, (float) $bill->amount);

        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->call('updateBillStatus', $bill->id, 'paid');

        $this->assertSame('paid', $bill->fresh()->status);
    }

    public function test_applicant_status_can_be_updated(): void
    {
        Livewire::actingAs($this->superadmin)
            ->test(ApplicantShow::class, ['applicant' => $this->applicant])
            ->set('applicant_status', 'passed')
            ->call('updateStatus')
            ->assertHasNoErrors();

        $this->assertSame('passed', $this->applicant->fresh()->status);
    }

    public function test_user_without_permission_cannot_open_applicant_detail(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admissions.show', $this->applicant))
            ->assertForbidden();
    }
}
