<?php

namespace App\Services;

use App\Enums\Preference;
use App\Models\GrapeVariety;
use App\Models\Producer;
use App\Models\Scan;
use App\Models\User;
use App\Models\UserWine;
use App\Models\Wine;
use App\Models\WineSource;
use App\Models\WineTasteFeature;
use App\Models\WineVintage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ScanService
{
    public function __construct(
        private readonly WineRecognitionService $recognition,
        private readonly WineEnrichmentService $enrichment,
        private readonly WineMatchingService $matching,
        private readonly WineNormalizationService $normalizer,
        private readonly TasteProfileService $tasteProfile,
        private readonly SettingsService $settings,
    ) {}

    public function createScan(User $user, UploadedFile $file): Scan
    {
        $preparedFile = $this->prepareRecognitionFile($file);

        try {
            $path = $this->storeOptimizedScanImage($user, $preparedFile);
            $recognized = $this->recognition->recognize($preparedFile, $user);
        } finally {
            $this->cleanupPreparedFile($preparedFile, $file);
        }

        $matches = filled($recognized['producer'] ?? null) && filled($recognized['wine_name'] ?? null)
            ? $this->matching->findMatches($user, $recognized)
            : ['exact' => null, 'candidates' => collect()];

        $localMatch = $matches['exact'];
        $externalResearchUsed = false;

        if ($localMatch === null && filled($recognized['producer'] ?? null) && filled($recognized['wine_name'] ?? null)) {
            $enriched = $this->enrichment->enrich($recognized);
            $externalResearchUsed = ($enriched['enrichment_status'] ?? null) === 'success';
            $recognized = $enriched;
            $matches = $this->matching->findMatches($user, $recognized);
        } elseif ($localMatch !== null) {
            $recognized['enrichment_status'] = 'skipped_local_match';
            $recognized['enrichment_message'] = 'Globaler lokaler Treffer ist ausreichend sicher; externe Recherche wurde uebersprungen.';
        } else {
            $recognized['enrichment_status'] = 'not_attempted';
            $recognized['enrichment_message'] = 'Fuer eine globale Recherche wurden noch nicht genuegend Etikettangaben erkannt.';
        }

        $matchedVintage = $matches['exact']['vintage'] ?? null;
        $knownOtherVintage = $matchedVintage ? null : $this->findKnownWineOtherVintage($user, $recognized);
        $localMatchScore = $matches['exact']['score'] ?? null;
        $externalCandidates = is_array($recognized['candidates'] ?? null) ? $recognized['candidates'] : [];

        $status = match (true) {
            $matchedVintage !== null => 'matched_existing',
            $knownOtherVintage !== null => 'known_wine_other_vintage',
            ($recognized['status'] ?? null) === 'manual_required' => 'manual_review',
            $externalCandidates !== [] => 'candidate_selection',
            $matches['candidates']->isNotEmpty() => 'candidate_selection',
            default => 'new_wine',
        };

        $scan = Scan::query()->create([
            'user_id' => $user->id,
            'image_path' => $path,
            'ocr_text' => $recognized['visible_text'] ?? null,
            'status' => $status,
            'recognition_confidence' => $recognized['confidence'] ?? 0.0,
            'matched_wine_vintage_id' => $matchedVintage?->id,
            'matched_wine_id' => $matchedVintage?->wine_id,
            'recognition_data_json' => array_merge($recognized, [
                'local_global_match_score' => $localMatchScore,
                'global_match_found' => $matchedVintage !== null,
                'external_research_used' => $externalResearchUsed,
                'external_candidates' => $externalCandidates,
                'personal_user_wine_exists' => $matchedVintage ? UserWine::query()->where('user_id', $user->id)->where('wine_vintage_id', $matchedVintage->id)->exists() : false,
                'known_other_vintage_id' => $knownOtherVintage?->id,
                'known_other_vintage_label' => $knownOtherVintage ? trim($knownOtherVintage->wine->producer->name.' '.$knownOtherVintage->wine->name.' '.($knownOtherVintage->vintage ?? '')) : null,
            ]),
            'candidate_data_json' => $matches['candidates']->map(fn ($candidate) => [
                'wine_vintage_id' => $candidate['vintage']->id,
                'score' => $candidate['score'],
            ])->all(),
        ]);

        if ($matchedVintage) {
            $this->touchUserWine($user, $matchedVintage);
        }

        return $scan;
    }

    public function persistScanSelection(User $user, Scan $scan, array $payload): WineVintage
    {
        return DB::transaction(function () use ($user, $scan, $payload) {
            $recognized = array_merge($scan->recognition_data_json ?? [], $payload);
            if (! array_key_exists('enrichment_status', $recognized)) {
                $recognized = $this->enrichment->enrich($recognized);
            }

            $producer = Producer::query()->firstOrCreate(
                ['normalized_name' => $this->normalizer->normalize($recognized['producer'])],
                ['name' => $recognized['producer'], 'country' => $recognized['country'] ?? null, 'region' => $recognized['region'] ?? null],
            );

            $wine = Wine::query()->firstOrCreate(
                ['producer_id' => $producer->id, 'normalized_name' => $this->normalizer->normalize($recognized['wine_name'])],
                [
                    'name' => $recognized['wine_name'],
                    'wine_type' => $recognized['wine_type'] ?? null,
                    'country' => $recognized['country'] ?? null,
                    'region' => $recognized['region'] ?? null,
                    'appellation' => $recognized['appellation'] ?? null,
                    'description' => $recognized['description'] ?? null,
                ],
            );
            $this->fillMissing($wine, [
                'name' => $recognized['wine_name'] ?? null,
                'wine_type' => $recognized['wine_type'] ?? null,
                'country' => $recognized['country'] ?? null,
                'region' => $recognized['region'] ?? null,
                'appellation' => $recognized['appellation'] ?? null,
                'description' => $recognized['description'] ?? null,
            ]);

            $vintage = WineVintage::query()->firstOrCreate(
                ['wine_id' => $wine->id, 'vintage' => $recognized['vintage'] ?: null],
                [
                    'colour' => $recognized['wine_type'] ?? null,
                    'body' => $recognized['body'] ?? null,
                    'tannin' => $recognized['tannin'] ?? null,
                    'acidity' => $recognized['acidity'] ?? null,
                    'sweetness' => $recognized['sweetness'] ?? null,
                    'oak' => $recognized['oak'] ?? null,
                    'fruit_intensity' => $recognized['fruit_intensity'] ?? null,
                    'mineral' => $recognized['mineral'] ?? null,
                    'earthy' => $recognized['earthy'] ?? null,
                    'spicy' => $recognized['spicy'] ?? null,
                    'floral' => $recognized['floral'] ?? null,
                    'confidence_score' => $recognized['confidence'] ?? null,
                    'description' => $recognized['description'] ?? null,
                    'pairing_suggestions' => $recognized['pairing_suggestions'] ?? null,
                ],
            );
            $this->fillMissing($vintage, [
                'colour' => $recognized['wine_type'] ?? null,
                'body' => $recognized['body'] ?? null,
                'tannin' => $recognized['tannin'] ?? null,
                'acidity' => $recognized['acidity'] ?? null,
                'sweetness' => $recognized['sweetness'] ?? null,
                'oak' => $recognized['oak'] ?? null,
                'fruit_intensity' => $recognized['fruit_intensity'] ?? null,
                'mineral' => $recognized['mineral'] ?? null,
                'earthy' => $recognized['earthy'] ?? null,
                'spicy' => $recognized['spicy'] ?? null,
                'floral' => $recognized['floral'] ?? null,
                'confidence_score' => $recognized['confidence'] ?? null,
                'description' => $recognized['description'] ?? null,
                'pairing_suggestions' => $recognized['pairing_suggestions'] ?? null,
            ]);

            $tasteFeature = WineTasteFeature::query()->firstOrNew(['wine_vintage_id' => $vintage->id]);
            $this->fillMissing($tasteFeature, [
                    'body' => $recognized['body'] ?? null,
                    'tannin' => $recognized['tannin'] ?? null,
                    'acidity' => $recognized['acidity'] ?? null,
                    'sweetness' => $recognized['sweetness'] ?? null,
                    'oak' => $recognized['oak'] ?? null,
                    'fruit' => $recognized['fruit_intensity'] ?? null,
                    'mineral' => $recognized['mineral'] ?? null,
                    'earth' => $recognized['earthy'] ?? null,
                    'spice' => $recognized['spicy'] ?? null,
                    'floral' => $recognized['floral'] ?? null,
                    'freshness' => $recognized['freshness'] ?? null,
                    'ripeness' => $recognized['ripeness'] ?? null,
                    'complexity' => $recognized['complexity'] ?? null,
            ]);

            foreach ($recognized['grape_varieties'] ?? [] as $grapeName) {
                $grape = GrapeVariety::query()->firstOrCreate(['name' => $grapeName]);
                $vintage->grapes()->syncWithoutDetaching([$grape->id => ['percentage' => null]]);
            }

            if (isset($recognized['source'])) {
                WineSource::query()->create([
                    'wine_vintage_id' => $vintage->id,
                    'source_type' => $recognized['source']['source_type'],
                    'source_name' => $recognized['source']['source_name'],
                    'source_url' => $recognized['source']['source_url'],
                    'retrieved_at' => now(),
                    'confidence' => $recognized['source']['confidence'],
                    'data_json' => $recognized,
                ]);
            }

            $userWine = $scan->matched_wine_vintage_id === $vintage->id
                ? UserWine::query()->where('user_id', $user->id)->where('wine_vintage_id', $vintage->id)->firstOrFail()
                : $this->touchUserWine($user, $vintage);
            $userWine->update([
                'preference' => $payload['preference'] ?? $userWine->preference ?? Preference::Unrated,
                'personal_note' => $payload['personal_note'] ?? $userWine->personal_note,
                'quantity' => array_key_exists('quantity', $payload) ? $payload['quantity'] : $userWine->quantity,
            ]);

            $scan->update([
                'status' => 'matched_existing',
                'matched_wine_vintage_id' => $vintage->id,
                'matched_wine_id' => $wine->id,
                'recognition_data_json' => $recognized,
            ]);

            $this->tasteProfile->buildFor($user);

            return $vintage;
        });
    }

    private function fillMissing(object $model, array $values): void
    {
        foreach ($values as $key => $value) {
            if ($value !== null && $model->getAttribute($key) === null) {
                $model->setAttribute($key, $value);
            }
        }

        if ($model->isDirty()) {
            $model->save();
        }
    }

    public function touchUserWine(User $user, WineVintage $vintage): UserWine
    {
        $userWine = UserWine::query()->firstOrCreate(
            ['user_id' => $user->id, 'wine_vintage_id' => $vintage->id],
            ['preference' => Preference::Unrated, 'first_scanned_at' => now(), 'last_scanned_at' => now(), 'scan_count' => 0],
        );

        $userWine->forceFill([
            'last_scanned_at' => now(),
            'first_scanned_at' => $userWine->first_scanned_at ?? now(),
            'scan_count' => $userWine->scan_count + 1,
        ])->save();

        return $userWine->fresh();
    }

    private function findKnownWineOtherVintage(User $user, array $recognized): ?WineVintage
    {
        if (! filled($recognized['producer'] ?? null) || ! filled($recognized['wine_name'] ?? null)) {
            return null;
        }

        return WineVintage::query()
            ->whereHas('wine.producer', fn ($query) => $query->where('normalized_name', $this->normalizer->normalize($recognized['producer'])))
            ->whereHas('wine', fn ($query) => $query->where('normalized_name', $this->normalizer->normalize($recognized['wine_name'])))
            ->with('wine.producer')
            ->first();
    }

    public function supportsHeic(): bool
    {
        if (! class_exists(\Imagick::class)) {
            return false;
        }

        try {
            return in_array('HEIC', (new \Imagick())->queryFormats('HEIC'), true);
        } catch (\Throwable) {
            return false;
        }
    }

    private function prepareRecognitionFile(UploadedFile $file): UploadedFile
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['heic', 'heif'], true)) {
            return $file;
        }

        if (! $this->supportsHeic()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'Dieses HEIC/HEIF-Bild kann auf diesem Server nicht verarbeitet werden. Bitte verwende JPEG oder PNG.',
            ]);
        }

        try {
            $imagick = new \Imagick($file->getRealPath());
            $imagick->setIteratorIndex(0);
            $imagick->setImageOrientation(\Imagick::ORIENTATION_TOPLEFT);
            $imagick->setImageFormat('jpeg');
            $imagick->setImageCompressionQuality(88);
            $imagick->stripImage();
            $temporaryPath = tempnam(sys_get_temp_dir(), 'wein-heic-');
            $written = $temporaryPath !== false && $imagick->writeImage($temporaryPath);
            $imagick->clear();
            $imagick->destroy();
        } catch (\Throwable) {
            $temporaryPath = false;
            $written = false;
        }

        if (! $written) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'image' => 'Das HEIC/HEIF-Bild konnte nicht verarbeitet werden. Bitte verwende JPEG oder PNG.',
            ]);
        }

        return new UploadedFile($temporaryPath, pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME).'.jpg', 'image/jpeg', null, true);
    }

    private function cleanupPreparedFile(UploadedFile $prepared, UploadedFile $original): void
    {
        if ($prepared->getPathname() !== $original->getPathname() && is_file($prepared->getPathname())) {
            @unlink($prepared->getPathname());
        }
    }

    private function storeOptimizedScanImage(User $user, UploadedFile $file): string
    {
        $disk = Storage::disk('local');
        $raw = @file_get_contents($file->getRealPath());
        $fallbackPath = 'scans/'.$user->id.'/'.Str::uuid()->toString().'.'.($file->guessExtension() ?: 'jpg');

        if ($raw === false) {
            $disk->putFileAs('scans/'.$user->id, $file, basename($fallbackPath));

            return $fallbackPath;
        }

        $image = function_exists('imagecreatefromstring') ? @imagecreatefromstring($raw) : false;

        if (! $image) {
            $disk->putFileAs('scans/'.$user->id, $file, basename($fallbackPath));

            return $fallbackPath;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $targetMax = min(
            (int) $this->settings->get('scan', 'max_image_dimension', 2200),
            (int) $this->settings->get('image', 'optimized_width', 1800),
        );
        $quality = (int) $this->settings->get('image', 'jpeg_quality', $this->settings->get('scan', 'jpeg_quality', 82));
        $scale = $targetMax > 0 && max($width, $height) > $targetMax ? $targetMax / max($width, $height) : 1;
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $temp = tempnam(sys_get_temp_dir(), 'scan-');
        imagejpeg($canvas, $temp, $quality);
        imagedestroy($canvas);
        imagedestroy($image);

        $filename = Str::uuid()->toString().'.jpg';
        $path = 'scans/'.$user->id.'/'.$filename;
        $stored = $temp !== false ? $disk->put($path, @file_get_contents($temp) ?: '') : false;

        if ($temp !== false) {
            @unlink($temp);
        }

        if (! $stored) {
            $disk->putFileAs('scans/'.$user->id, $file, basename($fallbackPath));

            return $fallbackPath;
        }

        return $path;
    }
}
