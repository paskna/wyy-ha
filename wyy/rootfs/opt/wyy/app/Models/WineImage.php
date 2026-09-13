<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WineImage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wine_vintage_id',
        'user_id',
        'image_type',
        'original_path',
        'optimized_path',
        'label_path',
        'perceptual_hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function wineVintage(): BelongsTo
    {
        return $this->belongsTo(WineVintage::class);
    }
}
