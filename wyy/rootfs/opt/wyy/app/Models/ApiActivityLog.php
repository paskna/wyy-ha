<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiActivityLog extends Model
{
    protected $fillable = [
        'provider',
        'action',
        'status',
        'response_time_ms',
        'http_status',
        'scan_id',
        'user_id',
        'message',
    ];

    protected function casts(): array
    {
        return [
            'response_time_ms' => 'integer',
            'http_status' => 'integer',
        ];
    }

    public function scan(): BelongsTo
    {
        return $this->belongsTo(Scan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
