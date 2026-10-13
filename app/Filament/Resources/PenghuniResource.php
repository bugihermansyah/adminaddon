<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Penghuni\Pages\ListPenghuni;
use App\Filament\Resources\Penghuni\Tables\PenghuniTable;
use App\Models\Penghuni;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PenghuniResource extends Resource
{
    protected static ?string $model = Penghuni::class;

    protected static ?string $navigationLabel = 'Penghuni';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'Penghuni';

    protected static ?string $pluralModelLabel = 'Penghuni';

    protected static ?string $recordTitleAttribute = 'ID';

    public static function table(Table $table): Table
    {
        return PenghuniTable::configure($table);
    }

    /** @return Builder<Penghuni> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select([
            'ID',
            'codebarcode',
            'namakaryawan',
            'dob',
            'status',
            'csv_name',
        ]);
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
            'index' => ListPenghuni::route('/'),
        ];
    }
}
