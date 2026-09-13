<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WineSource extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wine_vintage_id',
        'source_type',
        'source_name',
        'source_url',
        'retrieved_at',
        'confidence',
        'data_json',
    ];

    protected function casts(): array
    {
        return [
            'retrieved_at' => 'datetime',
            'confidence' => 'float',
            'data_json' => 'array',
        ];
    }

    public function wineVintage(): BelongsTo
    {
        return $this->belongsTo(WineVintage::class);
    }
}
