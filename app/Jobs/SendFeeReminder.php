<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\NotificationTemplate;
use App\Models\Student;
use App\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendFeeReminder implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string|int|float|null>  $replacements
     */
    public function __construct(
        public int $templateId,
        public int $studentId,
        public string $recipient,
        public array $replacements = [],
    ) {
    }

    public function handle(NotificationService $notifications): void
    {
        $template = NotificationTemplate::query()->find($this->templateId);

        if ($template === null || ! $template->is_active) {
            return;
        }

        $student = Student::query()->find($this->studentId);

        if ($student === null) {
            return;
        }

        $notifications->send($template, $student, $this->recipient, $this->replacements);
    }
}
