<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Scan extends Model
{
    public const FAILED_STATUSES = ['manual_review'];

    protected $fillable = [
        'user_id',
        'image_path',
        'ocr_text',
        'status',
        'recognition_confidence',
        'matched_wine_vintage_id',
        'matched_wine_id',
        'recognition_data_json',
        'candidate_data_json',
    ];

    protected function casts(): array
    {
        return [
            'recognition_confidence' => 'float',
            'recognition_data_json' => 'array',
            'candidate_data_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function matchedWineVintage(): BelongsTo
    {
        return $this->belongsTo(WineVintage::class, 'matched_wine_vintage_id');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'matched_existing' => 'Bereits in deiner Sammlung',
            'known_wine_other_vintage' => 'Bekannter Wein, anderer Jahrgang',
            'manual_review' => 'Manuelle Pruefung noetig',
            'candidate_selection' => 'Trefferauswahl noetig',
            'new_wine' => 'Neuer Wein erkannt',
            default => $this->status,
        };
    }
}
