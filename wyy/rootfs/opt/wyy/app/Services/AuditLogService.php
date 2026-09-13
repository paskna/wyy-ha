<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;

class AuditLogService
{
    public function log(User $admin, User $target, string $action, array $metadata = []): AuditLog
    {
        return AuditLog::query()->create([
            'admin_user_id' => $admin->id,
            'target_user_id' => $target->id,
            'action' => $action,
            'metadata' => $metadata,
        ]);
    }
}
