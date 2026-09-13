<?php

namespace App\Http\Controllers;

use App\Enums\Preference;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TopWineController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        $perPage = max(5, (int) $this->settings->get('general', 'per_page', 12));
        $viewMode = $request->string('view')->toString();
        $viewMode = in_array($viewMode, ['cards', 'list'], true)
            ? $viewMode
            : (string) $this->settings->get('general', 'default_wine_view', 'cards');

        $wines = $request->user()
            ->userWines()
            ->with('wineVintage.wine.producer', 'wineVintage.grapes')
            ->where('preference', Preference::Top)
            ->latest('updated_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('top-wines.index', compact('wines', 'viewMode'));
    }
}
