<?php

namespace App\Models;

use App\Enums\Preference;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWine extends Model
{
    protected $fillable = [
        'user_id',
        'wine_vintage_id',
        'preference',
        'personal_note',
        'quantity',
        'first_scanned_at',
        'last_scanned_at',
        'scan_count',
    ];

    protected function casts(): array
    {
        return [
            'preference' => Preference::class,
            'first_scanned_at' => 'datetime',
            'last_scanned_at' => 'datetime',
            'quantity' => 'integer',
            'scan_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function wineVintage(): BelongsTo
    {
        return $this->belongsTo(WineVintage::class);
    }
}
