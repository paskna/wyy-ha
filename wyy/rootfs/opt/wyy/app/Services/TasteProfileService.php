<?php

namespace App\Services;

use App\Enums\Preference;
use App\Models\User;
use App\Models\UserTasteProfile;
use App\Models\WineVintage;
use Illuminate\Support\Collection;

class TasteProfileService
{
    private const PROFILE_VERSION = 1;

    private const FEATURES = [
        'body' => 'body', 'acidity' => 'acidity', 'tannin' => 'tannin', 'sweetness' => 'sweetness',
        'fruit' => 'fruit', 'oak' => 'oak', 'spice' => 'spice', 'mineral' => 'mineral',
        'earth' => 'earth', 'floral' => 'floral', 'ripeness' => 'ripeness',
        'freshness' => 'freshness', 'complexity' => 'complexity',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    public function buildFor(User $user): UserTasteProfile
    {
        $wines = $user->userWines()->with('wineVintage.tasteFeature', 'wineVintage.grapes', 'wineVintage.wine')->get();
        $rated = $wines->filter(fn ($wine) => $wine->preference !== Preference::Unrated)->values();
        $positive = $rated->filter(fn ($wine) => in_array($wine->preference, [Preference::Top, Preference::Average], true))->values();
        $negative = $rated->filter(fn ($wine) => $wine->preference === Preference::Unsuitable)->values();
        $features = collect(self::FEATURES)->mapWithKeys(fn (string $source, string $key) => [$key => $this->signedFeature($rated, $key)])->all();
        $typePreferences = $this->preferenceBreakdown($rated, fn ($wine) => array_filter([$wine->wineVintage?->wine?->wine_type]));
        $typeProfiles = collect($typePreferences)->map(function (array $type) use ($rated): array {
            $typeWines = $rated->filter(fn ($wine) => mb_strtolower((string) $wine->wineVintage?->wine?->wine_type) === mb_strtolower((string) $type['name']));
            $type['features'] = collect(self::FEATURES)->mapWithKeys(fn (string $source, string $key) => [$key => $this->signedFeature($typeWines, $key)])->all();
            return $type;
        })->values()->all();
        $confidence = $this->confidenceFor($rated->count());

        return UserTasteProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'profile_version' => self::PROFILE_VERSION,
                'profile_json' => [
                    'features' => $features,
                    'rated_count' => $rated->count(),
                    'positive_sample_count' => $positive->count(),
                    'negative_sample_count' => $negative->count(),
                    'confidence' => $confidence,
                    'status' => $this->statusFor($rated->count()),
                    'types' => $typeProfiles,
                ],
                'positive_profile_json' => $this->averageFeatures($positive),
                'negative_profile_json' => $this->averageFeatures($negative),
                'grape_preferences_json' => $this->preferenceBreakdown($rated, fn ($wine) => $wine->wineVintage?->grapes?->pluck('name')->all() ?? []),
                'region_preferences_json' => $this->preferenceBreakdown($rated, fn ($wine) => array_values(array_filter([
                    $wine->wineVintage?->wine?->country, $wine->wineVintage?->wine?->region, $wine->wineVintage?->wine?->appellation,
                ]))),
                'sample_count' => $rated->count(),
                'positive_sample_count' => $positive->count(),
                'negative_sample_count' => $negative->count(),
                'confidence' => $confidence,
                'calculated_at' => now(),
                'updated_at' => now(),
            ],
        );
    }

    public function summary(User $user): array
    {
        $profile = $user->tasteProfile ?? $this->buildFor($user);
        $data = $profile->profile_json ?? [];
        $rated = (int) ($profile->sample_count ?? $data['rated_count'] ?? 0);
        $confidence = (float) ($profile->confidence ?? 0);
        return [
            'status' => $data['status'] ?? $this->statusFor($rated), 'rated_count' => $rated,
            'top_count' => (int) ($profile->positive_sample_count ?? 0), 'unsuitable_count' => (int) ($profile->negative_sample_count ?? 0),
            'confidence' => $confidence, 'confidence_label' => $this->confidenceLabel($confidence),
            'features' => $data['features'] ?? [], 'positive_features' => $profile->positive_profile_json ?? [],
            'negative_features' => $profile->negative_profile_json ?? [], 'grapes' => $this->preferredItems($profile->grape_preferences_json ?? []),
            'regions' => $this->preferredItems($profile->region_preferences_json ?? [], 3),
            'description' => $this->describe($data['features'] ?? [], $rated),
            'needed' => max(0, $this->threshold('recommendation_at', 5) - $rated),
        ];
    }

    public function match(User $user, WineVintage $wineVintage): array
    {
        $profile = $user->tasteProfile ?? $this->buildFor($user);
        $ratedCount = (int) ($profile->sample_count ?? ($profile->profile_json['rated_count'] ?? 0));
        $minimum = $this->threshold('first_signal_at', 3);
        if ($ratedCount < $minimum) {
            return ['score' => null, 'label' => 'Noch nicht genügend Bewertungen', 'explanation' => 'Bewerte noch '.($minimum - $ratedCount).' Weine, damit die App deinen Geschmack besser einschätzen kann.', 'confidence' => 'Erste Einschätzung', 'confidence_value' => $this->confidenceFor($ratedCount), 'breakdown' => [], 'similar_top_wines' => collect()];
        }

        $wineVintage->loadMissing('tasteFeature', 'wine', 'grapes');
        $features = $profile->profile_json['features'] ?? [];
        $candidateType = (string) ($wineVintage->wine?->wine_type ?? '');
        $typeProfile = collect($profile->profile_json['types'] ?? [])->first(fn ($type) => mb_strtolower((string) ($type['name'] ?? '')) === mb_strtolower($candidateType) && (int) ($type['count'] ?? 0) >= $minimum);
        if ($typeProfile && ! empty($typeProfile['features'])) {
            $features = $typeProfile['features'];
        }
        $breakdown = [];
        $featureSum = 0.0;
        $featureWeight = 0.0;
        foreach (self::FEATURES as $key => $source) {
            $candidate = $this->featureValue($wineVintage, $key);
            $target = $features[$key] ?? null;
            if ($candidate === null || $target === null) continue;
            $part = 1 - abs($candidate - $target);
            $weight = 1 / count(self::FEATURES);
            $featureSum += $part * $weight;
            $featureWeight += $weight;
            $breakdown[$key] = round($part * 100);
        }
        $featureScore = $featureWeight > 0 ? $featureSum / $featureWeight : null;
        $grapeScore = $this->categoryScore($wineVintage->grapes->pluck('name')->all(), $profile->grape_preferences_json ?? []);
        $regionScore = $this->categoryScore(array_values(array_filter([$wineVintage->wine?->country, $wineVintage->wine?->region, $wineVintage->wine?->appellation])), $profile->region_preferences_json ?? []);
        $typeScore = $this->categoryScore(array_filter([$wineVintage->wine?->wine_type]), $profile->profile_json['types'] ?? []);
        $topSimilarity = $this->topSimilarity($user, $wineVintage);
        $weights = $this->matchWeights();
        $components = collect([
            $featureScore === null ? null : $featureScore * $weights['features'],
            $grapeScore * $weights['grapes'],
            $regionScore * $weights['regions'],
            $typeScore * $weights['type'],
            $topSimilarity * $weights['top_similarity'],
        ])->filter(fn ($value): bool => $value !== null);
        $activeWeight = ($featureScore === null ? 0 : $weights['features']) + $weights['grapes'] + $weights['regions'] + $weights['type'] + $weights['top_similarity'];
        $score = max(0, min(100, $activeWeight > 0 ? (int) round(100 * ($components->sum() / $activeWeight)) : 0));
        $dataCoverage = $featureWeight > 0 ? min(1, $featureWeight) : 0;
        $matchConfidence = min((float) ($profile->confidence ?? 0), (float) $dataCoverage);
        $breakdown['Rebsorte'] = round($grapeScore * 100); $breakdown['Herkunft'] = round($regionScore * 100); $breakdown['Weintyp'] = round($typeScore * 100);

        return ['score' => $score, 'label' => $this->labelFor($score), 'explanation' => $this->explanation($score, $breakdown, $topSimilarity), 'confidence' => $this->confidenceLabel($matchConfidence), 'confidence_value' => $matchConfidence, 'breakdown' => $breakdown, 'similar_top_wines' => $this->topSimilarityWines($user, $wineVintage)];
    }

    public function recommendations(User $user, int $limit = 5, ?string $type = null): Collection
    {
        $profile = $user->tasteProfile ?? $this->buildFor($user);
        if ((int) ($profile->sample_count ?? 0) < $this->threshold('recommendation_at', 5)) return collect();
        return WineVintage::query()->whereDoesntHave('userWines', fn ($query) => $query->where('user_id', $user->id))
            ->when($type, fn ($query) => $query->whereHas('wine', fn ($wine) => $wine->where('wine_type', $type)))
            ->with(['wine.producer', 'tasteFeature', 'grapes'])->get()
            ->map(function (WineVintage $vintage) use ($user): array { $match = $this->match($user, $vintage); return ['vintage' => $vintage, 'score' => $match['score'] === null ? null : $match['score'] / 100, 'match' => $match]; })
            ->filter(fn (array $item) => $item['score'] !== null)->sortByDesc('score')->take($limit)->values();
    }

    private function signedFeature(Collection $wines, string $key): ?float
    {
        $weighted = 0.0; $weightTotal = 0.0;
        foreach ($wines as $wine) {
            $value = $this->featureValue($wine->wineVintage, $key); if ($value === null) continue;
            $weight = $this->ratingWeight($wine->preference);
            $weighted += $weight * ($value - 0.5);
            $weightTotal += abs($weight);
        }
        return $weightTotal > 0 ? round(max(0, min(1, 0.5 + ($weighted / $weightTotal))), 3) : null;
    }

    private function averageFeatures(Collection $wines): array
    {
        return collect(self::FEATURES)->mapWithKeys(function (string $source, string $key) use ($wines): array {
            $values = $wines->map(fn ($wine) => $this->featureValue($wine->wineVintage, $key))->filter(fn ($value) => $value !== null);
            return [$key => $values->isEmpty() ? null : round((float) $values->avg(), 3)];
        })->all();
    }

    private function preferenceBreakdown(Collection $wines, callable $values): array
    {
        $groups = [];
        foreach ($wines as $wine) {
            $signal = $this->ratingWeight($wine->preference);
            foreach (array_unique(array_filter($values($wine))) as $value) {
                $key = mb_strtolower(trim((string) $value)); if ($key === '') continue;
                $groups[$key]['name'] = (string) $value; $groups[$key]['count'] = ($groups[$key]['count'] ?? 0) + 1;
                $groups[$key]['top'] = ($groups[$key]['top'] ?? 0) + ($wine->preference === Preference::Top ? 1 : 0);
                $groups[$key]['average'] = ($groups[$key]['average'] ?? 0) + ($wine->preference === Preference::Average ? 1 : 0);
                $groups[$key]['unsuitable'] = ($groups[$key]['unsuitable'] ?? 0) + ($wine->preference === Preference::Unsuitable ? 1 : 0);
                $groups[$key]['signal'] = ($groups[$key]['signal'] ?? 0) + $signal;
            }
        }
        foreach ($groups as &$group) { $group['affinity'] = round(max(-1, min(1, $group['signal'] / max(1, $group['count']))), 3); $group['confidence'] = round(min(1, $group['count'] / 5), 3); unset($group['signal']); }
        unset($group); return array_values($groups);
    }

    private function preferredItems(array $items, int $limit = 5): array
    {
        return collect($items)->filter(fn ($item) => ($item['confidence'] ?? 0) >= 0.2 && ($item['affinity'] ?? 0) > 0)->sortByDesc(fn ($item) => ($item['affinity'] ?? 0) * ($item['confidence'] ?? 0))->take($limit)->values()->all();
    }

    private function categoryScore(array $values, array $preferences): float
    {
        $matches = collect($preferences)->filter(fn ($item) => collect($values)->contains(fn ($value) => mb_strtolower(trim((string) $value)) === mb_strtolower(trim((string) ($item['name'] ?? '')))));
        return $matches->isEmpty() ? 0.5 : (float) max(0, min(1, 0.5 + $matches->avg(fn ($item) => (($item['affinity'] ?? 0) * ($item['confidence'] ?? 0)) / 2)));
    }

    private function topSimilarity(User $user, WineVintage $candidate): float
    {
        $tops = $user->userWines()->where('preference', Preference::Top)->with('wineVintage.tasteFeature', 'wineVintage.wine', 'wineVintage.grapes')->get();
        return $tops->isEmpty() ? 0.5 : (float) $tops->map(fn ($wine) => $this->featureSimilarity($wine->wineVintage, $candidate))->max();
    }

    private function topSimilarityWines(User $user, WineVintage $candidate): Collection
    {
        return $user->userWines()->where('preference', Preference::Top)->with('wineVintage.wine.producer', 'wineVintage.tasteFeature', 'wineVintage.grapes')->get()->map(fn ($wine) => ['wine' => $wine, 'score' => round($this->featureSimilarity($wine->wineVintage, $candidate) * 100)])->sortByDesc('score')->take(3)->values();
    }

    private function featureSimilarity(WineVintage $a, WineVintage $b): float
    {
        $values = collect(self::FEATURES)->map(function (string $source, string $key) use ($a, $b) { $left = $this->featureValue($a, $key); $right = $this->featureValue($b, $key); return $left === null || $right === null ? null : 1 - abs($left - $right); })->filter(fn ($value) => $value !== null);
        return $values->isEmpty() ? 0.0 : (float) $values->avg();
    }

    private function featureValue(?WineVintage $vintage, string $key): ?float
    {
        if (! $vintage) return null;
        $fallback = $key === 'fruit' ? $vintage->fruit_intensity : ($key === 'earth' ? $vintage->earthy : $vintage->{$key} ?? null);
        $value = $vintage->tasteFeature?->{$key} ?? $fallback;
        return $value === null ? null : max(0, min(1, (float) $value));
    }

    private function ratingWeight(Preference $preference): float
    {
        return match ($preference) { Preference::Top => (float) $this->settings->get('taste_profile', 'top_weight', 1.0), Preference::Average => (float) $this->settings->get('taste_profile', 'average_weight', 0.25), Preference::Unsuitable => (float) $this->settings->get('taste_profile', 'unsuitable_weight', -1.0), default => 0.0 };
    }

    private function matchWeights(): array
    {
        return ['features' => (float) $this->settings->get('taste_profile', 'feature_weight', 0.45), 'grapes' => (float) $this->settings->get('taste_profile', 'grape_weight', 0.20), 'regions' => (float) $this->settings->get('taste_profile', 'region_weight', 0.10), 'type' => (float) $this->settings->get('taste_profile', 'type_weight', 0.10), 'top_similarity' => (float) $this->settings->get('taste_profile', 'top_wine_similarity_weight', 0.15)];
    }

    private function threshold(string $key, int $default): int { return (int) $this->settings->getWithFallback('taste_profile', $key, config('wine.taste_profile.'.$key), $default); }
    private function confidenceFor(int $count): float { return round(min(1, $count / max(1, $this->threshold('mature_profile_at', 10))), 3); }
    private function statusFor(int $count): string { return match (true) { $count >= 20 => 'Sehr belastbares Profil', $count >= $this->threshold('mature_profile_at', 10) => 'Gutes Profil', $count >= $this->threshold('recommendation_at', 5) => 'Persönliches Profil', $count >= $this->threshold('first_signal_at', 3) => 'Erste Tendenz', default => 'Noch keine verlässliche Tendenz' }; }
    private function confidenceLabel(float $confidence): string { return match (true) { $confidence >= 0.8 => 'hoch', $confidence >= 0.4 => 'mittel', default => 'niedrig' }; }
    private function labelFor(int $score): string { return match (true) { $score >= 85 => 'Sehr passend', $score >= 70 => 'Passend', $score >= 50 => 'Neutral', $score >= 30 => 'Eher nicht passend', default => 'Unpassend' }; }
    private function explanation(int $score, array $breakdown, float $topSimilarity): string
    {
        $best = collect($breakdown)->sortDesc()->keys()->take(3)->implode(', ');
        return $score >= 70 ? 'Dieser Wein passt vor allem bei '.($best ?: 'mehreren Geschmacksmerkmalen').' zu deinem bisherigen Profil.'.($topSimilarity >= 0.75 ? ' Er ähnelt mehreren deiner Top-Weine.' : '') : 'Dieser Wein weicht bei '.($best ?: 'einigen Merkmalen').' von deinen bisherigen Vorlieben ab. Die Einschätzung bleibt eine persönliche Tendenz.';
    }
    private function describe(array $features, int $rated): string
    {
        if ($rated < $this->threshold('first_signal_at', 3)) return 'Bewerte noch einige Weine, damit eine nachvollziehbare Beschreibung deines Geschmacks entsteht.';
        $labels = ['body' => 'körperreiche', 'fruit' => 'fruchtbetonte', 'acidity' => 'säurebetonte', 'oak' => 'holzgeprägte', 'spice' => 'würzige'];
        $positive = collect($labels)->filter(fn ($label, $key) => ($features[$key] ?? 0.5) >= 0.62)->values()->take(3)->implode(', ');
        return $positive !== '' ? 'Du bevorzugst bisher überwiegend '.$positive.' Weine. Diese Zusammenfassung basiert auf '.$rated.' bewerteten Weinen.' : 'Dein bisheriges Profil ist noch ausgewogen und wird mit weiteren Bewertungen genauer.';
    }
}
