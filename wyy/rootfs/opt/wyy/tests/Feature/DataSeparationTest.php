<?php

namespace Tests\Feature;

use App\Enums\Preference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Producer;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserWine;
use App\Models\Wine;
use App\Models\WineTasteFeature;
use App\Models\WineVintage;
use App\Services\WineMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_their_own_collection_and_top_wines(): void
    {
        [$userA, $userB, $vintage] = $this->seedSharedWine();

        UserWine::create([
            'user_id' => $userA->id,
            'wine_vintage_id' => $vintage->id,
            'preference' => Preference::Top,
            'personal_note' => 'Sehr gut',
            'quantity' => 3,
        ]);

        UserWine::create([
            'user_id' => $userB->id,
            'wine_vintage_id' => $vintage->id,
            'preference' => Preference::Unsuitable,
            'personal_note' => 'Zu schwer',
        ]);

        $this->actingAs($userA)->get(route('collection.index'))
            ->assertOk()
            ->assertSee('Sehr gut')
            ->assertDontSee('Zu schwer');

        $this->actingAs($userB)->get(route('top-wines.index'))
            ->assertOk()
            ->assertDontSee('Sehr gut');
    }

    public function test_user_cannot_open_other_users_personal_wine_or_scan_or_image_routes(): void
    {
        [$userA, $userB, $vintage] = $this->seedSharedWine();

        UserWine::create([
            'user_id' => $userA->id,
            'wine_vintage_id' => $vintage->id,
            'preference' => Preference::Top,
        ]);

        $scan = Scan::create([
            'user_id' => $userA->id,
            'image_path' => 'scans/a.jpg',
            'status' => 'matched_existing',
        ]);

        $this->actingAs($userB)->get(route('wines.show', $vintage))->assertOk()->assertDontSee('Nur fuer A');
        $this->actingAs($userB)->patch(route('wines.preference', $vintage), ['preference' => Preference::Top->value])->assertForbidden();
        $this->actingAs($userB)->get(route('scans.show', $scan))->assertForbidden();
    }

    public function test_admin_keeps_normal_personal_wine_features(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('admin-wine-2021.jpg'),
        ])->assertRedirect();

        $this->assertDatabaseCount('scans', 1);
    }

    public function test_shared_global_wine_keeps_personal_values_separate_between_two_users(): void
    {
        Storage::fake('local');
        $userA = User::factory()->create(['status' => UserStatus::Active]);
        $userB = User::factory()->create(['status' => UserStatus::Active]);

        $scanA = $this->actingAs($userA)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('chateau-test-2021.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanAId = (int) basename($scanA);

        $this->actingAs($userA)->post(route('scans.save', $scanAId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Chateau Test',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Sehr gut',
        ])->assertRedirect();

        $vintage = WineVintage::query()->firstOrFail();

        $this->actingAs($userA)->patch(route('wines.quantity', $vintage), [
            'quantity' => 3,
        ])->assertRedirect();

        $scanB = $this->actingAs($userB)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('chateau-test-2021.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanBId = (int) basename($scanB);

        $this->actingAs($userB)->post(route('scans.save', $scanBId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Chateau Test',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Unsuitable->value,
            'personal_note' => '',
        ])->assertRedirect();

        $userAWine = UserWine::query()->where('user_id', $userA->id)->where('wine_vintage_id', $vintage->id)->firstOrFail();
        $userBWine = UserWine::query()->where('user_id', $userB->id)->where('wine_vintage_id', $vintage->id)->firstOrFail();

        $this->assertSame(Preference::Top, $userAWine->preference);
        $this->assertSame('Sehr gut', $userAWine->personal_note);
        $this->assertSame(3, $userAWine->quantity);

        $this->assertSame(Preference::Unsuitable, $userBWine->preference);
        $this->assertNull($userBWine->personal_note);
        $this->assertNull($userBWine->quantity);

        $this->actingAs($userB)->get(route('wines.show', $vintage))
            ->assertOk()
            ->assertDontSee('Sehr gut')
            ->assertSee('Unpassend');
    }

    public function test_global_matching_finds_another_users_wine_without_loading_personal_data(): void
    {
        [$userA, $userB, $vintage] = $this->seedSharedWine();

        UserWine::create([
            'user_id' => $userA->id,
            'wine_vintage_id' => $vintage->id,
            'preference' => Preference::Top,
            'personal_note' => 'Nur fuer A',
            'quantity' => 4,
        ]);

        $result = app(WineMatchingService::class)->findMatches($userB, [
            'producer' => 'Shared Producer',
            'wine_name' => 'Shared Wine',
            'vintage' => '2021',
            'region' => 'Bordeaux',
        ]);

        $this->assertSame($vintage->id, $result['exact']['vintage']->id);
        $this->assertSame(0, $result['exact']['vintage']->userWines->count());
        $this->assertSame(1, UserWine::query()->count());
    }

    public function test_global_recommendation_can_be_opened_and_added_without_duplicate(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $rated = $this->seedSharedWine()[2];
            $user->userWines()->create(['wine_vintage_id' => $rated->id, 'preference' => Preference::Top]);
        }
        $recommended = $this->seedSharedWine()[2];
        app(\App\Services\TasteProfileService::class)->buildFor($user);

        $this->actingAs($user)->get(route('wines.show', $recommended))
            ->assertOk()
            ->assertSee('Noch nicht in deiner Sammlung');

        $this->actingAs($user)->post(route('wines.collection.add', $recommended))
            ->assertRedirect(route('wines.show', $recommended));
        $this->actingAs($user)->post(route('wines.collection.add', $recommended))
            ->assertRedirect(route('wines.show', $recommended));

        $this->assertSame(1, UserWine::query()->where('user_id', $user->id)->where('wine_vintage_id', $recommended->id)->count());
    }

    public function test_user_is_logged_out_if_account_gets_deactivated_during_session(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $this->actingAs($admin)->patch(route('admin.users.status', $user), [
            'status' => UserStatus::Inactive->value,
        ])->assertRedirect();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertRedirect(route('login'));
    }

    public function test_same_wine_with_different_vintage_is_not_treated_as_existing_same_vintage(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['status' => UserStatus::Active]);

        $scanA = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('first.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanAId = (int) basename($scanA);

        $this->actingAs($user)->post(route('scans.save', $scanAId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2020',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Erster Jahrgang',
        ])->assertRedirect();

        $scanB = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('second.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanBId = (int) basename($scanB);

        $this->actingAs($user)->post(route('scans.save', $scanBId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Average->value,
            'personal_note' => 'Zweiter Jahrgang',
        ])->assertRedirect();

        $this->assertDatabaseHas('wine_vintages', ['vintage' => '2020']);
        $this->assertDatabaseHas('wine_vintages', ['vintage' => '2021']);
        $this->assertSame(2, WineVintage::query()->count());
        $this->assertSame(1, Wine::query()->count());
        $this->assertSame(2, UserWine::query()->where('user_id', $user->id)->count());
    }

    private function seedSharedWine(): array
    {
        $userA = User::factory()->create(['status' => UserStatus::Active]);
        $userB = User::factory()->create(['status' => UserStatus::Active]);

        $producer = Producer::create(['name' => 'Shared Producer', 'normalized_name' => 'shared producer']);
        $wine = Wine::create([
            'producer_id' => $producer->id,
            'name' => 'Shared Wine',
            'normalized_name' => 'shared wine',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
        ]);
        $vintage = WineVintage::create([
            'wine_id' => $wine->id,
            'vintage' => '2021',
            'body' => 0.7,
            'tannin' => 0.5,
            'acidity' => 0.4,
            'fruit_intensity' => 0.6,
            'oak' => 0.3,
        ]);
        WineTasteFeature::create([
            'wine_vintage_id' => $vintage->id,
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

        return [$userA, $userB, $vintage];
    }
}
