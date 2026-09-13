<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producer extends Model
{
    protected $fillable = ['name', 'normalized_name', 'country', 'region', 'website', 'description'];

    public function wines(): HasMany
    {
        return $this->hasMany(Wine::class);
    }
}
