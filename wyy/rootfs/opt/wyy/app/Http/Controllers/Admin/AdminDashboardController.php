<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiActivityLog;
use App\Models\AuditLog;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserWine;
use App\Models\Wine;
use App\Models\WineVintage;
use App\Services\IntegrationManager;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __construct(private readonly IntegrationManager $integrations) {}

    public function __invoke(): View
    {
        $scans = Scan::query()->count();
        $failedScans = Scan::query()->whereIn('status', Scan::FAILED_STATUSES)->count();
        $successfulScans = max(0, $scans - $failedScans);

        $stats = [
            'users' => User::query()->count(),
            'active_users' => User::query()->where('status', 'active')->count(),
            'inactive_users' => User::query()->where('status', 'inactive')->count(),
            'admins' => User::query()->where('role', 'admin')->count(),
            'normal_users' => User::query()->where('role', 'user')->count(),
            'wines' => Wine::query()->count(),
            'vintages' => WineVintage::query()->count(),
            'user_wines' => UserWine::query()->count(),
            'scans' => $scans,
            'scans_today' => Scan::query()->whereDate('created_at', today())->count(),
            'failed_scans' => $failedScans,
            'scan_success_rate' => $scans > 0 ? round(($successfulScans / $scans) * 100, 1) : 100,
            'image_storage_mb' => round($this->directorySize(storage_path('app')) / 1024 / 1024, 1),
        ];

        $recentUsers = User::query()->latest('last_login_at')->take(8)->get();
        $recentActivities = AuditLog::query()->with('adminUser', 'targetUser')->latest()->take(8)->get();
        $recentApiErrors = ApiActivityLog::query()->where('status', 'error')->latest()->take(5)->get();

        return view('admin.dashboard', [
            'stats' => $stats,
            'recentUsers' => $recentUsers,
            'recentActivities' => $recentActivities,
            'recentApiErrors' => $recentApiErrors,
            'providers' => $this->integrations->overview(),
        ]);
    }

    private function directorySize(string $path): int
    {
        if (! File::exists($path)) {
            return 0;
        }

        return collect(File::allFiles($path))->sum(fn ($file) => $file->getSize());
    }
}
