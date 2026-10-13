<?php

namespace App\Filament\Resources\Transaksi\Tables;

use App\Models\Transaksi;
use App\Services\TransaksiCaptureService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class TransaksiTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('ID')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('NmTujuan')
                    ->label('Tujuan')
                    ->searchable(),
                TextColumn::make('CardNo')
                    ->label('ID Penghuni')
                    ->searchable(),
                TextColumn::make('IsCust')
                    ->label('Tipe')
                    ->formatStateUsing(fn ($state): string => match ((int) $state) {
                        1 => 'Penghuni',
                        0 => 'Tamu',
                        default => 'Tidak diketahui',
                    })
                    ->badge(),
                TextColumn::make('posIn.PosName')
                    ->label('Pos Masuk'),
                TextColumn::make('posOut.PosName')
                    ->label('Pos Keluar'),
                TextColumn::make('TimeIn')
                    ->dateTime(),
                TextColumn::make('TimeOut')
                    ->dateTime(),
                TextColumn::make('Barcode')
                    ->limit(12)
                    ->searchable(),
                TextColumn::make('qr_id')
                    ->label('QRCode')
                    ->searchable(),
            ])
            ->filters([
                Filter::make('tamu')
                    ->label('Tamu')
                    ->query(fn (Builder $query): Builder => $query->where('IsCust', 0)),
                Filter::make('rentang_waktu')
                    ->label('Rentang waktu')
                    ->schema([
                        Section::make('Waktu masuk')
                            ->description('Pilih tanggal awal dan/atau akhir. Tanggal akhir ikut dihitung.')
                            ->schema([
                                DatePicker::make('time_in_from')
                                    ->label('Dari tanggal'),
                                DatePicker::make('time_in_until')
                                    ->label('Sampai tanggal')
                                    ->afterOrEqual('time_in_from'),
                            ])
                            ->columns(2),
                        Section::make('Waktu keluar')
                            ->description('Bagian ini opsional dan dapat dipakai sendiri atau bersama TimeIn.')
                            ->schema([
                                DatePicker::make('time_out_from')
                                    ->label('Dari tanggal'),
                                DatePicker::make('time_out_until')
                                    ->label('Sampai tanggal')
                                    ->afterOrEqual('time_out_from'),
                            ])
                            ->columns(2),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        foreach ([
                            'TimeIn' => ['time_in_from', 'time_in_until'],
                            'TimeOut' => ['time_out_from', 'time_out_until'],
                        ] as $column => [$fromKey, $untilKey]) {
                            if (filled($data[$fromKey] ?? null)) {
                                $query->where($column, '>=', Carbon::parse($data[$fromKey])->startOfDay());
                            }

                            if (filled($data[$untilKey] ?? null)) {
                                $query->where($column, '<', Carbon::parse($data[$untilKey])->addDay()->startOfDay());
                            }
                        }

                        return $query;
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        foreach ([
                            'time_in' => ['Waktu masuk', 'time_in_from', 'time_in_until'],
                            'time_out' => ['Waktu keluar', 'time_out_from', 'time_out_until'],
                        ] as [$label, $fromKey, $untilKey]) {
                            $from = $data[$fromKey] ?? null;
                            $until = $data[$untilKey] ?? null;

                            if (blank($from) && blank($until)) {
                                continue;
                            }

                            $fromLabel = filled($from) ? Carbon::parse($from)->format('d/m/Y') : 'tanpa batas awal';
                            $untilLabel = filled($until) ? Carbon::parse($until)->format('d/m/Y') : 'tanpa batas akhir';

                            $indicators[] = Indicator::make("{$label}: {$fromLabel} – {$untilLabel}");
                        }

                        return $indicators;
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::Large)
            ->filtersTriggerAction(fn (Action $action): Action => $action
                ->button()
                ->label('Filter'))
            ->defaultSort('ID', 'desc')
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->recordAction('viewCaptures')
            ->recordActions([
                Action::make('viewCaptures')
                    ->label('')
                    ->icon(Heroicon::OutlinedCamera)
                    ->color('gray')
                    ->slideOver()
                    ->modalWidth(Width::Large)
                    ->modalHeading(fn (Transaksi $record): string => "Capture transaksi #{$record->ID}")
                    ->modalSubmitAction(false)
                    ->modalCancelAction(false)
                    ->modalContent(fn (Transaksi $record): View => view(
                        'filament.transaksi.captures',
                        [
                            'result' => app(TransaksiCaptureService::class)->forTransaction($record),
                        ],
                    )),
            ]);
    }
}
