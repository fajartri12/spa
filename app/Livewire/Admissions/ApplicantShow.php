<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionBill;
use App\Models\Applicant;
use App\Models\ApplicantDocument;
use App\Models\SchoolClass;
use App\Models\Unit;
use App\Services\ActivityLogger;
use App\Services\AdmissionPaymentService;
use App\Services\ApplicantConversionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;

#[Layout('layouts.app')]
#[Title('Detail Calon Siswa')]
class ApplicantShow extends Component
{
    use WithFileUploads;

    public Applicant $applicant;

    // Upload dokumen
    public string $document_type = 'birth_certificate';

    /** @var mixed */
    public $document_file = null;

    // Tagihan PPDB
    public string $bill_title = '';

    public string $bill_amount = '';

    public string $bill_due_date = '';

    // Status calon siswa
    public string $applicant_status = 'registered';

    // Konversi menjadi siswa
    public bool $showConvertModal = false;

    public ?int $convert_unit_id = null;

    public ?int $convert_class_id = null;

    public string $convert_nis = '';

    public string $convert_entry_year = '';

    public function mount(Applicant $applicant): void
    {
        $this->authorize('admissions.view');
        $this->applicant = $applicant->load(['admissionPeriod', 'documents', 'bills', 'student']);
        $this->applicant_status = (string) $applicant->status;
        $this->bill_due_date = now()->addMonth()->toDateString();
    }

    /**
     * Buka dialog konversi. Unit/kelas default mengikuti tahun ajaran aktif.
     */
    public function openConvert(): void
    {
        $this->authorize('admissions.update');

        if ($this->applicant->isConverted()) {
            $this->dispatch('toast', type: 'error', message: 'Pendaftar ini sudah menjadi siswa.');

            return;
        }

        $this->reset(['convert_class_id', 'convert_nis']);
        $this->convert_unit_id = $this->convert_unit_id ?? (\App\Models\Unit::query()->orderBy('code')->value('id'));
        $this->convert_entry_year = (string) now()->year;
        $this->resetErrorBag();
        $this->showConvertModal = true;
    }

    public function updatedConvertUnitId(): void
    {
        $this->convert_class_id = null;
    }

