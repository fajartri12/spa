<?php

declare(strict_types=1);

namespace App\Livewire\Tools;

use App\Models\Backup;
use App\Support\Pagination;
use App\Services\ActivityLogger;
use App\Services\BackupService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

#[Layout('layouts.app')]
#[Title('Backup Database')]
class BackupIndex extends Component
{
    use WithPagination;

    public ?int $deletingId = null;

    public bool $showRestoreModal = false;

    public ?int $restoringId = null;

    public string $confirmText = '';

    public function createBackup(BackupService $service, ActivityLogger $logger): void
    {
        $this->authorize('backup.create');

        try {
            $backup = $service->create();
            $logger->log('backups.created', "Membuat backup database {$backup->filename}", $backup);
            $this->dispatch('toast', type: 'success', message: 'Backup database berhasil dibuat.');
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function confirmRestore(int $id): void
    {
        $this->authorize('backup.restore');

        $this->restoringId = $id;
        $this->confirmText = '';
        $this->showRestoreModal = true;
    }

    public function restore(BackupService $service, ActivityLogger $logger): void
    {
        $this->authorize('backup.restore');

        if ($this->confirmText !== 'RESTORE' || $this->restoringId === null) {
            $this->addError('confirmText', 'Ketik RESTORE untuk mengonfirmasi.');

            return;
        }

        $backup = Backup::query()->findOrFail($this->restoringId);

        try {
            $service->restore($backup);
            $logger->log('backups.restored', "Restore database dari {$backup->filename}", $backup);
            $this->dispatch('toast', type: 'success', message: 'Database berhasil dipulihkan.');
        } catch (Throwable $e) {
            report($e);
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }

        $this->showRestoreModal = false;
        $this->restoringId = null;
        $this->confirmText = '';
    }

    public function confirmDelete(int $id): void
    {
        $this->authorize('backup.delete');
        $this->deletingId = $id;
    }

    public function delete(BackupService $service, ActivityLogger $logger): void
    {
        $this->authorize('backup.delete');

        if ($this->deletingId === null) {
            return;
        }

        $backup = Backup::query()->findOrFail($this->deletingId);
        $service->delete($backup);
        $logger->log('backups.deleted', "Menghapus backup {$backup->filename}", $backup);

        $this->deletingId = null;
        $this->dispatch('toast', type: 'success', message: 'Backup berhasil dihapus.');
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->canAny(['backup.create', 'backup.delete']), 403);

        $backups = Backup::query()->with('creator:id,name')->latest('id')->paginate(Pagination::perPage());

        return view('livewire.tools.backup-index', compact('backups'));
    }
}
