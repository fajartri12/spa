<?php

declare(strict_types=1);

namespace App\Livewire\Tools;

use App\Services\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Informasi Server')]
class ServerInfoIndex extends Component
{
    /**
     * @return array<string, string>
     */
    public function serverDetails(): array
    {
        $this->authorize('settings.view');

        return [
            'Aplikasi' => config('app.name'),
            'Lingkungan' => app()->environment(),
            'Versi Laravel' => app()->version(),
            'Versi PHP' => PHP_VERSION,
            'Zona Waktu' => config('app.timezone'),
            'Database' => (string) config('database.default'),
            'Driver Antrian' => (string) config('queue.default'),
            'Driver Cache' => (string) config('cache.default'),
            'Disk Terpakai' => $this->diskUsage(),
            'Waktu Server' => now()->translatedFormat('d F Y H:i:s'),
        ];
    }

    public function clearCache(ActivityLogger $logger): void
    {
        $this->authorize('settings.update');

        Artisan::call('optimize:clear');
        $logger->log('system.cache-cleared', 'Membersihkan cache aplikasi');

        $this->dispatch('toast', type: 'success', message: 'Cache aplikasi berhasil dibersihkan.');
    }

    public function render(): View
    {
        $this->authorize('settings.view');

        return view('livewire.tools.server-info-index', [
            'details' => $this->visibleDetails(),
        ]);
    }

    /**
     * Rincian runtime hanya untuk pemegang `settings.update`; pemegang
     * `settings.view` cukup melihat waktu server dan pemakaian disk.
     *
     * @return array<string, string>
     */
    private function visibleDetails(): array
    {
        $details = $this->serverDetails();

        if (auth()->user()?->can('settings.update')) {
            return $details;
        }

        return array_intersect_key($details, array_flip(['Aplikasi', 'Zona Waktu', 'Disk Terpakai', 'Waktu Server']));
    }

    private function diskUsage(): string
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return 'Tidak tersedia';
        }

        $usedPercent = (int) round((($total - $free) / $total) * 100);

        return sprintf('%d%% terpakai dari %s', $usedPercent, $this->formatBytes((float) $total));
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return number_format($bytes, 1, ',', '.').' '.$units[$index];
    }
}
