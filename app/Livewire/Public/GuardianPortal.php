<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Enums\BillStatus;
use App\Models\Guardian;
use App\Models\Student;
use App\Services\PortalService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Portal orang tua/wali.
 *
 * Berbeda dari portal siswa yang hanya menampilkan satu siswa, komponen ini
 * menampilkan ringkasan seluruh anak pada satu akun lalu memungkinkan wali
 * memilih satu anak untuk melihat rinciannya.
 */
#[Layout('layouts.public')]
class GuardianPortal extends Component
{
    #[Url(as: 'anak')]
    public string $selectedStudentId = '';

    public function mount(): void
    {
        if (! session('public_guardian_id')) {
            $this->redirect('/portal/orang-tua/login');
        }
    }

    public function selectStudent(int $studentId): void
    {
        $this->selectedStudentId = (string) $studentId;
    }

    public function render(PortalService $portal): View
    {
        $guardian = Guardian::query()
            ->with(['activeStudents.unit', 'activeStudents.schoolClass', 'activeStudents.savingsAccount'])
            ->findOrFail(session('public_guardian_id'));

        $students = $guardian->activeStudents;
        $overview = $portal->childrenOverview($students);

        $selected = $this->resolveSelectedStudent($students);
        $detail = null;

        if ($selected instanceof Student) {
            $detail = [
                'outstanding' => $portal->outstanding($selected),
                'latest_payment' => $portal->latestApprovedPayment($selected),
                'bills' => $portal->bills($selected),
                'intents' => $portal->openIntents($selected),
                'payments' => $portal->payments($selected),
                'savings_account' => $selected->savingsAccount,
                'savings_transactions' => $portal->savingsTransactions($selected),
            ];
        }

        return view('livewire.public.guardian-portal', [
            'guardian' => $guardian,
            'overview' => $overview,
            'students' => $students,
            'selected' => $selected,
            'detail' => $detail,
            'totalOutstanding' => $overview->sum('outstanding_amount'),
            'totalSavings' => $overview->sum('savings_balance'),
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     */
    private function resolveSelectedStudent($students): ?Student
    {
        if ($students->isEmpty()) {
            return null;
        }

        $selected = $students->firstWhere('id', (int) $this->selectedStudentId);

        return $selected ?? $students->first();
    }
}