    public function convert(ApplicantConversionService $service): void
    {
        $this->authorize('admissions.update');

        $data = $this->validate([
            'convert_unit_id' => ['required', 'integer', 'exists:units,id'],
            'convert_class_id' => ['nullable', 'integer', 'exists:school_classes,id'],
            'convert_nis' => ['required', 'string', 'max:30', Rule::unique('students', 'nis')],
            'convert_entry_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ], [
            'convert_nis.unique' => 'NIS sudah dipakai siswa lain.',
            'convert_unit_id.required' => 'Unit pendidikan wajib dipilih.',
        ]);

        try {
            $student = $service->convert($this->applicant, [
                'unit_id' => (int) $data['convert_unit_id'],
                'school_class_id' => $data['convert_class_id'] !== null ? (int) $data['convert_class_id'] : null,
                'nis' => $data['convert_nis'],
                'entry_year' => (int) $data['convert_entry_year'],
            ], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->addError('convert_nis', $exception->getMessage());

            return;
        }

        $this->showConvertModal = false;
        $this->applicant->refresh()->load('student');
        $this->dispatch('toast', type: 'success', message: "Pendaftar dikonversi menjadi siswa dengan NIS {$student->nis}.");
    }

    public function updateStatus(ActivityLogger $logger): void
    {
        $this->authorize('admissions.update');

        $data = $this->validate([
            'applicant_status' => ['required', Rule::in(['registered', 'verified', 'passed', 'rejected', 'withdrawn'])],
        ]);

        $old = $this->applicant->status;
        $this->applicant->update(['status' => $data['applicant_status']]);
        $logger->log('admissions.applicant_status_changed', "Mengubah status calon siswa {$this->applicant->name} dari {$old} ke {$data['applicant_status']}", $this->applicant);

        $this->applicant->refresh();
        $this->dispatch('toast', type: 'success', message: 'Status calon siswa berhasil diperbarui.');
    }

    public function uploadDocument(ActivityLogger $logger): void
    {
        $this->authorize('admissions.create');

        $this->validate([
            'document_type' => ['required', Rule::in(['birth_certificate', 'family_card', 'graduation_certificate', 'report_card', 'photo', 'other'])],
            'document_file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $file = $this->document_file;
        $existing = ApplicantDocument::query()
            ->where('applicant_id', $this->applicant->getKey())
            ->where('document_type', $this->document_type)
            ->first();

        if ($existing !== null) {
            Storage::disk('local')->delete($existing->storage_path);
            $existing->delete();
        }

        $path = $file->store("applicant-documents/{$this->applicant->getKey()}", 'local');

        $doc = $this->applicant->documents()->create([
            'document_type' => $this->document_type,
            'original_name' => $file->getClientOriginalName(),
            'storage_path' => $path,
            'disk' => 'private',
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => $file->getSize() ?? 0,
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'status' => 'pending',
        ]);

        $logger->log('admissions.document_uploaded', "Mengunggah dokumen {$doc->document_type} untuk {$this->applicant->name}", $doc);

        $this->reset(['document_file']);
        $this->applicant->load('documents');
        $this->dispatch('toast', type: 'success', message: 'Dokumen berhasil diunggah.');
    }

    public function verifyDocument(int $documentId, string $status, ActivityLogger $logger): void
    {
        $this->authorize('admissions.verify');

        if (! in_array($status, ['verified', 'rejected', 'pending'], true)) {
            return;
        }

        $doc = ApplicantDocument::query()
            ->where('applicant_id', $this->applicant->getKey())
            ->findOrFail($documentId);

        $doc->update(['status' => $status]);
        $logger->log('admissions.document_verified', "Memperbarui status dokumen {$doc->document_type} menjadi {$status}", $doc);

        $this->applicant->load('documents');
        $this->dispatch('toast', type: 'success', message: "Status dokumen diubah menjadi {$status}.");
    }

    public function deleteDocument(int $documentId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.delete');

        $doc = ApplicantDocument::query()
            ->where('applicant_id', $this->applicant->getKey())
            ->findOrFail($documentId);

        Storage::disk('local')->delete($doc->storage_path);
        $doc->delete();
        $logger->log('admissions.document_deleted', "Menghapus dokumen {$doc->document_type}", $this->applicant);

        $this->applicant->load('documents');
        $this->dispatch('toast', type: 'success', message: 'Dokumen berhasil dihapus.');
    }

    public function createBill(ActivityLogger $logger): void
    {
        $this->authorize('admissions.create');

        $data = $this->validate([
            'bill_title' => ['required', 'string', 'max:150'],
            'bill_amount' => ['required', 'numeric', 'gt:0'],
            'bill_due_date' => ['nullable', 'date'],
        ]);

        $bill = $this->createAdmissionBill($data);

        $logger->log('admissions.bill_created', "Membuat tagihan PPDB {$bill->bill_number} senilai {$bill->amount}", $bill);

        $this->reset(['bill_title', 'bill_amount']);
        $this->bill_due_date = now()->addMonth()->toDateString();
        $this->applicant->load('bills');
        $this->dispatch('toast', type: 'success', message: "Tagihan {$bill->bill_number} berhasil dibuat.");
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createAdmissionBill(array $data): AdmissionBill
    {
        $attributes = [
            'title' => $data['bill_title'],
            'amount' => $data['bill_amount'],
            'due_date' => $data['bill_due_date'] !== '' ? $data['bill_due_date'] : null,
            'status' => 'unpaid',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return $this->applicant->bills()->create($attributes + [
                    'bill_number' => $this->admissionBillNumber(),
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                if (! str_contains($exception->getMessage(), 'bill_number') || $attempt === 5) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Gagal membuat nomor tagihan PPDB unik.');
    }

    private function admissionBillNumber(): string
    {
        return 'PPDB-INV-'.now()->format('Ymd').'-'.Str::upper(Str::random(5));
    }

    public function updateBillStatus(int $billId, string $status, AdmissionPaymentService $payments): void
    {
        $this->authorize('admissions.verify');

        if (! in_array($status, ['unpaid', 'paid', 'cancelled'], true)) {
            return;
        }

        $bill = AdmissionBill::query()
            ->where('applicant_id', $this->applicant->getKey())
            ->findOrFail($billId);

        $actor = auth()->user();

        try {
            match ($status) {
                'paid' => $payments->markPaid($bill, $actor),
                'cancelled' => $payments->cancel($bill, $actor),
                default => $payments->revertToUnpaid($bill, $actor),
            };
        } catch (InvalidArgumentException $exception) {
            $this->dispatch('toast', type: 'error', message: $exception->getMessage());

            return;
        }

        $this->applicant->load('bills');
        $this->dispatch(
            'toast',
            type: 'success',
            message: $status === 'paid'
                ? "Tagihan {$bill->bill_number} ditandai lunas dan kas masuk tercatat."
                : "Status tagihan {$bill->bill_number} diubah ke {$status}.",
        );
    }

    public function render(): View
    {
        $this->authorize('admissions.view');

        return view('livewire.admissions.applicant-show', [
            'units' => Unit::query()->orderBy('code')->pluck('name', 'id')->all(),
            'classes' => SchoolClass::query()
                ->when($this->convert_unit_id !== null, fn ($query) => $query->where('unit_id', $this->convert_unit_id))
                ->ordered()
                ->get()
                ->mapWithKeys(static fn (SchoolClass $class): array => [$class->id => $class->name.' - '.($class->academicYear?->name ?? '-')])
                ->all(),
        ]);
    }
}
