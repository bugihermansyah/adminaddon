<?php

namespace App\Filament\Resources\Penghuni\Tables;

use App\Models\Penghuni;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PenghuniTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ID')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('codebarcode')
                    ->label('ID Penghuni')
                    ->searchable(),
                TextColumn::make('namakaryawan')
                    ->label('Nama')
                    ->searchable(),
                TextColumn::make('dob')
                    ->label('DoB')
                    ->date('d M Y'),
                IconColumn::make('status')
                    ->label('Tunggakan')
                    ->state(fn (Penghuni $record): bool => (int) $record->status === 0)
                    ->boolean(),
                TextColumn::make('csv_name')
                    ->label('Kode Cluster')
                    ->searchable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Tunggakan')
                    ->options([
                        0 => 'True',
                        1 => 'False',
                    ]),
            ])
            ->filtersTriggerAction(fn (Action $action): Action => $action
                ->button()
                ->label('Filter'))
            ->defaultSort('ID', 'asc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
