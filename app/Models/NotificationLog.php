<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use MassPrunable;

    /**
     * Retensi log notifikasi: 90 hari (mengandung data pribadi penerima).
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        return static::query()->where('created_at', '<=', now()->subDays(90));
    }
    /**
     * @var list<string>
     */
    protected $fillable = [
        'notification_template_id',
        'student_id',
        'channel',
        'recipient',
        'status',
        'message',
        'error',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Masking penerima (nomor WA/email) agar data pribadi tidak tampil penuh.
     */
    public function maskedRecipient(): string
    {
        return self::mask($this->recipient);
    }

    public static function mask(string $recipient): string
    {
        if (filter_var($recipient, FILTER_VALIDATE_EMAIL) !== false) {
            [$name, $domain] = explode('@', $recipient, 2);

            return mb_substr($name, 0, 2) . str_repeat('*', max(3, mb_strlen($name) - 2)) . '@' . $domain;
        }

        $digits = preg_replace('/\D+/', '', $recipient) ?? '';

        if (mb_strlen($digits) < 6) {
            return str_repeat('*', mb_strlen($recipient));
        }

        return mb_substr($digits, 0, 4) . str_repeat('*', max(0, mb_strlen($digits) - 8)) . mb_substr($digits, -4);
    }
}
