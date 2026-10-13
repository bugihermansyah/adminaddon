<?php

namespace Database\Seeders;

use App\Models\ResidentCsvSetting;
use App\Models\ResidentCsvSource;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ResidentCsvSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ResidentCsvSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'interval_minutes' => max(1, (int) config('services.resident_csv.interval_minutes', 60)),
                'atomicity' => in_array(config('services.resident_csv.atomicity'), ['per_file', 'all_files'], true)
                    ? config('services.resident_csv.atomicity')
                    : 'per_file',
            ],
        );

        foreach (config('services.resident_csv.urls', []) as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            $sourceKey = is_string($path) ? basename($path) : '';

            if ($sourceKey === '') {
                continue;
            }

            ResidentCsvSource::query()->firstOrCreate(
                ['source_key' => $sourceKey],
                [
                    'url' => $url,
                    'is_enabled' => true,
                    'next_sync_at' => Carbon::now(),
                ],
            );
        }
    }
}
