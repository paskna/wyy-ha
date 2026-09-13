<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTasteProfile extends Model
{
    public $timestamps = false;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'profile_version',
        'profile_json',
        'positive_profile_json',
        'negative_profile_json',
        'grape_preferences_json',
        'region_preferences_json',
        'sample_count',
        'positive_sample_count',
        'negative_sample_count',
        'confidence',
        'calculated_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'profile_json' => 'array',
            'positive_profile_json' => 'array',
            'negative_profile_json' => 'array',
            'grape_preferences_json' => 'array',
            'region_preferences_json' => 'array',
            'profile_version' => 'integer',
            'sample_count' => 'integer',
            'positive_sample_count' => 'integer',
            'negative_sample_count' => 'integer',
            'confidence' => 'float',
            'calculated_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
