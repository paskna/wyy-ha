<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiActivityLog;
use App\Services\SettingsService;
use Illuminate\View\View;

class AdminApiActivityController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(): View
    {
        return view('admin.api-activities.index', [
            'logs' => ApiActivityLog::query()->latest()->paginate(max(5, (int) $this->settings->get('general', 'per_page', 12))),
        ]);
    }
}
