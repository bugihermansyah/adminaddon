<?php

namespace App\Filament\Widgets;

use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServerStorageOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Storage server';

    // protected ?string $description = 'Kapasitas filesystem yang menyimpan aplikasi ini.';

    protected ?string $pollingInterval = '30s';

    protected function getStats(): array
    {
        $path = base_path();
        $totalBytes = disk_total_space($path);
        $freeBytes = disk_free_space($path);

        if (($totalBytes === false) || ($freeBytes === false) || ($totalBytes <= 0)) {
            return [
                Stat::make('Storage server', 'Tidak tersedia')
                    ->description('Kapasitas filesystem tidak dapat dibaca.')
                    ->icon(Heroicon::OutlinedServerStack)
                    ->color('gray'),
            ];
        }

        $usedBytes = max(0, $totalBytes - $freeBytes);
        $usedPercentage = min(100, ($usedBytes / $totalBytes) * 100);
        $usageColor = match (true) {
            $usedPercentage >= 90 => 'danger',
            $usedPercentage >= 75 => 'warning',
            default => 'success',
        };

        return [
            Stat::make('Terpakai', $this->formatBytes($usedBytes))
                ->description(number_format($usedPercentage, 1, ',', '.').'% dari kapasitas')
                ->icon(Heroicon::OutlinedServerStack)
                ->color($usageColor),
            Stat::make('Tersedia', $this->formatBytes($freeBytes))
                ->description('Ruang kosong pada filesystem')
                ->icon(Heroicon::OutlinedCircleStack)
                ->color('success'),
            Stat::make('Total', $this->formatBytes($totalBytes))
                ->description('Filesystem tempat aplikasi berjalan')
                ->icon(Heroicon::OutlinedCircleStack)
                ->color('gray'),
        ];
    }

    private function formatBytes(float|int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $unitIndex = 0;

        while (($bytes >= 1024) && ($unitIndex < (count($units) - 1))) {
            $bytes /= 1024;
            $unitIndex++;
        }

        $precision = $unitIndex === 0 ? 0 : 1;

        return number_format($bytes, $precision, ',', '.').' '.$units[$unitIndex];
    }
}
