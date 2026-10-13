<?php

namespace App\Filament\Widgets;

use App\Models\ResidentCsvSource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ResidentCsvSourceMonitor extends TableWidget
{
    protected static ?string $heading = 'Sumber CSV penghuni';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ResidentCsvSource::query()->where('is_enabled', true))
            ->poll('30s')
            ->columns([
                TextColumn::make('source_key')
                    ->label('File CSV'),
                TextColumn::make('is_enabled')
                    ->label('Konfigurasi')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('last_status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'queued' => 'Menunggu',
                        'running' => 'Berjalan',
                        'success' => 'Berhasil',
                        'failed' => 'Gagal',
                        default => 'Belum berjalan',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'queued' => 'info',
                        'running' => 'warning',
                        'success' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('last_attempt_at')
                    ->label('Percobaan terakhir')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta')
                    ->placeholder('—'),
                TextColumn::make('last_success_at')
                    ->label('Berhasil terakhir')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta')
                    ->placeholder('—'),
                TextColumn::make('next_sync_at')
                    ->label('Jadwal berikutnya')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta')
                    ->placeholder('—'),
                TextColumn::make('last_rows')
                    ->label('Baris')
                    ->numeric(),
                TextColumn::make('last_added')
                    ->label('Ditambah')
                    ->numeric(),
                TextColumn::make('last_updated')
                    ->label('Diubah')
                    ->numeric(),
                TextColumn::make('last_deleted')
                    ->label('Dihapus')
                    ->numeric(),
                TextColumn::make('last_error')
                    ->label('Pesan')
                    ->formatStateUsing(static function (?string $state): ?string {
                        if (blank($state)) {
                            return null;
                        }

                        return preg_match('/\bstatus code\s+(\d{3})\b/i', $state, $matches) === 1
                            ? 'HTTP '.$matches[1]
                            : 'Error';
                    })
                    ->tooltip(fn (?string $state): ?string => $state),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('source_key')
            ->paginated(false)
            ->emptyStateHeading('Belum ada sumber CSV')
            ->emptyStateDescription('Tambahkan URL CSV melalui menu Pengaturan sinkronisasi CSV pada profil pengguna.');
    }
}
