<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class WineVintage extends Model
{
    protected $fillable = [
        'wine_id',
        'vintage',
        'alcohol',
        'bottle_size',
        'drinking_window_from',
        'drinking_window_to',
        'serving_temperature_min',
        'serving_temperature_max',
        'colour',
        'body',
        'tannin',
        'acidity',
        'sweetness',
        'oak',
        'fruit_intensity',
        'mineral',
        'earthy',
        'spicy',
        'floral',
        'confidence_score',
        'description',
        'pairing_suggestions',
    ];

    protected function casts(): array
    {
        return [
            'drinking_window_from' => 'date',
            'drinking_window_to' => 'date',
            'alcohol' => 'decimal:1',
            'body' => 'float',
            'tannin' => 'float',
            'acidity' => 'float',
            'sweetness' => 'float',
            'oak' => 'float',
            'fruit_intensity' => 'float',
            'mineral' => 'float',
            'earthy' => 'float',
            'spicy' => 'float',
            'floral' => 'float',
            'confidence_score' => 'float',
        ];
    }

    public function wine(): BelongsTo
    {
        return $this->belongsTo(Wine::class);
    }

    public function grapes(): BelongsToMany
    {
        return $this->belongsToMany(GrapeVariety::class, 'wine_vintage_grapes')
            ->withPivot('percentage')
            ->withTimestamps();
    }

    public function userWines(): HasMany
    {
        return $this->hasMany(UserWine::class);
    }

    public function tasteFeature(): HasOne
    {
        return $this->hasOne(WineTasteFeature::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(WineSource::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(WineImage::class);
    }
}
