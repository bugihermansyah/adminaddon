<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ResidentCsvSyncRuns\ResidentCsvSyncRunResource;
use App\Models\ResidentCsvSyncRun;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class ResidentCsvSyncMonitor extends TableWidget
{
    protected static ?string $heading = 'Pemantauan sinkronisasi CSV';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ResidentCsvSyncRun::query()
                ->orderByDesc('started_at')
                ->limit(8))
            ->poll('30s')
            ->columns([
                TextColumn::make('source_key')
                    ->label('File CSV'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'running' => 'Berjalan',
                        'success' => 'Berhasil',
                        'partial' => 'Sebagian',
                        'failed' => 'Gagal',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'running' => 'warning',
                        'success' => 'success',
                        'partial' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta'),
                TextColumn::make('added')
                    ->label('Tambah')
                    ->numeric(),
                TextColumn::make('updated')
                    ->label('Ubah')
                    ->numeric(),
                TextColumn::make('deleted')
                    ->label('Hapus')
                    ->numeric(),
                TextColumn::make('error')
                    ->label('Pesan')
                    ->formatStateUsing(static function (?string $state): ?string {
                        if (blank($state)) {
                            return null;
                        }

                        return preg_match('/\bstatus code\s+(\d{3})\b/i', $state, $matches) === 1
                            ? 'HTTP '.$matches[1]
                            : (preg_match('/^Baris dilewati:\s*(\d+)/', $state, $matches) === 1 ? ''.$matches[1].' baris dilewati' : 'Error');
                    })
                    ->tooltip(fn (?string $state): ?string => $state),
            ])
            ->headerActions([
                Action::make('allLogs')
                    ->label('Lihat semua log')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->url(fn (): string => ResidentCsvSyncRunResource::getUrl('index')),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('started_at', 'desc')
            ->paginated(false)
            ->emptyStateHeading('Belum ada log sinkronisasi')
            ->emptyStateDescription('Log akan muncul setelah proses sinkronisasi CSV berjalan.');
    }
}
