<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Backup;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Backup & restore database MySQL/MariaDB (AGENTS.MD bagian 7 & 9).
 *
 * Membutuhkan binary mysqldump/mysql. Lokasi dapat diatur melalui
 * MYSQLDUMP_PATH dan MYSQL_PATH pada .env.
 */
class BackupService
{
    public function create(): Backup
    {
        $database = (string) config('database.connections.'.config('database.default').'.database');
        $filename = sprintf('%s-%s.sql', str_replace('-', '', $database), now()->format('Ymd-His'));

        $process = new Process([
            $this->binary('MYSQLDUMP_PATH', 'mysqldump'),
            ...$this->connectionArgs(),
            '--single-transaction',
            '--routines',
            '--skip-lock-tables',
            $database,
        ]);

        $process->setTimeout(300);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Backup gagal: '.trim($process->getErrorOutput()));
        }

        $disk = \Illuminate\Support\Facades\Storage::disk(Backup::DISK);
        $disk->makeDirectory(Backup::DIRECTORY);
        $disk->put(Backup::DIRECTORY.'/'.$filename, $process->getOutput());

        return Backup::query()->create([
            'filename' => $filename,
            'size' => strlen($process->getOutput()),
            'created_by' => auth()->id(),
            'status' => 'completed',
        ]);
    }

    public function restore(Backup $backup): void
    {
        $disk = \Illuminate\Support\Facades\Storage::disk(Backup::DISK);

        if (! $disk->exists($backup->path())) {
            throw new RuntimeException('File backup tidak ditemukan pada storage.');
        }

        $database = (string) config('database.connections.'.config('database.default').'.database');

        $process = new Process([
            $this->binary('MYSQL_PATH', 'mysql'),
            ...$this->connectionArgs(),
            $database,
        ]);

        $process->setTimeout(300);
        $process->setInput($disk->get($backup->path()));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Restore gagal: '.trim($process->getErrorOutput()));
        }
    }

    public function delete(Backup $backup): void
    {
        \Illuminate\Support\Facades\Storage::disk(Backup::DISK)->delete($backup->path());
        $backup->delete();
    }

    /**
     * @return list<string>
     */
    private function connectionArgs(): array
    {
        $connection = config('database.connections.'.config('database.default'));

        $args = ['--host='.$connection['host'], '--port='.$connection['port'], '--user='.$connection['username']];

        if (! empty($connection['password'])) {
            // Jangan kirim password di CLI karena bisa terlihat di `ps`.
            // Gunakan file sementara dengan permission 0600.
            $tempFile = tempnam(sys_get_temp_dir(), 'mysql-creds-');
            file_put_contents($tempFile, "[client]\npassword={$connection['password']}\n");
            chmod($tempFile, 0600);
            $args[] = '--defaults-extra-file='.$tempFile;
            
            // Hapus file setelah proses selesai.
            register_shutdown_function(function () use ($tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            });
        }

        return $args;
    }

    private function binary(string $envKey, string $default): string
    {
        $path = (string) env($envKey, $default);

        if ($path === '') {
            throw new RuntimeException("Binary {$default} belum dikonfigurasi ({$envKey}).");
        }

        return $path;
    }
}
