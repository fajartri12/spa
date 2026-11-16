<?php

declare(strict_types=1);

namespace App\Livewire\Tools;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Log Aktivitas')]
class ActivityLogIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $eventFilter = '';

    public function updatedSearch(): void { $this->resetPage(); }

    public function updatedEventFilter(): void { $this->resetPage(); }

    public function render(): View
    {
        $this->authorize('activity-logs.view');

        $logs = ActivityLog::query()
            ->with('user:id,name')
            ->when($this->search !== '', fn ($query) => $query->where(function ($inner): void {
                $inner->where('description', 'like', '%'.$this->search.'%')
                    ->orWhere('event', 'like', '%'.$this->search.'%');
            }))
            ->when($this->eventFilter !== '', fn ($query) => $query->where('event', $this->eventFilter))
            ->latest('created_at')
            ->paginate(20);

        $events = ActivityLog::query()->select('event')->distinct()->orderBy('event')->pluck('event', 'event')->all();

        return view('livewire.tools.activity-log-index', compact('logs', 'events'));
    }
}
