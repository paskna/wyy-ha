<?php

namespace App\Services;

use App\Enums\Preference;
use App\Models\User;
use App\Models\WineVintage;

class WineSimilarityService
{
    public function similarFor(User $user, WineVintage $wineVintage, int $limit = 3)
    {
        return WineVintage::query()
            ->whereKeyNot($wineVintage->id)
            ->whereHas('userWines', fn ($query) => $query->where('user_id', $user->id))
            ->with(['wine.producer', 'userWines' => fn ($query) => $query->where('user_id', $user->id), 'tasteFeature', 'grapes'])
            ->get()
            ->map(function (WineVintage $candidate) use ($wineVintage) {
                return [
                    'vintage' => $candidate,
                    'score' => $this->score($wineVintage, $candidate),
                    'is_top' => optional($candidate->userWines->first())->preference === Preference::Top,
                ];
            })
            ->sortByDesc(fn (array $candidate) => ($candidate['is_top'] ? 1 : 0) + $candidate['score'])
            ->take($limit)
            ->values();
    }

    public function score(WineVintage $a, WineVintage $b): float
    {
        $featurePairs = [
            [$a->tasteFeature?->body ?? $a->body, $b->tasteFeature?->body ?? $b->body],
            [$a->tasteFeature?->tannin ?? $a->tannin, $b->tasteFeature?->tannin ?? $b->tannin],
            [$a->tasteFeature?->acidity ?? $a->acidity, $b->tasteFeature?->acidity ?? $b->acidity],
            [$a->tasteFeature?->fruit ?? $a->fruit_intensity, $b->tasteFeature?->fruit ?? $b->fruit_intensity],
        ];
        $featureDistances = collect($featurePairs)
            ->filter(fn (array $pair): bool => $pair[0] !== null && $pair[1] !== null)
            ->map(fn (array $pair): float => abs((float) $pair[0] - (float) $pair[1]));
        $featureScore = $featureDistances->isEmpty() ? null : (float) $featureDistances->avg();

        $origin = $a->wine->region === $b->wine->region ? 1.0 : ($a->wine->country === $b->wine->country ? 0.6 : 0.0);
        $type = $a->wine->wine_type === $b->wine->wine_type ? 1.0 : 0.0;
        $grapesA = $a->grapes->pluck('name')->map(fn ($name) => mb_strtolower($name));
        $grapesB = $b->grapes->pluck('name')->map(fn ($name) => mb_strtolower($name));
        $grapeOverlap = $grapesA->isEmpty() && $grapesB->isEmpty() ? 0.5 : $grapesA->intersect($grapesB)->count() / max(1, $grapesA->count(), $grapesB->count());

        $components = collect([
            $featureScore === null ? null : (1 - $featureScore) * 0.55,
            $origin * 0.15,
            $type * 0.10,
            $grapeOverlap * 0.20,
        ])->filter(fn ($value): bool => $value !== null);
        $weight = $featureScore === null ? 0.45 : 1.0;

        return round(max(0, min(1, $components->sum() / $weight)), 3);
    }
}
