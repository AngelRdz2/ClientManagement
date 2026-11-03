<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Auditoría de acciones importantes (quién, qué, sobre qué registro y desde qué IP).
 */
class ActivityLogger
{
    /** @param array<string, mixed> $properties */
    public static function log(string $action, string $description, ?Model $subject = null, array $properties = [], ?int $userId = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => mb_substr($description, 0, 500),
            'properties' => $properties ?: null,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
        ]);
    }
}
