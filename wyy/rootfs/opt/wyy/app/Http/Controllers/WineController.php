<?php

namespace App\Http\Controllers;

use App\Enums\Preference;
use App\Models\UserWine;
use App\Models\WineImage;
use App\Models\WineVintage;
use App\Services\TasteProfileService;
use App\Services\WineSimilarityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WineController extends Controller
{
    public function show(Request $request, WineVintage $wineVintage, TasteProfileService $tasteProfileService, WineSimilarityService $similarityService): View
    {
        $this->authorize('view', $wineVintage);

        $wineVintage->load([
            'wine.producer',
            'grapes',
            'tasteFeature',
            'sources',
            'userWines' => fn ($query) => $query->where('user_id', $request->user()->id),
        ]);

        $userWine = $wineVintage->userWines->first();
        $tasteMatch = $tasteProfileService->match($request->user(), $wineVintage);
        $similar = $similarityService->similarFor($request->user(), $wineVintage);
        $image = WineImage::query()->where('wine_vintage_id', $wineVintage->id)->where('user_id', $request->user()->id)->latest('created_at')->first();

        return view('wines.show', compact('wineVintage', 'userWine', 'tasteMatch', 'similar', 'image'));
    }

    public function addToCollection(Request $request, WineVintage $wineVintage): RedirectResponse
    {
        $this->authorize('view', $wineVintage);

        app(\App\Services\ScanService::class)->touchUserWine($request->user(), $wineVintage);

        return redirect()->route('wines.show', $wineVintage)->with('status', 'Wein wurde in deine Sammlung uebernommen.');
    }

    public function updatePreference(Request $request, WineVintage $wineVintage, TasteProfileService $tasteProfileService): RedirectResponse
    {
        $this->authorize('update', $wineVintage);
        $data = $request->validate([
            'preference' => ['required', 'string', 'in:'.collect(Preference::cases())->pluck('value')->implode(',')],
        ]);

        UserWine::query()->where('user_id', $request->user()->id)->where('wine_vintage_id', $wineVintage->id)->firstOrFail()->update($data);
        $tasteProfileService->buildFor($request->user());

        return back()->with('status', 'Bewertung gespeichert.');
    }

    public function updateNote(Request $request, WineVintage $wineVintage): RedirectResponse
    {
        $this->authorize('update', $wineVintage);
        $data = $request->validate(['personal_note' => ['nullable', 'string', 'max:2000']]);
        UserWine::query()->where('user_id', $request->user()->id)->where('wine_vintage_id', $wineVintage->id)->firstOrFail()->update($data);

        return back()->with('status', 'Notiz gespeichert.');
    }

    public function updateQuantity(Request $request, WineVintage $wineVintage): RedirectResponse
    {
        $this->authorize('update', $wineVintage);
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:0', 'max:999']]);
        UserWine::query()->where('user_id', $request->user()->id)->where('wine_vintage_id', $wineVintage->id)->firstOrFail()->update($data);

        return back()->with('status', 'Bestand aktualisiert.');
    }
}
