<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SendFeeReminder;
use App\Models\ReminderSchedule;
use App\Models\StudentBill;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Antrikan notifikasi pengingat tagihan yang mendekati jatuh tempo';

    public function handle(): int
    {
        $queued = 0;

        $schedules = ReminderSchedule::query()
            ->where('is_active', true)
            ->with('template')
            ->get();

        foreach ($schedules as $schedule) {
            $template = $schedule->template;

            if ($template === null || ! $template->is_active) {
                continue;
            }

            // Cegah pengiriman ganda bila perintah dijalankan lebih dari sekali sehari.
            if ($schedule->last_run_at?->isToday()) {
                $this->line("Jadwal #{$schedule->id} sudah dijalankan hari ini, dilewati.");

                continue;
            }

            $targetDate = now()->addDays($schedule->days_before_due)->toDateString();

            /** @var array<int, Collection<int, StudentBill>> $perStudent */
            $perStudent = [];

            StudentBill::query()
                ->with(['student', 'bill'])
                ->whereIn('status', ['unpaid', 'partial'])
                ->whereDate('due_date', $targetDate)
                ->chunkById(500, function ($bills) use (&$perStudent): void {
                    foreach ($bills as $bill) {
                        if ($bill->student === null) {
                            continue;
                        }

                        $perStudent[$bill->student_id] ??= new Collection();
                        $perStudent[$bill->student_id]->push($bill);
                    }
                });

            // Satu siswa hanya menerima satu pesan berisi ringkasan seluruh tagihannya.
            foreach ($perStudent as $bills) {
                /** @var StudentBill $first */
                $first = $bills->first();
                $student = $first->student;

                $recipient = $template->channel === 'email'
                    ? (string) $student->email
                    : (string) $student->parent_phone;

                if ($recipient === '') {
                    continue;
                }

                $titles = $bills
                    ->map(fn (StudentBill $bill): string => $bill->bill?->title ?? $bill->bill_number)
                    ->unique()
                    ->implode(', ');

                SendFeeReminder::dispatch($template->id, $student->id, $recipient, [
                    'nama' => $student->name,
                    'tagihan' => $titles,
                    'nominal' => number_format((float) $bills->sum('remaining_amount'), 0, ',', '.'),
                    'jatuh_tempo' => $first->due_date?->translatedFormat('d F Y') ?? '-',
                    'sekolah' => config('app.name'),
                ]);

                $queued++;
            }

            $schedule->update(['last_run_at' => now()]);
        }

        $this->info("Pengingat diantrikan: {$queued}");

        return self::SUCCESS;
    }
}
