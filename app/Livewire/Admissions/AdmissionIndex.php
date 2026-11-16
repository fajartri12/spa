<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionContent;
use App\Models\AdmissionPeriod;
use App\Models\Applicant;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('PPDB')]
class AdmissionIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $periodFilter = '';

    public string $activeTab = 'periods';

    public bool $showModal = false;

    public string $modalType = 'period';

    public ?int $editingId = null;

    public string $name = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public string $status = 'draft';

    public string $description = '';

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

    public ?int $contentId = null;

    public string $contentTitle = '';

    public string $contentBody = '';

    public bool $contentPublished = false;

    public ?int $contentPeriodId = null;

    protected $queryString = ['search' => ['except' => ''], 'periodFilter' => ['except' => ''], 'page' => ['except' => 1]];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPeriodFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->periodFilter = '';
        $this->resetPage();
    }

    public function createPeriod(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->modalType = 'period';
        $this->starts_at = now()->toDateString();
        $this->ends_at = now()->addMonths(3)->toDateString();
        $this->showModal = true;
    }

    public function createApplicant(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->modalType = 'applicant';
        $this->admission_period_id = $this->periodFilter !== '' ? (int) $this->periodFilter : null;
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
    {
        $this->authorize('admissions.create');
        if ($this->modalType === 'period') {
            $data = $this->validate(['name' => ['required', 'string', 'max:120'], 'starts_at' => ['required', 'date'], 'ends_at' => ['required', 'date', 'after_or_equal:starts_at'], 'status' => ['required', Rule::in(['draft', 'open', 'closed'])], 'description' => ['nullable', 'string', 'max:1000']]);
            $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(5));
            $period = AdmissionPeriod::query()->create($data);
            $logger->log('admissions.period_created', "Menambahkan periode {$period->name}", $period);
            $message = 'Periode PPDB berhasil ditambahkan.';
        } else {
            $data = $this->validate(['admission_period_id' => ['required', 'exists:admission_periods,id'], 'applicantName' => ['required', 'string', 'max:120'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', 'string', 'max:20'], 'birth_place' => ['nullable', 'string', 'max:120'], 'birth_date' => ['nullable', 'date', 'before:today'], 'address' => ['nullable', 'string', 'max:1000'], 'parent_name' => ['nullable', 'string', 'max:120'], 'parent_phone' => ['nullable', 'string', 'max:30'], 'notes' => ['nullable', 'string', 'max:1000']]);
            $data['name'] = $data['applicantName'];
            unset($data['applicantName']);
            $data = array_map(static fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $data);
            $data['registration_number'] = $this->registrationNumber();
            $applicant = Applicant::query()->create($data);
            $logger->log('admissions.applicant_created', "Mendaftarkan calon siswa {$applicant->name}", $applicant);
            $message = "Calon siswa berhasil didaftarkan dengan nomor {$applicant->registration_number}.";
        }
        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $message);
    }

    public function createContent(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->modalType = 'content';
        $this->contentPeriodId = $this->periodFilter !== '' ? (int) $this->periodFilter : null;
        $this->showModal = true;
    }

    public function editContent(int $contentId): void
    {
        $this->authorize('admissions.update');
        $this->resetForm();
        $content = AdmissionContent::query()->findOrFail($contentId);
        $this->modalType = 'content';
        $this->contentId = $content->id;
        $this->contentTitle = $content->title;
        $this->contentBody = $content->body;
        $this->contentPublished = (bool) $content->is_published;
        $this->contentPeriodId = $content->admission_period_id;
        $this->showModal = true;
    }

    public function saveContent(ActivityLogger $logger): void
    {
        $this->authorize($this->contentId === null ? 'admissions.create' : 'admissions.update');

        $data = $this->validate([
            'contentTitle' => ['required', 'string', 'max:180'],
            'contentBody' => ['required', 'string'],
            'contentPublished' => ['boolean'],
            'contentPeriodId' => ['nullable', 'exists:admission_periods,id'],
        ]);

        $attributes = [
            'admission_period_id' => $data['contentPeriodId'],
            'title' => $data['contentTitle'],
            'body' => $data['contentBody'],
            'is_published' => $data['contentPublished'],
        ];

        if ($this->contentId === null) {
            $attributes['slug'] = $this->uniqueSlug($data['contentTitle']);
            $content = AdmissionContent::query()->create($attributes);
            $logger->log('admissions.content_created', "Menambahkan konten PPDB {$content->title}", $content);
        } else {
            $content = AdmissionContent::query()->findOrFail($this->contentId);
            $content->update($attributes);
            $logger->log('admissions.content_updated', "Memperbarui konten PPDB {$content->title}", $content);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: 'Konten PPDB berhasil disimpan.');
    }

    public function toggleContentPublished(int $contentId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.update');

        $content = AdmissionContent::query()->findOrFail($contentId);
        $content->update(['is_published' => ! $content->is_published]);
        $logger->log('admissions.content_published', "Mengubah publikasi konten {$content->title}", $content);

        $this->dispatch('toast', type: 'success', message: $content->is_published ? 'Konten dipublikasikan.' : 'Konten disembunyikan.');
    }

    public function deleteContent(int $contentId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.delete');

        $content = AdmissionContent::query()->findOrFail($contentId);
        $content->delete();
        $logger->log('admissions.content_deleted', "Menghapus konten PPDB {$content->title}");

        $this->dispatch('toast', type: 'success', message: 'Konten PPDB berhasil dihapus.');
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'konten';
        $slug = $base;
        $suffix = 2;

        while (AdmissionContent::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
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
        $this->editingId = null;
        $this->name = '';
        $this->starts_at = '';
        $this->ends_at = '';
        $this->status = 'draft';
        $this->description = '';
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
        $this->contentId = null;
        $this->contentTitle = '';
        $this->contentBody = '';
        $this->contentPublished = false;
        $this->contentPeriodId = null;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $this->authorize('admissions.view');
        $periods = AdmissionPeriod::query()->withCount('applicants')->when(trim($this->search) !== '', fn ($q) => $q->where('name', 'like', '%'.trim($this->search).'%'))->latest()->paginate(15, ['*'], 'periodPage');
        $applicants = Applicant::query()->with('admissionPeriod')->when($this->periodFilter !== '', fn ($q) => $q->where('admission_period_id', $this->periodFilter))->when(trim($this->search) !== '', fn ($q) => $q->where(fn ($x) => $x->where('name', 'like', '%'.trim($this->search).'%')->orWhere('registration_number', 'like', '%'.trim($this->search).'%')))->latest()->paginate(15, ['*'], 'applicantPage');
        $contents = AdmissionContent::query()->with('admissionPeriod')->when($this->periodFilter !== '', fn ($q) => $q->where('admission_period_id', $this->periodFilter))->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%'.trim($this->search).'%'))->latest()->paginate(15, ['*'], 'contentPage');

        return view('livewire.admissions.admission-index', [
            'periods' => $periods,
            'applicants' => $applicants,
            'contents' => $contents,
            'periodOptions' => AdmissionPeriod::query()->orderByDesc('starts_at')->pluck('name', 'id'),
        ]);
    }
}
