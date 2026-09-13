<?php

namespace App\Services;

use App\Enums\Preference;
use App\Models\UserWine;
use App\Models\WineVintage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WineAdminService
{
    public function possibleDuplicates(): Collection
    {
        $vintages = WineVintage::query()->with('wine.producer')->get();
        $pairs = collect();

        foreach ($vintages as $left) {
            foreach ($vintages as $right) {
                if ($left->id >= $right->id) {
                    continue;
                }

                similar_text(
                    $left->wine->normalized_name.' '.$left->vintage,
                    $right->wine->normalized_name.' '.$right->vintage,
                    $score,
                );

                if ($score >= 82.0) {
                    $pairs->push([
                        'left' => $left,
                        'right' => $right,
                        'score' => round($score / 100, 3),
                        'reason' => 'Aehnlicher Weinname und Jahrgang',
                    ]);
                }
            }
        }

        return $pairs->sortByDesc('score')->values()->take(25);
    }

    public function mergeVintages(WineVintage $source, WineVintage $target): void
    {
        DB::transaction(function () use ($source, $target): void {
            foreach ($source->userWines as $userWine) {
                $existing = UserWine::query()->firstOrNew([
                    'user_id' => $userWine->user_id,
                    'wine_vintage_id' => $target->id,
                ]);

                $existing->preference = $existing->exists && $existing->preference !== Preference::Unrated
                    ? $existing->preference
                    : $userWine->preference;
                $existing->personal_note = $existing->personal_note ?: $userWine->personal_note;
                $existing->quantity = (int) ($existing->quantity ?? 0) + (int) ($userWine->quantity ?? 0);
                $existing->first_scanned_at = $existing->first_scanned_at
                    ? min($existing->first_scanned_at, $userWine->first_scanned_at)
                    : $userWine->first_scanned_at;
                $existing->last_scanned_at = $existing->last_scanned_at
                    ? max($existing->last_scanned_at, $userWine->last_scanned_at)
                    : $userWine->last_scanned_at;
                $existing->scan_count = (int) ($existing->scan_count ?? 0) + (int) $userWine->scan_count;
                $existing->save();

                $userWine->delete();
            }

            $source->grapes()->each(fn ($grape) => $target->grapes()->syncWithoutDetaching([$grape->id => ['percentage' => $grape->pivot->percentage]]));
            $source->sources()->update(['wine_vintage_id' => $target->id]);
            $source->images()->update(['wine_vintage_id' => $target->id]);
            $source->userWines()->delete();
            $source->loadMissing('tasteFeature');

            if ($source->tasteFeature && ! $target->tasteFeature) {
                $target->tasteFeature()->create($source->tasteFeature->only([
                    'body', 'tannin', 'acidity', 'sweetness', 'oak', 'fruit', 'mineral', 'earth', 'spice', 'floral', 'freshness', 'ripeness', 'complexity',
                ]));
                $source->tasteFeature()->delete();
            }

            DB::table('scans')->where('matched_wine_vintage_id', $source->id)->update([
                'matched_wine_vintage_id' => $target->id,
                'matched_wine_id' => $target->wine_id,
            ]);

            $source->delete();
        });
    }
}
