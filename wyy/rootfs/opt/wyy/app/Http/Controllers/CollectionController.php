<?php

namespace App\Http\Controllers;

use App\Enums\Preference;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function index(Request $request): View
    {
        $perPage = max(5, (int) $this->settings->get('general', 'per_page', 12));
        $viewMode = $request->string('view')->toString();
        $viewMode = in_array($viewMode, ['cards', 'list'], true)
            ? $viewMode
            : (string) $this->settings->get('general', 'default_wine_view', 'cards');

        $query = $request->user()->userWines()->with('wineVintage.wine.producer', 'wineVintage.grapes');

        if ($search = $request->string('q')->toString()) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('personal_note', 'like', '%'.$search.'%')
                    ->orWhereHas('wineVintage.wine', fn ($wineQuery) => $wineQuery
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('region', 'like', '%'.$search.'%')
                        ->orWhere('appellation', 'like', '%'.$search.'%'))
                    ->orWhereHas('wineVintage.wine.producer', fn ($producerQuery) => $producerQuery
                        ->where('name', 'like', '%'.$search.'%'));
            });
        }

        if ($preference = $request->string('preference')->toString()) {
            $query->where('preference', $preference);
        }

        return view('collection.index', [
            'wines' => $query->latest('last_scanned_at')->paginate($perPage)->withQueryString(),
            'preferences' => Preference::cases(),
            'viewMode' => $viewMode,
        ]);
    }
}
