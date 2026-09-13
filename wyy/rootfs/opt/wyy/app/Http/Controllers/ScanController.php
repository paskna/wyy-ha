<?php

namespace App\Http\Controllers;

use App\Enums\Preference;
use App\Models\Scan;
use App\Models\WineVintage;
use App\Services\ScanService;
use App\Services\SettingsService;
use App\Services\TasteProfileService;
use App\Services\WineSimilarityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    public function create(): View
    {
        return view('scans.create');
    }

    public function store(Request $request, ScanService $scanService): RedirectResponse
    {
        $maxSize = min(
            (int) $this->settings->get('scan', 'max_image_size', 8192),
            (int) $this->settings->get('image', 'max_upload_size', 8192),
        );

        $data = $request->validate(
            [
                'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.$maxSize],
            ],
            [
                'image.required' => 'Bitte waehle ein Bild fuer den Scan aus.',
                'image.mimes' => 'Dieses Bildformat wird derzeit nicht unterstuetzt. Bitte verwende JPEG, PNG, WEBP oder HEIC/HEIF.',
                'image.max' => 'Das Bild ist zu gross. Bitte reduziere die Dateigroesse und versuche es erneut.',
            ],
        );

        $scan = $scanService->createScan($request->user(), $data['image']);

        return redirect()->route('scans.show', $scan);
    }

    public function show(Request $request, Scan $scan, TasteProfileService $tasteProfileService, WineSimilarityService $similarityService): View
    {
        $this->authorize('view', $scan);

        $scan->load('matchedWineVintage.wine.producer', 'matchedWineVintage.userWines', 'matchedWineVintage.tasteFeature');
        $match = $scan->matchedWineVintage;
        $matchUserWine = $match?->userWines?->firstWhere('user_id', $request->user()->id);
        $similar = $match ? $similarityService->similarFor($request->user(), $match) : collect();
        $tasteMatch = $match ? $tasteProfileService->match($request->user(), $match) : null;
        $candidates = collect($scan->candidate_data_json ?? [])
            ->map(fn ($candidate) => [
                'vintage' => WineVintage::query()->with('wine.producer', 'userWines')->find($candidate['wine_vintage_id']),
                'score' => $candidate['score'],
            ])
            ->filter(fn ($candidate) => $candidate['vintage']);

        return view('scans.show', compact('scan', 'match', 'matchUserWine', 'similar', 'tasteMatch', 'candidates'));
    }

    public function save(Request $request, Scan $scan, ScanService $scanService): RedirectResponse
    {
        $this->authorize('update', $scan);

        $data = $request->validate([
            'producer' => ['required', 'string', 'max:255'],
            'wine_name' => ['required', 'string', 'max:255'],
            'vintage' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'max:255'],
            'region' => ['nullable', 'string', 'max:255'],
            'wine_type' => ['nullable', 'string', 'max:255'],
            'preference' => ['required', 'string', 'in:'.collect(Preference::cases())->pluck('value')->implode(',')],
            'personal_note' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:999'],
        ]);

        $vintage = $scanService->persistScanSelection($request->user(), $scan, $data);

        return redirect()->route('wines.show', $vintage)->with('status', 'Wein wurde in deine Sammlung uebernommen.');
    }
}
