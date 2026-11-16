<?php

declare(strict_types=1);

namespace App\Livewire\Admissions;

use App\Models\AdmissionContent;
use App\Models\AdmissionPeriod;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Pengumuman PPDB')]
class ContentIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $periodFilter = '';

    public bool $showModal = false;

    public ?int $contentId = null;

    public string $contentTitle = '';

    public string $contentBody = '';

    public bool $contentPublished = false;

    public ?int $contentPeriodId = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'periodFilter' => ['except' => ''],
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

    public function resetFilters(): void
    {
        $this->search = '';
        $this->periodFilter = '';
        $this->resetPage();
    }

    public function create(): void
    {
        $this->authorize('admissions.create');
        $this->resetForm();
        $this->contentPeriodId = $this->periodFilter !== '' ? (int) $this->periodFilter : null;
        $this->showModal = true;
    }

    public function edit(int $contentId): void
    {
        $this->authorize('admissions.update');
        $this->resetForm();

        $content = AdmissionContent::query()->findOrFail($contentId);
        $this->contentId = $content->id;
        $this->contentTitle = $content->title;
        $this->contentBody = $content->body;
        $this->contentPublished = (bool) $content->is_published;
        $this->contentPeriodId = $content->admission_period_id;
        $this->showModal = true;
    }

    public function save(ActivityLogger $logger): void
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
        $this->dispatch('toast', type: 'success', message: 'Pengumuman PPDB berhasil disimpan.');
    }

    public function togglePublished(int $contentId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.update');

        $content = AdmissionContent::query()->findOrFail($contentId);
        $content->update(['is_published' => ! $content->is_published]);
        $logger->log('admissions.content_published', "Mengubah publikasi konten {$content->title}", $content);

        $this->dispatch('toast', type: 'success', message: $content->is_published ? 'Pengumuman dipublikasikan.' : 'Pengumuman disembunyikan.');
    }

    public function delete(int $contentId, ActivityLogger $logger): void
    {
        $this->authorize('admissions.delete');

        $content = AdmissionContent::query()->findOrFail($contentId);
        $content->delete();
        $logger->log('admissions.content_deleted', "Menghapus konten PPDB {$content->title}");

        $this->dispatch('toast', type: 'success', message: 'Pengumuman PPDB berhasil dihapus.');
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

    public function resetForm(): void
    {
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

        $contents = AdmissionContent::query()
            ->with('admissionPeriod')
            ->when($this->periodFilter !== '', fn ($q) => $q->where('admission_period_id', $this->periodFilter))
            ->when(trim($this->search) !== '', fn ($q) => $q->where('title', 'like', '%'.trim($this->search).'%'))
            ->latest()
            ->paginate(Pagination::perPage());

        return view('livewire.admissions.content-index', [
            'contents' => $contents,
            'periodOptions' => AdmissionPeriod::query()->orderByDesc('starts_at')->pluck('name', 'id'),
        ]);
    }
}
