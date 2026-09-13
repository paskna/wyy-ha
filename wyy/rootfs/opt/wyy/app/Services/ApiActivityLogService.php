<?php

namespace App\Services;

use App\Models\ApiActivityLog;
use App\Models\Scan;
use App\Models\User;

class ApiActivityLogService
{
    public function log(
        string $provider,
        string $action,
        string $status,
        ?int $responseTimeMs = null,
        ?int $httpStatus = null,
        ?string $message = null,
        ?User $user = null,
        ?Scan $scan = null,
    ): ApiActivityLog {
        return ApiActivityLog::query()->create([
            'provider' => $provider,
            'action' => $action,
            'status' => $status,
            'response_time_ms' => $responseTimeMs,
            'http_status' => $httpStatus,
            'message' => $message,
            'user_id' => $user?->id,
            'scan_id' => $scan?->id,
        ]);
    }
}
