<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransactionStatistics extends Page implements HasTable
{
    use InteractsWithTable {
        applyTableFilters as applyValidatedTableFilters;
    }

    private const SECURITY_GROUP_NAMES = ['Security Group A', 'Security Group B', 'Security Group C'];

    protected static string|null|\BackedEnum $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Statistik';

    protected static ?string $title = 'Statistik';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.transaction-statistics';

    public function applyTableFilters(): void
    {
        $period = $this->tableDeferredFilters['period'] ?? [];
        $from = $period['date_from'] ?? null;
        $until = $period['date_until'] ?? null;

        if (blank($from) || blank($until)) {
            Notification::make()
                ->title('Periode belum lengkap')
                ->body('Pilih tanggal awal dan tanggal akhir.')
                ->danger()
                ->send();

            return;
        }

        try {
            $startDate = Carbon::parse($from)->startOfDay();
            $endDate = Carbon::parse($until)->endOfDay();
        } catch (Throwable) {
            Notification::make()
                ->title('Tanggal tidak valid')
                ->body('Periksa kembali tanggal awal dan tanggal akhir.')
                ->danger()
                ->send();

            return;
        }

        $today = now()->endOfDay();

        if ($startDate->greaterThan($today) || $endDate->greaterThan($today)) {
            Notification::make()
                ->title('Tanggal tidak valid')
                ->body('Tanggal awal dan akhir tidak boleh melewati hari ini.')
                ->danger()
                ->send();

            return;
        }

        if ($endDate->lessThan($startDate)) {
            Notification::make()
                ->title('Rentang tanggal tidak valid')
                ->body('Tanggal akhir harus sama atau setelah tanggal awal.')
                ->danger()
                ->send();

            return;
        }

        $latestAllowedEndDate = $startDate->copy()->addMonthsNoOverflow(3)->subDay()->endOfDay();
        $maximumEndDate = $latestAllowedEndDate->greaterThan($today) ? $today : $latestAllowedEndDate;

        if ($endDate->greaterThan($maximumEndDate)) {
            Notification::make()
                ->title('Rentang tanggal terlalu panjang')
                ->body('Pilih rentang tanggal maksimal 3 bulan.')
                ->danger()
                ->send();

            return;
        }

        $this->applyValidatedTableFilters();
    }

    public function table(Table $table): Table
    {
        return $table
            // ->heading('Jumlah transaksi')
            // ->description('Masuk dihitung dari PosInID dan TimeIn. Keluar dihitung dari PosOutID dan TimeOut.')
            ->records(fn (?array $filters): array => $this->getStatisticsRecords($filters ?? []))
            ->columns([
                TextColumn::make('type')
                    ->label('Jenis transaksi')
                    ->description(fn (array $record): string => $record['description']),
                TextColumn::make('incoming')
                    ->label('Masuk')
                    ->alignEnd()
                    ->formatStateUsing(fn (int|string $state): string => number_format((int) $state, 0, ',', '.')),
                TextColumn::make('outgoing')
                    ->label('Keluar')
                    ->alignEnd()
                    ->formatStateUsing(fn (int|string $state): string => number_format((int) $state, 0, ',', '.')),
            ])
            ->filters([
                Filter::make('period')
                    ->label('Periode')
                    ->schema([
                        DatePicker::make('date_from')
                            ->label('Dari tanggal')
                            ->default(fn (): Carbon => now()->startOfMonth())
                            ->native(false),
                        DatePicker::make('date_until')
                            ->label('Sampai tanggal')
                            ->default(fn (): Carbon => now())
                            ->native(false),
                    ])
                    ->columns(2)
                    ->indicateUsing(function (array $data): array {
                        $from = $data['date_from'] ?? null;
                        $until = $data['date_until'] ?? null;

                        if (blank($from) || blank($until)) {
                            return [];
                        }

                        return [Indicator::make('Periode: '.Carbon::parse($from)->format('d/m/Y').' – '.Carbon::parse($until)->format('d/m/Y'))];
                    }),
            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth(Width::Large)
            ->filtersTriggerAction(fn (Action $action): Action => $action
                ->button()
                ->label('Filter')
                ->icon(Heroicon::OutlinedFunnel))
            ->paginated(false);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array{type: string, description: string, incoming: int, outgoing: int}>
     */
    private function getStatisticsRecords(array $filters): array
    {
        $period = $filters['period'] ?? [];
        $startDate = Carbon::parse($period['date_from'] ?? now()->startOfMonth()->toDateString())->startOfDay();
        $endDate = Carbon::parse($period['date_until'] ?? now()->toDateString())->endOfDay();
        $latestAllowedEndDate = $startDate->copy()->addMonthsNoOverflow(3)->subDay()->endOfDay();

        if ($endDate->lessThan($startDate)) {
            $endDate = $startDate->copy();
        } elseif ($endDate->greaterThan($latestAllowedEndDate)) {
            $endDate = $latestAllowedEndDate;
        }

        $incoming = $this->countByDirection('PosInID', 'TimeIn', $startDate, $endDate);
        $outgoing = $this->countByDirection('PosOutID', 'TimeOut', $startDate, $endDate);

        return [
            'guest' => [
                'type' => 'Tamu',
                'description' => '',
                'incoming' => $incoming['guest'],
                'outgoing' => $outgoing['guest'],
            ],
            'resident' => [
                'type' => 'Warga',
                'description' => '',
                'incoming' => $incoming['resident'],
                'outgoing' => $outgoing['resident'],
            ],
            'security' => [
                'type' => 'Security',
                'description' => '',
                'incoming' => $incoming['security'],
                'outgoing' => $outgoing['security'],
            ],
        ];
    }

    /** @return array{total: int, guest: int, resident: int, security: int} */
    private function countByDirection(string $positionColumn, string $timeColumn, Carbon $startDate, Carbon $endDate): array
    {
        $securityGroups = self::SECURITY_GROUP_NAMES;

        /** @var object{total: int|string|null, guest: int|string|null, resident: int|string|null, security: int|string|null} $counts */
        $counts = DB::connection('trs_vip')
            ->table('trs')
            ->where($positionColumn, '>', 0)
            ->whereBetween($timeColumn, [$startDate, $endDate])
            ->selectRaw(
                'COUNT(*) AS total,
                SUM(CASE WHEN IsCust = 0 THEN 1 ELSE 0 END) AS guest,
                SUM(CASE WHEN IsCust = 1 AND NmTujuan IN (?, ?, ?) THEN 1 ELSE 0 END) AS security,
                SUM(CASE WHEN IsCust = 1 AND (NmTujuan IS NULL OR NmTujuan NOT IN (?, ?, ?)) THEN 1 ELSE 0 END) AS resident',
                [...$securityGroups, ...$securityGroups],
            )
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'guest' => (int) ($counts->guest ?? 0),
            'resident' => (int) ($counts->resident ?? 0),
            'security' => (int) ($counts->security ?? 0),
        ];
    }
}
