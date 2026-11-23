<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Livewire\Public\Concerns\UploadsTransferProof;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\PortalService;
use App\Services\SppMatrixService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Portal siswa.
 *
 * Hanya menampilkan data satu siswa, yaitu pemilik sesi. Data seluruh anak
 * dalam satu keluarga bukan wewenang portal ini; untuk itu gunakan portal
 * orang tua yang memiliki akun tersendiri.
 */
#[Layout('layouts.public')]
class StudentPortal extends Component
{
    use UploadsTransferProof;

    public ?Student $student = null;

    public function mount(): void
    {
        $studentId = session('public_student_id');

        if (! $studentId) {
            $this->redirect('/portal/login');
        }

        $this->student = Student::query()->findOrFail($studentId);
    }

    public function render(PortalService $portal, SppMatrixService $sppMatrix): View
    {
        return view('livewire.public.student-portal', [
            'bills' => $portal->bills($this->student),
            'intents' => $portal->openIntents($this->student),
            'payments' => $portal->payments($this->student),
            'latestApprovedPayment' => $portal->latestApprovedPayment($this->student),
            'outstanding' => $portal->outstanding($this->student),
            'savingsAccount' => $this->student->savingsAccount,
            'savingsTransactions' => $portal->savingsTransactions($this->student),
            'sppMatrix' => $sppMatrix->getMatrix($this->student),
            'sppPdfUrl' => route('public.spp-recap', $this->student),
        ]);
    }

    /**
     * @return list<StudentBill>
     */
    protected function proofBills(): iterable
    {
        return $this->student->studentBills()->get()->all();
    }
}
