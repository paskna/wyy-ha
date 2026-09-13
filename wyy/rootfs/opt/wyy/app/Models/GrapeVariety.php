<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class GrapeVariety extends Model
{
    public $timestamps = false;

    protected $fillable = ['name'];

    public function vintages(): BelongsToMany
    {
        return $this->belongsToMany(WineVintage::class, 'wine_vintage_grapes')
            ->withPivot('percentage')
            ->withTimestamps();
    }
}
