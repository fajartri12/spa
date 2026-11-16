<?php

declare(strict_types=1);

namespace App\Livewire\Tools;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\ReminderSchedule;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Notifikasi & Antrian')]
class NotificationIndex extends Component
{
    use WithPagination;

    public string $tab = 'logs';

    public string $search = '';

    public string $channel = '';

    public string $status = '';

    public function updating(string $field): void
    {
        if (in_array($field, ['search', 'channel', 'status', 'tab'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $this->authorize('activity-logs.view');

        $logs = NotificationLog::query()
            ->with(['template:id,name', 'student:id,name'])
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('recipient', 'like', "%{$this->search}%")
                ->orWhereHas('student', fn ($q) => $q->where('name', 'like', "%{$this->search}%"))))
            ->when($this->channel !== '', fn ($q) => $q->where('channel', $this->channel))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(Pagination::perPage());

        $templates = NotificationTemplate::query()->orderBy('name')->get();
        $schedules = ReminderSchedule::query()->with('template:id,name')->orderBy('days_before_due')->get();

        $queues = [
            'pending' => DB::table('jobs')->count(),
            'failed' => DB::table('failed_jobs')->count(),
        ];

        return view('livewire.tools.notification-index', compact('logs', 'templates', 'schedules', 'queues'));
    }
}
