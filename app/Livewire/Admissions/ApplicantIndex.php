<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Pendaftar PPDB')]
class ApplicantIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $periodFilter = '';

    public string $statusFilter = '';

    public bool $showModal = false;

    public ?int $admission_period_id = null;

    public string $applicantName = '';

    public string $email = '';

    public string $phone = '';

    public string $gender = '';

    public string $birth_place = '';

    public string $birth_date = '';

    public string $address = '';

    public string $parent_name = '';

    public string $parent_phone = '';

    public string $notes = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'periodFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'page' => ['except' => 1],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->periodFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->admission_period_id = $this->periodFilter !== '' ? (int) $this->periodFilter : null;
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('admissions.create');

        $data = $this->validate([
            'admission_period_id' => ['required', 'exists:admission_periods,id'],
            'applicantName' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'string', 'max:20'],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'address' => ['nullable', 'string', 'max:1000'],
            'parent_name' => ['nullable', 'string', 'max:120'],
            'parent_phone' => ['nullable', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['name'] = $data['applicantName'];
        unset($data['applicantName']);
        $data = array_map(static fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $data);
        $data['registration_number'] = $this->registrationNumber();
        $applicant = Applicant::query()->create($data);

        $logger->log('admissions.applicant_created', "Mendaftarkan calon siswa {$applicant->name}", $applicant);

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: "Calon siswa berhasil didaftarkan dengan nomor {$applicant->registration_number}.");
    }

    private function registrationNumber(): string
    {
        do {
            $number = 'PPDB-'.now()->format('Y').'-'.str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        } while (Applicant::query()->where('registration_number', $number)->exists());

        return $number;
    }

    public function resetForm(): void
    {
        $this->admission_period_id = null;
        $this->applicantName = '';
        $this->email = '';
        $this->phone = '';
        $this->gender = '';
        $this->birth_place = '';
        $this->birth_date = '';
        $this->address = '';
        $this->parent_name = '';
        $this->parent_phone = '';
        $this->notes = '';
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('admissions.view');

        $query = Applicant::query()
            ->with(['admissionPeriod'])
            ->withCount(['documents', 'bills'])
            ->when($this->periodFilter !== '', fn ($q) => $q->where('admission_period_id', $this->periodFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when(trim($this->search) !== '', function ($q): void {
                $term = '%'.trim($this->search).'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)
                    ->orWhere('registration_number', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('email', 'like', $term));
            });

        return view('livewire.admissions.applicant-index', [
            'applicants' => $query->latest()->paginate(Pagination::perPage()),
            'periodOptions' => AdmissionPeriod::query()->orderByDesc('starts_at')->pluck('name', 'id'),
            'totalApplicants' => Applicant::query()->count(),
            'totalVerified' => Applicant::query()->where('status', 'verified')->count(),
            'totalPassed' => Applicant::query()->where('status', 'passed')->count(),
        ]);
    }
}
