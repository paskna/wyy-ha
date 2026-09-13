<?php

namespace App\Http\Controllers;

use App\Services\InstallationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(InstallationService $installation): JsonResponse
    {
        if (! $installation->isInstalled()) {
            return response()->json(['status' => 'initializing'], 503);
        }

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return response()->json(['status' => 'database_unavailable'], 503);
        }

        return response()->json(['status' => 'ok']);
    }
}
