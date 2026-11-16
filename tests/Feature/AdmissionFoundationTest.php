<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_records_and_secure_document_metadata_are_persisted(): void
    {
        $period = AdmissionPeriod::create([
            'name' => 'PPDB 2027/2028', 'slug' => 'ppdb-2027-2028',
            'starts_at' => '2027-01-01', 'ends_at' => '2027-03-31',
        ]);
        $applicant = Applicant::create([
            'admission_period_id' => $period->id, 'registration_number' => 'REG-0001', 'name' => 'Calon Siswa',
        ]);
        $document = ApplicantDocument::create([
            'applicant_id' => $applicant->id, 'document_type' => 'birth_certificate',
            'original_name' => 'akta.pdf', 'storage_path' => 'applicants/1/akta.pdf',
            'disk' => 'private', 'mime_type' => 'application/pdf', 'size' => 2048,
        ]);

        $this->assertTrue($period->applicants->contains($applicant));
        $this->assertSame('private', $document->fresh()->disk);
        $this->assertSame(2048, $document->fresh()->size);
    }

    public function test_document_type_is_unique_per_applicant(): void
    {
        $period = AdmissionPeriod::create([
            'name' => 'PPDB', 'slug' => 'ppdb-test', 'starts_at' => '2027-01-01', 'ends_at' => '2027-03-31',
        ]);
        $applicant = Applicant::create(['admission_period_id' => $period->id, 'registration_number' => 'REG-0002', 'name' => 'Siswa']);
        $data = ['applicant_id' => $applicant->id, 'document_type' => 'family_card', 'original_name' => 'kk.pdf', 'storage_path' => 'private/kk.pdf', 'disk' => 'private', 'mime_type' => 'application/pdf', 'size' => 1];
        ApplicantDocument::create($data);

        $this->expectException(\Illuminate\Database\QueryException::class);
        ApplicantDocument::create($data);
    }
}
