<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Transaksi\Pages\ListTransaksi;
use App\Filament\Resources\Transaksi\Tables\TransaksiTable;
use App\Models\Transaksi;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransaksiResource extends Resource
{
    protected static ?string $model = Transaksi::class;

    protected static ?string $navigationLabel = 'Transaksi';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static ?string $modelLabel = 'Transaksi';

    protected static ?string $pluralModelLabel = 'Transaksi';

    protected static ?string $recordTitleAttribute = 'ID';

    public static function table(Table $table): Table
    {
        return TransaksiTable::configure($table);
    }

    /** @return Builder<Transaksi> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->select([
            'ID',
            'NmTujuan',
            'CardNo',
            'IsCust',
            'PosInID',
            'PosOutID',
            'TimeIn',
            'TimeOut',
            'Barcode',
            'qr_id',
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
            'index' => ListTransaksi::route('/'),
        ];
    }
}
