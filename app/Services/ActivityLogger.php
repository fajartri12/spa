<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Pencatatan aktivitas untuk audit (AGENTS.MD bagian 9: Audit logging).
 */
class ActivityLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function log(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        ?User $user = null,
    ): ActivityLog {
        $actor = $user ?? Auth::user();

        return ActivityLog::query()->create([
            'user_id' => $actor instanceof User ? $actor->getKey() : null,
            'event' => $event,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    public function login(User $user): void
    {
        $this->log('auth.login', "{$user->name} masuk ke aplikasi", $user, user: $user);
    }

    public function logout(User $user): void
    {
        $this->log('auth.logout', "{$user->name} keluar dari aplikasi", $user, user: $user);
    }
}
