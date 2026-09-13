<?php

namespace Tests\Feature;

use App\Enums\Preference;
use App\Models\GrapeVariety;
use App\Models\Producer;
use App\Models\User;
use App\Models\Wine;
use App\Models\WineVintage;
use App\Models\WineTasteFeature;
use App\Services\TasteProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasteProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_uses_only_the_current_users_ratings_and_keeps_negative_signals(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $top = $this->vintage('Top', 0.85, 'Syrah', 'Rhoene');
        $bad = $this->vintage('Bad', 0.10, 'Pinot Noir', 'Burgund');
        $candidate = $this->vintage('Candidate', 0.82, 'Syrah', 'Rhoene');

        $userA->userWines()->createMany([
            ['wine_vintage_id' => $top->id, 'preference' => Preference::Top],
            ['wine_vintage_id' => $bad->id, 'preference' => Preference::Unsuitable],
        ]);
        $userB->userWines()->create(['wine_vintage_id' => $bad->id, 'preference' => Preference::Top]);

        $service = app(TasteProfileService::class);
        $profileA = $service->buildFor($userA);
        $profileB = $service->buildFor($userB);

        $this->assertSame(2, $profileA->sample_count);
        $this->assertSame(1, $profileB->sample_count);
        $this->assertGreaterThan($profileB->profile_json['features']['body'], $profileA->profile_json['features']['body']);
        $this->assertSame(Preference::Top, $userB->userWines()->where('wine_vintage_id', $bad->id)->first()->preference);
    }

    public function test_reference_profile_scores_matching_wine_higher_than_opposite_style(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $wine = $this->vintage('Top '.$i, 0.85, 'Syrah', 'Rhoene');
            $user->userWines()->create(['wine_vintage_id' => $wine->id, 'preference' => Preference::Top]);
        }
        for ($i = 0; $i < 3; $i++) {
            $wine = $this->vintage('Bad '.$i, 0.10, 'Pinot Noir', 'Burgund');
            $user->userWines()->create(['wine_vintage_id' => $wine->id, 'preference' => Preference::Unsuitable]);
        }
        $good = $this->vintage('Good Candidate', 0.82, 'Syrah', 'Rhoene');
        $weak = $this->vintage('Weak Candidate', 0.10, 'Pinot Noir', 'Burgund');

        $service = app(TasteProfileService::class);
        $service->buildFor($user);
        $goodMatch = $service->match($user->fresh(['tasteProfile']), $good);
        $weakMatch = $service->match($user->fresh(['tasteProfile']), $weak);

        $this->assertGreaterThan($weakMatch['score'], $goodMatch['score']);
        $this->assertNotEmpty($goodMatch['breakdown']);
        $this->assertNotEmpty($goodMatch['similar_top_wines']);
    }

    public function test_recommendations_exclude_personal_wines_and_profile_page_is_user_scoped(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $wine = $this->vintage('Rated '.$i, 0.8, 'Merlot', 'Bordeaux');
            $user->userWines()->create(['wine_vintage_id' => $wine->id, 'preference' => Preference::Top]);
        }
        $owned = $this->vintage('Owned', 0.8, 'Merlot', 'Bordeaux');
        $unowned = $this->vintage('Suggested', 0.78, 'Merlot', 'Bordeaux');
        $user->userWines()->create(['wine_vintage_id' => $owned->id, 'preference' => Preference::Top]);

        $recommendations = app(TasteProfileService::class)->recommendations($user);

        $this->assertTrue($recommendations->contains(fn ($item) => $item['vintage']->id === $unowned->id));
        $this->assertFalse($recommendations->contains(fn ($item) => $item['vintage']->id === $owned->id));
        $this->actingAs($user)->get(route('taste-profile.show'))->assertOk()->assertSee('Mein Genussprofil');
    }

    public function test_missing_features_remain_unknown_and_reduce_match_confidence(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 3; $i++) {
            $rated = $this->vintage('Rated '.$i, 0.8, 'Merlot', 'Bordeaux');
            $user->userWines()->create(['wine_vintage_id' => $rated->id, 'preference' => Preference::Top]);
        }
        $profile = app(TasteProfileService::class)->buildFor($user);

        $candidate = $this->vintage('Unknown', 0.8, 'Merlot', 'Bordeaux');
        WineTasteFeature::query()->where('wine_vintage_id', $candidate->id)->delete();
        $candidate->update(['body' => 0.8, 'tannin' => null, 'acidity' => null, 'fruit_intensity' => null, 'oak' => null]);
        $candidate->refresh();

        $this->assertNull($candidate->tasteFeature);
        $this->assertNull($candidate->tannin);
        $this->assertNull($profile->negative_profile_json['acidity']);

        $match = app(TasteProfileService::class)->match($user->fresh(['tasteProfile']), $candidate);
        $this->assertLessThan(1, $match['confidence_value']);
        $this->assertArrayNotHasKey('acidity', $match['breakdown']);
        $this->assertNotNull($match['score']);
    }

    private function vintage(string $name, float $body, string $grape, string $region): WineVintage
    {
        $producer = Producer::query()->create(['name' => $name.' Produzent', 'normalized_name' => strtolower($name.' produzent')]);
        $wine = Wine::query()->create(['producer_id' => $producer->id, 'name' => $name, 'normalized_name' => strtolower($name), 'wine_type' => 'Rotwein', 'country' => 'Frankreich', 'region' => $region]);
        $vintage = WineVintage::query()->create(['wine_id' => $wine->id, 'vintage' => '2021', 'body' => $body, 'tannin' => $body, 'acidity' => 1 - $body, 'fruit_intensity' => $body, 'oak' => $body]);
        $vintage->grapes()->attach(GrapeVariety::query()->firstOrCreate(['name' => $grape])->id);
        return $vintage->load('wine', 'grapes', 'tasteFeature');
    }
}
