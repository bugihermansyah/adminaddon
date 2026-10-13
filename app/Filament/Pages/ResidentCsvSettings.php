<?php

namespace App\Filament\Pages;

use App\Models\ResidentCsvSetting;
use App\Models\ResidentCsvSource;
use App\Services\ResidentCsvSyncDispatcher;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ResidentCsvSettings extends Page
{
    protected static string|null|\BackedEnum $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static ?string $navigationLabel = 'Pengaturan sinkronisasi CSV';

    protected static ?string $title = 'Pengaturan sinkronisasi CSV';

    protected string $view = 'filament.pages.resident-csv-settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getFormData());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sumber data penghuni')
                    ->description('Atur satu atau beberapa file CSV untuk cluster ini. Nama file pada URL menjadi identitas sumber.')
                    ->schema([
                        Repeater::make('sources')
                            // ->label('URL file CSV')
                            ->table([
                                TableColumn::make('URL'),
                            ])
                            ->compact()
                            // ->helperText('Nama file CSV pada URL menjadi identitas sumber. CSV yang dinonaktifkan tidak menghapus data penghuni yang sudah tersimpan.')
                            ->schema([
                                TextInput::make('url')
                                    ->label('Alamat URL CSV')
                                    ->placeholder('https://sales.paramount-land.com/api/v1/files/estate/gate/PEI-P01494.csv')
                                    ->required()
                                    ->maxLength(2048)
                                    ->rules(['url:https']),
                            ])
                            ->required()
                            ->minItems(1)
                            ->addActionLabel('Tambah URL CSV')
                            ->reorderable(false)
                            ->columnSpanFull(),
                    ]),
                Section::make('Jadwal dan cara sinkronisasi')
                    ->description('Pengaturan diterapkan scheduler berikutnya. Perubahan ini tidak memerlukan restart queue worker.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('interval_minutes')
                            ->label('Interval sinkronisasi (menit)')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(1440)
                            ->required(),
                        Select::make('atomicity')
                            ->label('Cara memproses banyak CSV')
                            ->options([
                                'per_file' => 'Per file — setiap file diproses terpisah',
                                'all_files' => 'Semua file — satu gagal, seluruh batch dibatalkan',
                            ])
                            ->native(false)
                            ->required(),
                    ]),
            ])
            ->statePath('data');
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan pengaturan')
                ->color('primary')
                ->action(function (): void {
                    $this->save();
                }),
            Action::make('syncNow')
                ->label('Sinkronkan sekarang')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sinkronkan data penghuni sekarang?')
                ->modalDescription('Proses dijalankan di antrean. Data penghuni yang tidak ditemukan pada CSV yang berhasil diproses akan dihapus. CSV yang gagal diunduh tidak mengubah data sumbernya.')
                ->modalSubmitActionLabel('Masukkan ke antrean')
                ->action(function (): void {
                    $result = app(ResidentCsvSyncDispatcher::class)->dispatchManually();

                    if ($result['queued_jobs'] === 0) {
                        Notification::make()
                            ->title('Sinkronisasi belum dimasukkan ke antrean')
                            ->body($result['skipped_sources'] > 0
                                ? 'Ada proses sinkronisasi yang masih menunggu atau berjalan.'
                                : 'Belum ada URL CSV yang aktif.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Sinkronisasi dimasukkan ke antrean')
                        ->body("{$result['queued_sources']} file CSV akan diproses oleh queue worker.")
                        ->success()
                        ->send();
                }),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $sources = $this->normalizeSources($data['sources'] ?? []);
        $sourceKeys = array_map(static fn (array $source): string => strtolower($source['source_key']), $sources);

        if (count($sourceKeys) !== count(array_unique($sourceKeys))) {
            throw ValidationException::withMessages([
                'data.sources' => 'Nama file CSV harus berbeda untuk setiap URL.',
            ]);
        }

        DB::transaction(function () use ($data, $sources): void {
            /** @var Collection<int, ResidentCsvSource> $existingSources */
            $existingSources = ResidentCsvSource::query()
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $desiredUrls = collect($sources)->keyBy('source_key');

            foreach ($existingSources as $existingSource) {
                $isActive = in_array($existingSource->last_status, ['queued', 'running'], true);
                $desiredSource = $desiredUrls->get($existingSource->source_key);
                $isBeingChanged = $desiredSource === null || $desiredSource['url'] !== $existingSource->url;

                if ($isActive && $isBeingChanged) {
                    throw ValidationException::withMessages([
                        'data.sources' => "{$existingSource->source_key} sedang menunggu atau berjalan. Simpan kembali setelah prosesnya selesai.",
                    ]);
                }
            }

            $settings = ResidentCsvSetting::query()->firstOrCreate(['id' => 1]);
            $settings->fill([
                'interval_minutes' => (int) $data['interval_minutes'],
                'atomicity' => $data['atomicity'],
            ])->save();

            ResidentCsvSource::query()->where('is_enabled', true)->update(['is_enabled' => false]);

            foreach ($sources as $sourceData) {
                $source = ResidentCsvSource::query()->firstOrNew([
                    'source_key' => $sourceData['source_key'],
                ]);

                if (! $source->exists || $source->url !== $sourceData['url']) {
                    $source->next_sync_at = now();
                }

                $source->forceFill([
                    'url' => $sourceData['url'],
                    'is_enabled' => true,
                ])->save();
            }
        });

        $this->form->fill($this->getFormData());

        Notification::make()
            ->title('Pengaturan sinkronisasi disimpan')
            ->body('Pengaturan baru digunakan oleh jadwal berikutnya tanpa restart queue worker.')
            ->success()
            ->send();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /** @return array<string, mixed> */
    private function getFormData(): array
    {
        $settings = ResidentCsvSetting::query()->firstOrCreate(
            ['id' => 1],
            [
                'interval_minutes' => max(1, (int) config('services.resident_csv.interval_minutes', 60)),
                'atomicity' => in_array(config('services.resident_csv.atomicity'), ['per_file', 'all_files'], true)
                    ? config('services.resident_csv.atomicity')
                    : 'per_file',
            ],
        );

        return [
            'sources' => ResidentCsvSource::query()
                ->where('is_enabled', true)
                ->orderBy('source_key')
                ->get(['url'])
                ->map(fn (ResidentCsvSource $source): array => ['url' => $source->url])
                ->all(),
            'interval_minutes' => $settings->interval_minutes,
            'atomicity' => $settings->atomicity,
        ];
    }

    /**
     * @param  array<int|string, array{url?: string}>  $sources
     * @return array<int, array{source_key: string, url: string}>
     */
    private function normalizeSources(array $sources): array
    {
        $normalizedSources = [];

        foreach ($sources as $source) {
            $url = trim((string) ($source['url'] ?? ''));
            $path = parse_url($url, PHP_URL_PATH);
            $sourceKey = is_string($path) ? basename($path) : '';

            if (preg_match('/^[A-Za-z0-9._-]+\.csv$/i', $sourceKey) !== 1) {
                throw ValidationException::withMessages([
                    'data.sources' => 'Setiap URL harus berakhir dengan nama file CSV, misalnya PEI-P01494.csv.',
                ]);
            }

            $normalizedSources[] = [
                'source_key' => $sourceKey,
                'url' => $url,
            ];
        }

        return $normalizedSources;
    }
}
