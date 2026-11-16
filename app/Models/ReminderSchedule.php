<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderSchedule extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'notification_template_id',
        'name',
        'days_before_due',
        'send_at',
        'is_active',
        'last_run_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_before_due' => 'integer',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<NotificationTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'notification_template_id');
    }
}
