<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wine extends Model
{
    protected $fillable = [
        'producer_id',
        'name',
        'normalized_name',
        'wine_type',
        'country',
        'region',
        'subregion',
        'appellation',
        'classification',
        'description',
    ];

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Producer::class);
    }

    public function vintages(): HasMany
    {
        return $this->hasMany(WineVintage::class);
    }
}
