<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentCsvSource extends Model
{
    protected $fillable = [
        'source_key',
        'url',
        'is_enabled',
        'next_sync_at',
        'last_attempt_at',
        'last_success_at',
        'last_status',
        'last_rows',
        'last_added',
        'last_updated',
        'last_deleted',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'next_sync_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'last_success_at' => 'datetime',
        ];
    }
}
