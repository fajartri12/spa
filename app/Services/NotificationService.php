<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Student;
use Illuminate\Support\Facades\Mail;

/**
 * Pengiriman notifikasi tagihan (AGENTS.MD fase 7).
 *
 * Kanal yang belum dikonfigurasi (token WhatsApp kosong) ditandai gagal
 * dengan alasan yang jelas, bukan seolah-olah berhasil.
 */
class NotificationService
{
    /**
     * @param  array<string, string|int|float|null>  $replacements
     */
    public function render(NotificationTemplate $template, array $replacements): string
    {
        $body = $template->body;

        foreach ($replacements as $key => $value) {
            $val = (string) $value;
            // Dukung format bracket [VARIABLE] dan kurawal {variable}
            $body = str_replace('['.strtoupper($key).']', $val, $body);
            $body = str_replace('{'.strtolower($key).'}', $val, $body);
            $body = str_replace('{'.$key.'}', $val, $body);
        }

        return $body;
    }

    /**
     * @param  array<string, string|int|float|null>  $replacements
     */
    public function send(NotificationTemplate $template, Student $student, string $recipient, array $replacements): NotificationLog
    {
        $message = $this->render($template, $replacements);

        $log = NotificationLog::query()->create([
            'notification_template_id' => $template->id,
            'student_id' => $student->id,
            'channel' => $template->channel,
            'recipient' => $recipient,
            'status' => 'pending',
            'message' => $message,
        ]);

        try {
            match ($template->channel) {
                'email' => $this->sendEmail($recipient, $template->subject ?: $template->name, $message),
                'whatsapp' => $this->sendWhatsApp($recipient, $message),
                default => throw new \RuntimeException("Kanal {$template->channel} tidak dikenal."),
            };

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $log;
    }

    private function sendEmail(string $recipient, string $subject, string $message): void
    {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Alamat email penerima tidak valid.');
        }

        Mail::raw($message, static function ($mail) use ($recipient, $subject): void {
            $mail->to($recipient)->subject($subject);
        });
    }

    private function sendWhatsApp(string $recipient, string $message): void
    {
        $endpoint = (string) config('services.whatsapp.endpoint');
        $token = (string) config('services.whatsapp.token');

        if ($endpoint === '' || $token === '') {
            throw new \RuntimeException('Gateway WhatsApp belum dikonfigurasi (WHATSAPP_ENDPOINT / WHATSAPP_TOKEN).');
        }

        $response = \Illuminate\Support\Facades\Http::withToken($token)
            ->timeout(20)
            ->post($endpoint, ['target' => $recipient, 'message' => $message]);

        if ($response->failed()) {
            throw new \RuntimeException('Gateway WhatsApp menolak permintaan: '.$response->status());
        }
    }
}
