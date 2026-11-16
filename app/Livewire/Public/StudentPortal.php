<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Student;
use App\Services\PortalService;
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
    public ?Student $student = null;

    public function mount(): void
    {
        $studentId = session('public_student_id');

        if (! $studentId) {
            $this->redirect('/portal/login');
        }

        $this->student = Student::query()->findOrFail($studentId);
    }

    public function render(PortalService $portal): View
    {
        return view('livewire.public.student-portal', [
            'bills' => $portal->bills($this->student),
            'intents' => $portal->openIntents($this->student),
            'payments' => $portal->payments($this->student),
            'latestApprovedPayment' => $portal->latestApprovedPayment($this->student),
            'outstanding' => $portal->outstanding($this->student),
            'savingsAccount' => $this->student->savingsAccount,
            'savingsTransactions' => $portal->savingsTransactions($this->student),
        ]);
    }
}
