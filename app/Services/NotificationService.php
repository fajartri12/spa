<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use App\Models\Student;
use Illuminate\Support\Facades\Crypt;
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
     * @param  array{file?: string, delay?: int, schedule?: int}|null  $whatsappOptions
     */
    public function send(NotificationTemplate $template, Student $student, string $recipient, array $replacements, ?array $whatsappOptions = null): NotificationLog
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
                'whatsapp' => $this->sendWhatsApp($recipient, $message, $whatsappOptions ?? []),
                default => throw new \RuntimeException("Kanal {$template->channel} tidak dikenal."),
            };

            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (\Throwable $e) {
            report($e);
            $log->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }

        return $log;
    }

    /**
     * Kirim pesan WhatsApp bebas (tanpa template) dan catat hasilnya.
     *
     * Dipakai untuk kode bayar ulang dan OTP ubah password. Pesan OTP
     * sengaja tidak disalin ke kolom `message` agar tidak tersimpan permanen.
     */
    public function sendWhatsAppText(string $recipient, string $message, ?Student $student = null, bool $recordMessage = true): NotificationLog
    {
        $log = NotificationLog::query()->create([
            'notification_template_id' => null,
            'student_id' => $student?->id,
            'channel' => 'whatsapp',
            'recipient' => $recipient,
            'status' => 'pending',
            'message' => $recordMessage ? $message : null,
        ]);

        try {
            $this->sendWhatsApp($recipient, $message, []);

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

    /**
     * @param  array{file?: string, delay?: int, schedule?: int}  $options
     */
    private function sendWhatsApp(string $recipient, string $message, array $options): void
    {
        $endpoint = (string) AppSetting::get('whatsapp_endpoint', config('services.whatsapp.endpoint'));
        $storedToken = AppSetting::get('whatsapp_api_key');
        $token = $storedToken !== null
            ? Crypt::decryptString((string) $storedToken)
            : (string) config('services.whatsapp.token');
        $header = (string) AppSetting::get(
            'whatsapp_auth_header',
            config('services.whatsapp.auth_header', 'Authorization'),
        );
        $prefix = (string) AppSetting::get(
            'whatsapp_auth_prefix',
            config('services.whatsapp.auth_prefix', 'Bearer'),
        );

        if ($endpoint === '' || $token === '' || $header === '') {
            throw new \RuntimeException('Gateway WhatsApp belum dikonfigurasi (STARSENDER_API_KEY dan header autentikasi).');
        }

        $payload = [
            'messageType' => 'text',
            'to' => $recipient,
            'body' => $message,
        ];

        foreach (['file', 'delay', 'schedule'] as $key) {
            if (isset($options[$key])) {
                $payload[$key] = $options[$key];
            }
        }

        $authorization = trim($prefix) === '' ? $token : trim($prefix).' '.$token;
        $response = \Illuminate\Support\Facades\Http::withHeaders([$header => $authorization])
            ->timeout((int) config('services.whatsapp.timeout', 20))
            ->post($endpoint, $payload);

        if ($response->failed()) {
            throw new \RuntimeException('Gateway WhatsApp menolak permintaan: '.$response->status());
        }
    }
}
