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
use App\Models\WineVintage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuthAndScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_is_available(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertDontSee('demo@wein-assistent.test')
            ->assertDontSee('Demo-Zugang');
    }

    public function test_authenticated_user_can_create_a_scan(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scannen', [
            'image' => UploadedFile::fake()->image('chateau-figeac-2020.jpg'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('scans', 1);
    }

    public function test_scan_without_configured_provider_falls_back_to_manual_review_without_fake_wine_data(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/scannen', [
            'image' => UploadedFile::fake()->image('anything.jpg'),
        ]);

        $response->assertRedirect();

        $scan = Scan::query()->firstOrFail();

        $this->assertSame('manual_review', $scan->status);
        $this->assertNull($scan->recognition_data_json['producer'] ?? null);
        $this->assertStringContainsString('manuell erfassen', $scan->recognition_data_json['message'] ?? '');
    }

    public function test_heic_upload_returns_a_clear_message_when_the_server_cannot_decode_heic(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->create('label.heic', 10, 'image/heic'),
        ]);

        if (! app(\App\Services\ScanService::class)->supportsHeic()) {
            $response->assertSessionHasErrors('image');
            $this->assertDatabaseCount('scans', 0);
        } else {
            $response->assertRedirect();
        }
    }

    public function test_openai_provider_can_recognize_label_when_configured(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'producer' => 'Chateau Test',
                    'wine_name' => 'Reserve',
                    'vintage' => '2021',
                    'country' => 'Frankreich',
                    'region' => 'Bordeaux',
                    'appellation' => 'Bordeaux Superieur',
                    'wine_type' => 'Rotwein',
                    'grape_varieties' => ['Merlot'],
                    'visible_text' => 'Chateau Test Reserve 2021',
                    'confidence' => 0.91,
                ], JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => 'sk-proj-123456789ABCDE',
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ])->assertRedirect();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('label.jpg'),
        ])->assertRedirect();

        $scan = Scan::query()->firstOrFail();

        $this->assertSame('new_wine', $scan->status);
        $this->assertSame('Chateau Test', $scan->recognition_data_json['producer']);
        $this->assertSame('Reserve', $scan->recognition_data_json['wine_name']);
    }

    public function test_secure_global_match_skips_external_research_and_keeps_personal_relation_scoped(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $producer = Producer::create(['name' => 'Cache Producer', 'normalized_name' => 'cache producer']);
        $wine = Wine::create(['producer_id' => $producer->id, 'name' => 'Cache Cuvée', 'normalized_name' => 'cache cuvee', 'country' => 'Frankreich', 'region' => 'Bordeaux']);
        $vintage = WineVintage::create(['wine_id' => $wine->id, 'vintage' => '2021']);

        $this->configureRecognitionProviders($admin);
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
                'producer' => 'Cache Producer', 'wine_name' => 'Cache Cuvée', 'vintage' => '2021', 'country' => 'Frankreich', 'region' => 'Bordeaux', 'confidence' => 0.95,
            ], JSON_THROW_ON_ERROR)], 200),
            'https://wine.test/*' => Http::response(['producer' => 'Wrong Provider Result'], 200),
        ]);

        $scanLocation = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('cache.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scan = Scan::query()->findOrFail((int) basename($scanLocation));
        $this->assertSame('matched_existing', $scan->status);
        $this->assertSame($vintage->id, $scan->matched_wine_vintage_id);
        $this->assertFalse($scan->recognition_data_json['external_research_used']);
        $this->assertSame('skipped_local_match', $scan->recognition_data_json['enrichment_status']);
        Http::assertNotSent(fn ($request) => str_starts_with($request->url(), 'https://wine.test'));
        $this->assertSame(1, UserWine::query()->where('user_id', $user->id)->count());
    }

    public function test_unknown_global_wine_uses_external_provider_before_it_is_saved_globally(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $this->configureRecognitionProviders($admin);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode([
                'producer' => 'Producer ABC', 'wine_name' => 'Reserva Especial', 'vintage' => '2021', 'confidence' => 0.72,
            ], JSON_THROW_ON_ERROR)], 200),
            'https://wine.test/*' => Http::response([
                'producer' => 'Producer ABC', 'wine_name' => 'Reserva Especial', 'vintage' => '2021', 'country' => 'Spanien', 'region' => 'Rioja', 'confidence' => 0.94, 'source_url' => 'https://wine.test/abc', 'body' => 0.8,
            ], 200),
        ]);

        $scanLocation = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('unknown.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');
        $scanId = (int) basename($scanLocation);
        $scan = Scan::query()->findOrFail($scanId);

        $this->assertSame('success', $scan->recognition_data_json['enrichment_status']);
        $this->assertTrue($scan->recognition_data_json['external_research_used']);
        Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://wine.test'));

        $this->actingAs($user)->post(route('scans.save', $scanId), [
            'producer' => 'Producer ABC', 'wine_name' => 'Reserva Especial', 'vintage' => '2021', 'country' => 'Spanien', 'region' => 'Rioja', 'wine_type' => 'Rotwein', 'preference' => Preference::Top->value,
        ])->assertRedirect();

        $this->assertSame(1, WineVintage::query()->count());
        $this->assertDatabaseHas('wine_sources', ['source_url' => 'https://wine.test/abc']);
    }

    public function test_uncertain_local_match_is_verified_externally(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $producer = Producer::create(['name' => 'Old Producer', 'normalized_name' => 'old producer']);
        $wine = Wine::create(['producer_id' => $producer->id, 'name' => 'Old Cuvée', 'normalized_name' => 'old cuvee', 'country' => 'Frankreich', 'region' => 'Bordeaux']);
        WineVintage::create(['wine_id' => $wine->id, 'vintage' => '2020']);
        $this->configureRecognitionProviders($admin);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode(['producer' => 'Old Producer', 'wine_name' => 'Old Cuvée', 'vintage' => '2021', 'country' => 'Frankreich', 'region' => 'Bordeaux', 'confidence' => 0.7], JSON_THROW_ON_ERROR)], 200),
            'https://wine.test/*' => Http::response(['producer' => 'Verified Producer', 'wine_name' => 'Verified Cuvée', 'vintage' => '2021', 'confidence' => 0.96], 200),
        ]);

        $location = $this->actingAs($user)->post(route('scans.store'), ['image' => UploadedFile::fake()->image('uncertain.jpg')])->assertRedirect()->baseResponse->headers->get('Location');
        $scan = Scan::query()->findOrFail((int) basename($location));

        $this->assertTrue($scan->recognition_data_json['external_research_used']);
        $this->assertSame('Verified Cuvée', $scan->recognition_data_json['wine_name']);
    }

    public function test_multiple_external_candidates_do_not_create_an_automatic_global_match(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $this->configureRecognitionProviders($admin);

        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response(['output_text' => json_encode(['producer' => 'Ambiguous Producer', 'wine_name' => 'Reserve', 'vintage' => '2021', 'confidence' => 0.55], JSON_THROW_ON_ERROR)], 200),
            'https://wine.test/*' => Http::response(['candidates' => [
                ['producer' => 'Ambiguous Producer', 'wine_name' => 'Reserve A', 'vintage' => '2021'],
                ['producer' => 'Ambiguous Producer', 'wine_name' => 'Reserve B', 'vintage' => '2021'],
            ]], 200),
        ]);

        $location = $this->actingAs($user)->post(route('scans.store'), ['image' => UploadedFile::fake()->image('ambiguous.jpg')])->assertRedirect()->baseResponse->headers->get('Location');
        $scan = Scan::query()->findOrFail((int) basename($location));

        $this->assertSame('candidate_selection', $scan->status);
        $this->assertCount(2, $scan->recognition_data_json['external_candidates']);
        $this->assertNull($scan->matched_wine_vintage_id);
        $this->assertSame(0, WineVintage::query()->count());
    }

    private function configureRecognitionProviders(User $admin): void
    {
        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1', 'api_key' => 'sk-test', 'image_model' => 'gpt-4.1-mini', 'text_model' => 'gpt-4.1-mini', 'structuring_model' => 'gpt-4.1-mini', 'timeout' => 10, 'retries' => 0, 'priority' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.integrations.update', 'wine_provider'), [
            'enabled' => '1', 'api_key' => 'provider-test', 'endpoint' => 'https://wine.test/search', 'region' => 'EU', 'timeout' => 10, 'cache_minutes' => 60, 'priority' => 1,
        ])->assertRedirect();
    }

    public function test_google_vision_can_recognize_label_when_ocr_is_enabled(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://vision.googleapis.com/*' => Http::response([
                'responses' => [[
                    'fullTextAnnotation' => [
                        'text' => "Chateau Test\nReserve 2021\nBordeaux",
                    ],
                ]],
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.integrations.update', 'google_vision'), [
            'enabled' => '1',
            'credentials_json' => '{"api_key":"vision-key-123"}',
            'endpoint' => 'https://vision.googleapis.com/v1/images:annotate',
            'ocr_enabled' => '1',
            'timeout' => 8,
            'priority' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.settings.update', 'scan'), [
            'max_image_size' => 8192,
            'jpeg_quality' => 82,
            'max_image_dimension' => 2200,
            'provider_timeout' => 8,
            'retry_attempts' => 0,
            'use_ocr' => '1',
        ])->assertRedirect();

        $user = User::factory()->create();

        $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('label.jpg'),
        ])->assertRedirect();

        $scan = Scan::query()->firstOrFail();

        $this->assertSame('new_wine', $scan->status);
        $this->assertSame('google_vision', $scan->recognition_data_json['provider']);
        $this->assertSame('Chateau Test', $scan->recognition_data_json['producer']);
        $this->assertStringContainsString('Reserve', (string) $scan->recognition_data_json['wine_name']);
    }

    public function test_scan_save_skips_external_enrichment_when_disabled(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::response([
                'output_text' => json_encode([
                    'producer' => 'Chateau Test',
                    'wine_name' => 'Reserve',
                    'vintage' => '2021',
                    'country' => 'Frankreich',
                    'region' => 'Bordeaux',
                    'appellation' => null,
                    'wine_type' => 'Rotwein',
                    'grape_varieties' => [],
                    'visible_text' => 'Chateau Test Reserve 2021',
                    'confidence' => 0.9,
                ], JSON_THROW_ON_ERROR),
            ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);

        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => 'sk-proj-123456789ABCDE',
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ])->assertRedirect();

        $this->actingAs($admin)->put(route('admin.settings.update', 'scan'), [
            'max_image_size' => 8192,
            'jpeg_quality' => 82,
            'max_image_dimension' => 2200,
            'provider_timeout' => 10,
            'retry_attempts' => 1,
            'use_ocr' => '1',
            'external_research' => '1',
            'auto_enrichment' => '0',
        ])->assertRedirect();

        $user = User::factory()->create();

        $redirect = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('label.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanId = (int) basename($redirect);

        Http::preventStrayRequests();

        $this->actingAs($user)->post(route('scans.save', $scanId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Ohne externe Anreicherung',
            'quantity' => 1,
        ])->assertRedirect();

        $scan = Scan::query()->findOrFail($scanId);

        $this->assertSame('disabled', $scan->recognition_data_json['enrichment_status'] ?? null);
        $this->assertDatabaseHas('wine_vintages', [
            'id' => $scan->matched_wine_vintage_id,
            'description' => null,
        ]);
    }

    public function test_profile_email_update_rejects_duplicate_email_cleanly(): void
    {
        $userA = User::factory()->create(['email' => 'a@example.test']);
        $userB = User::factory()->create(['email' => 'b@example.test']);

        $this->actingAs($userA)->patch(route('profile.update'), [
            'name' => $userA->name,
            'email' => $userB->email,
        ])->assertSessionHasErrors('email');
    }

    public function test_user_can_update_quantity_from_wine_detail(): void
    {
        $user = User::factory()->create();
        Storage::fake('local');

        $scanRedirect = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('chateau-figeac-2020.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $scanId = (int) basename($scanRedirect);

        $this->actingAs($user)->post(route('scans.save', $scanId), [
            'producer' => 'Chateau Figeac',
            'wine_name' => 'Grand Vin',
            'vintage' => '2020',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Lagerwein',
        ])->assertRedirect();

        $userWine = UserWine::query()->firstOrFail();

        $this->actingAs($user)->patch(route('wines.quantity', $userWine->wine_vintage_id), [
            'quantity' => 4,
        ])->assertRedirect();

        $this->assertDatabaseHas('user_wines', [
            'id' => $userWine->id,
            'quantity' => 4,
        ]);
    }

    public function test_rescanning_existing_personal_wine_increments_scan_count_only_once_per_new_scan(): void
    {
        Storage::fake('local');
        Http::fake([
            'https://api.openai.com/v1/responses' => Http::sequence()
                ->push([
                    'output_text' => json_encode([
                        'producer' => 'Chateau Test',
                        'wine_name' => 'Reserve',
                        'vintage' => '2021',
                        'country' => 'Frankreich',
                        'region' => 'Bordeaux',
                        'appellation' => null,
                        'wine_type' => 'Rotwein',
                        'grape_varieties' => [],
                        'visible_text' => 'Chateau Test Reserve 2021',
                        'confidence' => 0.9,
                    ], JSON_THROW_ON_ERROR),
                ], 200)
                ->push([
                    'output_text' => json_encode([
                        'country' => 'Frankreich',
                        'region' => 'Bordeaux',
                        'description' => 'Strukturiert',
                        'confidence' => 0.8,
                    ], JSON_THROW_ON_ERROR),
                ], 200)
                ->push([
                    'output_text' => json_encode([
                        'producer' => 'Chateau Test',
                        'wine_name' => 'Reserve',
                        'vintage' => '2021',
                        'country' => 'Frankreich',
                        'region' => 'Bordeaux',
                        'appellation' => null,
                        'wine_type' => 'Rotwein',
                        'grape_varieties' => [],
                        'visible_text' => 'Chateau Test Reserve 2021',
                        'confidence' => 0.9,
                    ], JSON_THROW_ON_ERROR),
                ], 200),
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin, 'status' => UserStatus::Active]);
        $this->actingAs($admin)->put(route('admin.integrations.update', 'openai'), [
            'enabled' => '1',
            'api_key' => 'sk-proj-123456789ABCDE',
            'image_model' => 'gpt-4.1-mini',
            'text_model' => 'gpt-4.1-mini',
            'structuring_model' => 'gpt-4.1-mini',
            'timeout' => 10,
            'retries' => 1,
            'priority' => 1,
        ]);

        $user = User::factory()->create();

        $firstRedirect = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('first.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $firstScanId = (int) basename($firstRedirect);

        $this->actingAs($user)->post(route('scans.save', $firstScanId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Schon bekannt',
            'quantity' => 2,
        ])->assertRedirect();

        $userWine = UserWine::query()->firstOrFail();
        $this->assertSame(1, $userWine->scan_count);

        $secondRedirect = $this->actingAs($user)->post(route('scans.store'), [
            'image' => UploadedFile::fake()->image('second.jpg'),
        ])->assertRedirect()->baseResponse->headers->get('Location');

        $secondScanId = (int) basename($secondRedirect);

        $this->actingAs($user)->post(route('scans.save', $secondScanId), [
            'producer' => 'Chateau Test',
            'wine_name' => 'Reserve',
            'vintage' => '2021',
            'country' => 'Frankreich',
            'region' => 'Bordeaux',
            'wine_type' => 'Rotwein',
            'preference' => Preference::Top->value,
            'personal_note' => 'Schon bekannt',
            'quantity' => 2,
        ])->assertRedirect();

        $this->assertSame(2, $userWine->fresh()->scan_count);
    }

    public function test_more_page_counts_manual_review_scans_as_failed_and_shows_human_status(): void
    {
        $user = User::factory()->create();

        Scan::query()->create([
            'user_id' => $user->id,
            'image_path' => 'scans/'.$user->id.'/manual.jpg',
            'status' => 'manual_review',
            'recognition_data_json' => [],
            'candidate_data_json' => [],
        ]);

        Scan::query()->create([
            'user_id' => $user->id,
            'image_path' => 'scans/'.$user->id.'/new.jpg',
            'status' => 'new_wine',
            'recognition_data_json' => [],
            'candidate_data_json' => [],
        ]);

        $this->actingAs($user)->get(route('more.index'))
            ->assertOk()
            ->assertSee('1')
            ->assertSee('Manuelle Pruefung noetig');
    }

    public function test_scan_upload_shows_clear_message_for_unsupported_heic_like_file(): void
    {
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create(
            'iphone-image.heic',
            128,
            'image/heic',
        );

        $this->actingAs($user)->post(route('scans.store'), [
            'image' => $file,
        ])->assertSessionHasErrors('image');
    }
}
