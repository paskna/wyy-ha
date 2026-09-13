<?php

namespace App\Http\Controllers;

use App\Enums\Preference;
use App\Services\TasteProfileService;
use App\Services\WineSimilarityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, TasteProfileService $tasteProfileService, WineSimilarityService $similarityService): View
    {
        $user = $request->user();
        $profile = $tasteProfileService->buildFor($user);
        $profileSummary = $tasteProfileService->summary($user);
        $recent = $user->userWines()->with('wineVintage.wine.producer', 'wineVintage.grapes')->latest('last_scanned_at')->take(4)->get();
        $top = $user->userWines()->with('wineVintage.wine.producer', 'wineVintage.grapes')->where('preference', Preference::Top)->latest('updated_at')->take(4)->get();
        $recommendations = $tasteProfileService->recommendations($user, 5);

        return view('dashboard', compact('profile', 'profileSummary', 'recent', 'top', 'recommendations'));
    }
}
