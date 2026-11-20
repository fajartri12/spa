<?php

declare(strict_types=1);

namespace App\Livewire\Tools;

use App\Models\AppSetting;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Services\ActivityLogger;
use App\Models\ReminderSchedule;
use App\Support\Pagination;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;
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

    public string $whatsapp_endpoint = '';

    public string $whatsapp_api_key = '';

    public bool $whatsapp_api_key_configured = false;

    public function mount(): void
    {
        $this->authorize('activity-logs.view');

        $this->whatsapp_endpoint = (string) AppSetting::get(
            'whatsapp_endpoint',
            (string) config('services.whatsapp.endpoint'),
        );
        $this->whatsapp_api_key_configured = AppSetting::get('whatsapp_api_key') !== null;
    }

    public function saveWhatsAppSettings(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        $this->validate([
            'whatsapp_endpoint' => ['required', 'url', 'max:500'],
            'whatsapp_api_key' => ['nullable', 'string', 'max:500'],
        ]);

        AppSetting::set('whatsapp_endpoint', $this->whatsapp_endpoint);

        if ($this->whatsapp_api_key !== '') {
            AppSetting::set('whatsapp_api_key', Crypt::encryptString($this->whatsapp_api_key));
            $this->whatsapp_api_key = '';
            $this->whatsapp_api_key_configured = true;
        }

        $logger->log('settings.updated', 'Memperbarui pengaturan gateway WhatsApp Starsender');
        $this->dispatch('toast', type: 'success', message: 'Pengaturan WhatsApp berhasil disimpan.');
    }

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
