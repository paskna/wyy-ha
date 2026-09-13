<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\SettingsService;
use Illuminate\View\View;

class AdminAuditController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): View
    {
        $logs = AuditLog::query()
            ->with(['adminUser', 'targetUser'])
            ->latest()
            ->paginate(max(5, (int) $this->settings->get('general', 'per_page', 12)));

        return view('admin.audits.index', compact('logs'));
    }
}
