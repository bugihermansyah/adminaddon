<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidentCsvSyncRun extends Model
{
    protected $fillable = [
        'batch_id',
        'source_key',
        'status',
        'started_at',
        'finished_at',
        'rows',
        'added',
        'updated',
        'deleted',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
