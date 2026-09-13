<?php

namespace App\Services;

use App\Models\User;
use App\Models\WineVintage;

class WineMatchingService
{
    public function __construct(
        private readonly WineNormalizationService $normalizer,
        private readonly SettingsService $settings,
    ) {}

    public function findMatches(User $user, array $recognized): array
    {
        $candidates = WineVintage::query()
            ->with(['wine.producer', 'userWines' => fn ($query) => $query->where('user_id', $user->id)])
            ->get()
            ->map(fn (WineVintage $vintage) => ['vintage' => $vintage, 'score' => $this->score($vintage, $recognized)])
            ->sortByDesc('score')
            ->values();

        return [
            'exact' => $candidates->firstWhere('score', '>=', $this->settings->getWithFallback('matching', 'exact_threshold', config('wine.matching.exact_threshold'), 0.88)),
            'candidates' => $candidates
                ->filter(fn (array $candidate) => $candidate['score'] >= $this->settings->getWithFallback('matching', 'candidate_threshold', config('wine.matching.candidate_threshold'), 0.72))
                ->take(3)
                ->values(),
        ];
    }

    public function score(WineVintage $wineVintage, array $recognized): float
    {
        $weights = [
            'producer' => (float) $this->settings->getWithFallback('matching', 'producer_weight', config('wine.matching.weights.producer'), 0.30),
            'wine_name' => (float) $this->settings->getWithFallback('matching', 'wine_name_weight', config('wine.matching.weights.wine_name'), 0.30),
            'vintage' => (float) $this->settings->getWithFallback('matching', 'vintage_weight', config('wine.matching.weights.vintage'), 0.15),
            'region' => (float) $this->settings->getWithFallback('matching', 'region_weight', config('wine.matching.weights.region'), 0.10),
            'visual' => (float) $this->settings->getWithFallback('matching', 'visual_weight', config('wine.matching.weights.visual'), 0.10),
            'other' => (float) $this->settings->getWithFallback('matching', 'other_weight', config('wine.matching.weights.other'), 0.05),
        ];
        $producerScore = $this->stringSimilarity($wineVintage->wine->producer->normalized_name ?? '', $recognized['producer'] ?? null);
        $wineScore = $this->stringSimilarity($wineVintage->wine->normalized_name, $recognized['wine_name'] ?? null);
        $regionScore = max(
            $this->stringSimilarity($wineVintage->wine->region ?? '', $recognized['region'] ?? null),
            $this->stringSimilarity($wineVintage->wine->appellation ?? '', $recognized['appellation'] ?? null),
        );
        $vintage = $this->normalizer->vintage((string) ($recognized['vintage'] ?? ''));
        $vintageScore = $vintage === null || $wineVintage->vintage === null ? 0.5 : ((int) $wineVintage->vintage === $vintage ? 1.0 : 0.0);
        $visualScore = (float) ($recognized['visual_similarity'] ?? 0.5);
        $otherScore = $this->stringSimilarity($wineVintage->wine->country ?? '', $recognized['country'] ?? null);

        return round(
            ($producerScore * $weights['producer'])
            + ($wineScore * $weights['wine_name'])
            + ($vintageScore * $weights['vintage'])
            + ($regionScore * $weights['region'])
            + ($visualScore * $weights['visual'])
            + ($otherScore * $weights['other']),
            3,
        );
    }

    private function stringSimilarity(?string $left, ?string $right): float
    {
        $left = $this->normalizer->normalize($left);
        $right = $this->normalizer->normalize($right);

        if ($left === '' || $right === '') {
            return 0.0;
        }

        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);

        return round($percent / 100, 3);
    }
}
