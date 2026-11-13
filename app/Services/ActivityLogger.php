<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogger
{
    public function log(string $action, string $subject, string $description): ActivityLog
    {
        $user = Auth::user();

        return ActivityLog::query()->create([
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->name ?? 'System',
            'action' => $action,
            'subject' => $subject,
            'description' => $description,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
        ]);
    }
}
