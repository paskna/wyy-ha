<?php

namespace App\Http\Controllers;

use App\Models\Scan;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MoreController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $perPage = max(5, (int) $this->settings->get('general', 'per_page', 12));

        $stats = [
            'collection' => $user->userWines()->count(),
            'scans' => $user->scans()->count(),
            'failed_scans' => $user->scans()->whereIn('status', Scan::FAILED_STATUSES)->count(),
        ];

        $history = $user->scans()
            ->with('matchedWineVintage.wine.producer')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return view('more.index', compact('stats', 'history'));
    }
}
