<?php

namespace Database\Seeders;

use App\Enums\Preference;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\GrapeVariety;
use App\Models\Producer;
use App\Models\User;
use App\Models\UserWine;
use App\Models\Wine;
use App\Models\WineTasteFeature;
use App\Models\WineVintage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', app()->environment(['local', 'testing']) ? 'demo@wein-assistent.test' : null);
        $adminPassword = env('ADMIN_PASSWORD', app()->environment(['local', 'testing']) ? 'password' : null);
        $adminName = env('ADMIN_NAME', 'Administrator');

        $user = User::query()->where('role', UserRole::Admin)->first();

        if (! $user && $adminEmail && $adminPassword) {
            $user = User::query()->firstOrCreate(
                ['email' => $adminEmail],
                [
                    'name' => $adminName,
                    'password' => Hash::make($adminPassword),
                    'timezone' => 'Europe/Zurich',
                    'role' => UserRole::Admin,
                    'status' => UserStatus::Active,
                ],
            );
        }

        if (! $user && app()->environment(['local', 'testing'])) {
            $user = User::factory()->create([
                'name' => 'Demo Nutzer',
                'email' => 'demo@wein-assistent.test',
                'password' => Hash::make('password'),
                'role' => UserRole::Admin,
                'status' => UserStatus::Active,
            ]);
        }

        if (! $user) {
            return;
        }

        foreach (['Cabernet Franc', 'Merlot', 'Pinot Noir', 'Chardonnay', 'Syrah'] as $grapeName) {
            GrapeVariety::query()->firstOrCreate(['name' => $grapeName]);
        }

        $producer = Producer::query()->firstOrCreate(
            ['normalized_name' => 'chateau figeac'],
            ['name' => 'Chateau Figeac', 'country' => 'Frankreich', 'region' => 'Saint-Emilion'],
        );

        $wine = Wine::query()->firstOrCreate(
            ['producer_id' => $producer->id, 'normalized_name' => 'saint emilion premier grand cru classe'],
            [
                'name' => 'Saint-Emilion Premier Grand Cru Classe',
                'wine_type' => 'Rotwein',
                'country' => 'Frankreich',
                'region' => 'Saint-Emilion',
                'appellation' => 'Saint-Emilion Grand Cru',
                'classification' => 'Premier Grand Cru Classe',
                'description' => 'Strukturiert, ruhig und mit dunkler Frucht.',
            ],
        );

        foreach ([
            ['year' => '2020', 'preference' => Preference::Top, 'note' => 'Sehr rund, wenig Saeure, dunkle Fruechte.'],
            ['year' => '2021', 'preference' => Preference::Average, 'note' => 'Solide, etwas straffer als 2020.'],
            ['year' => '2022', 'preference' => Preference::Unrated, 'note' => null],
        ] as $entry) {
            $vintage = WineVintage::query()->firstOrCreate(
                ['wine_id' => $wine->id, 'vintage' => $entry['year']],
                [
                    'colour' => 'Rotwein',
                    'body' => 0.74,
                    'tannin' => 0.58,
                    'acidity' => 0.43,
                    'sweetness' => 0.08,
                    'oak' => 0.41,
                    'fruit_intensity' => 0.68,
                    'mineral' => 0.39,
                    'earthy' => 0.28,
                    'spicy' => 0.34,
                    'floral' => 0.17,
                    'confidence_score' => 0.92,
                    'pairing_suggestions' => 'Rind, Wild, gereifter Hartkaese',
                ],
            );

            WineTasteFeature::query()->updateOrCreate(
                ['wine_vintage_id' => $vintage->id],
                [
                    'body' => 0.74,
                    'tannin' => 0.58,
                    'acidity' => 0.43,
                    'sweetness' => 0.08,
                    'oak' => 0.41,
                    'fruit' => 0.68,
                    'mineral' => 0.39,
                    'earth' => 0.28,
                    'spice' => 0.34,
                    'floral' => 0.17,
                    'freshness' => 0.46,
                    'ripeness' => 0.69,
                    'complexity' => 0.63,
                ],
            );

            UserWine::query()->updateOrCreate(
                ['user_id' => $user->id, 'wine_vintage_id' => $vintage->id],
                [
                    'preference' => $entry['preference'],
                    'personal_note' => $entry['note'],
                    'quantity' => $entry['year'] === '2020' ? 2 : null,
                    'first_scanned_at' => now()->subDays(30),
                    'last_scanned_at' => now()->subDays((int) $entry['year'] - 2019),
                    'scan_count' => $entry['year'] === '2020' ? 3 : 1,
                ],
            );
        }
    }
}
