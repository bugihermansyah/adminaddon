<?php

namespace App\Filament\Resources\ResidentCsvSyncRuns;

use App\Filament\Resources\ResidentCsvSyncRuns\Pages\ManageResidentCsvSyncRuns;
use App\Models\ResidentCsvSyncRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ResidentCsvSyncRunResource extends Resource
{
    protected static ?string $model = ResidentCsvSyncRun::class;

    protected static ?string $navigationLabel = 'Log sinkronisasi CSV';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'log sinkronisasi CSV';

    protected static ?string $pluralModelLabel = 'Riwayat sinkronisasi CSV';

    protected static ?string $recordTitleAttribute = 'source_key';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source_key')
                    ->label('File CSV')
                    ->searchable()
                    ->sortable(),
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
                    })
                    ->sortable(),
                TextColumn::make('started_at')
                    ->label('Mulai')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta')
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Selesai')
                    ->dateTime('d M Y H:i:s', 'Asia/Jakarta')
                    ->sortable(),
                TextColumn::make('rows')
                    ->label('Baris')
                    ->numeric(),
                TextColumn::make('added')
                    ->label('Ditambah')
                    ->numeric(),
                TextColumn::make('updated')
                    ->label('Diubah')
                    ->numeric(),
                TextColumn::make('deleted')
                    ->label('Dihapus')
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
            ->filters([
                SelectFilter::make('source_key')
                    ->label('File CSV')
                    ->options(fn (): array => ResidentCsvSyncRun::query()
                        ->distinct()
                        ->orderBy('source_key')
                        ->pluck('source_key', 'source_key')
                        ->all()),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'running' => 'Berjalan',
                        'success' => 'Berhasil',
                        'partial' => 'Sebagian',
                        'failed' => 'Gagal',
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([])
            ->defaultSort('started_at', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Belum ada log sinkronisasi')
            ->emptyStateDescription('Log akan muncul setelah proses sinkronisasi CSV berjalan.');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageResidentCsvSyncRuns::route('/'),
        ];
    }
}
