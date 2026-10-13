<?php

namespace App\Models;

use Database\Factories\ResidentCsvSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ResidentCsvSetting extends Model
{
    /** @use HasFactory<ResidentCsvSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'interval_minutes',
        'atomicity',
    ];
}
