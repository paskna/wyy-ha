<?php

namespace Tests\Unit;

use App\Enums\Preference;
use App\Models\Producer;
use App\Models\User;
use App\Models\Wine;
use App\Models\WineTasteFeature;
use App\Models\WineVintage;
use App\Services\SettingsService;
use App\Services\TasteProfileService;
use App\Services\WineMatchingService;
use App\Services\WineNormalizationService;
use App\Services\WineSimilarityService;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WineMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_identical_wine_scores_high(): void
    {
        $service = new WineMatchingService(new WineNormalizationService, new SettingsService(new Repository(new ArrayStore)));
        $vintage = $this->makeVintage('Chateau Figeac', 'Grand Vin', '2021', 'Saint-Emilion');

        $score = $service->score($vintage, [
            'producer' => 'Château Figeac',
            'wine_name' => 'Grand Vin',
            'vintage' => '2021',
            'region' => 'Saint-Emilion',
            'visual_similarity' => 0.9,
        ]);

        $this->assertGreaterThanOrEqual(0.88, $score);
    }

    public function test_different_vintage_is_penalized(): void
    {
        $service = new WineMatchingService(new WineNormalizationService, new SettingsService(new Repository(new ArrayStore)));
        $vintage = $this->makeVintage('Chateau Figeac', 'Grand Vin', '2020', 'Saint-Emilion');

        $score = $service->score($vintage, [
            'producer' => 'Chateau Figeac',
            'wine_name' => 'Grand Vin',
            'vintage' => '2022',
            'region' => 'Saint-Emilion',
            'visual_similarity' => 0.9,
        ]);

        $this->assertLessThan(0.88, $score);
        $this->assertGreaterThan(0.55, $score);
    }

    public function test_similarity_and_taste_profile_handle_missing_taste_feature_without_exception(): void
    {
        $settings = new SettingsService(new Repository(new ArrayStore));
        $similarity = new WineSimilarityService;
        $tasteProfile = new TasteProfileService($settings);
        $user = User::factory()->create();

        $base = $this->persistVintage('Producer A', 'Wine A', '2021', 'Bordeaux');
        $candidate = $this->persistVintage('Producer B', 'Wine B', '2022', 'Bordeaux');

        WineTasteFeature::create([
            'wine_vintage_id' => $base->id,
            'body' => 0.7,
            'tannin' => 0.5,
            'acidity' => 0.4,
            'sweetness' => 0.1,
            'oak' => 0.3,
            'fruit' => 0.6,
            'mineral' => 0.2,
            'earth' => 0.2,
            'spice' => 0.2,
            'floral' => 0.2,
            'freshness' => 0.4,
            'ripeness' => 0.6,
            'complexity' => 0.5,
        ]);

        $user->userWines()->create([
            'wine_vintage_id' => $base->id,
            'preference' => Preference::Top,
        ]);

        $base->load('tasteFeature', 'wine', 'grapes');
        $candidate->load('tasteFeature', 'wine', 'grapes');

        $score = $similarity->score($base, $candidate);
        $match = $tasteProfile->match($user->fresh(['tasteProfile']), $candidate);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
        $this->assertIsArray($match);
        $this->assertArrayHasKey('label', $match);
    }

    private function makeVintage(string $producerName, string $wineName, string $year, string $region): WineVintage
    {
        $producer = new Producer(['name' => $producerName, 'normalized_name' => 'chateau figeac']);
        $wine = new Wine(['name' => $wineName, 'normalized_name' => 'grand vin', 'region' => $region]);
        $wine->setRelation('producer', $producer);

        $vintage = new WineVintage(['vintage' => $year]);
        $vintage->setRelation('wine', $wine);

        return $vintage;
    }

    private function persistVintage(string $producerName, string $wineName, string $year, string $region): WineVintage
    {
        $producer = Producer::query()->create([
            'name' => $producerName,
            'normalized_name' => app(WineNormalizationService::class)->normalize($producerName),
        ]);

        $wine = Wine::query()->create([
            'producer_id' => $producer->id,
            'name' => $wineName,
            'normalized_name' => app(WineNormalizationService::class)->normalize($wineName),
            'region' => $region,
            'country' => 'Frankreich',
            'wine_type' => 'Rotwein',
        ]);

        return WineVintage::query()->create([
            'wine_id' => $wine->id,
            'vintage' => $year,
            'body' => 0.7,
            'tannin' => 0.5,
            'acidity' => 0.4,
            'fruit_intensity' => 0.6,
            'oak' => 0.3,
        ]);
    }
}
