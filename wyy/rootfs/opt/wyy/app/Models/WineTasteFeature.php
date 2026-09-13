<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WineTasteFeature extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'wine_vintage_id';

    public $incrementing = false;

    protected $fillable = [
        'wine_vintage_id',
        'body',
        'tannin',
        'acidity',
        'sweetness',
        'oak',
        'fruit',
        'mineral',
        'earth',
        'spice',
        'floral',
        'freshness',
        'ripeness',
        'complexity',
    ];

    protected function casts(): array
    {
        return array_fill_keys($this->fillable, 'float') + ['wine_vintage_id' => 'integer'];
    }

    public function wineVintage(): BelongsTo
    {
        return $this->belongsTo(WineVintage::class);
    }
}
