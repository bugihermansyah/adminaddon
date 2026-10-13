<?php

namespace App\Jobs;

use App\Models\ResidentCsvSource;
use App\Models\ResidentCsvSyncRun;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SyncResidentCsvSource implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var array<int, int> */
    public array $backoff = [30, 90];

    public string $batchId;

    /**
     * @param  array<int, int>  $sourceIds
     */
    public function __construct(public array $sourceIds, ?string $batchId = null)
    {
        $this->sourceIds = array_values(array_unique(array_map('intval', $sourceIds)));
        sort($this->sourceIds);
        $this->batchId = $batchId ?? (string) Str::uuid();

        $this->onConnection('database');
        $this->onQueue('resident-sync');
    }

    public function handle(): void
    {
        $sources = ResidentCsvSource::query()->whereIn('id', $this->sourceIds)->get();

        if ($sources->count() !== count($this->sourceIds)) {
            throw new RuntimeException('Konfigurasi salah satu sumber CSV tidak ditemukan.');
        }

        $lock = Cache::lock('resident-csv-sync-global', 300);

        if (! $lock->get()) {
            $this->release(15);

            return;
        }

        try {
            foreach ($sources as $source) {
                ResidentCsvSyncRun::query()->updateOrCreate(
                    [
                        'batch_id' => $this->batchId,
                        'source_key' => $source->source_key,
                    ],
                    [
                        'status' => 'running',
                        'started_at' => now(),
                        'finished_at' => null,
                        'rows' => 0,
                        'added' => 0,
                        'updated' => 0,
                        'deleted' => 0,
                        'error' => null,
                    ],
                );
            }

            ResidentCsvSource::query()
                ->whereIn('id', $this->sourceIds)
                ->update([
                    'last_attempt_at' => now(),
                    'last_status' => 'running',
                    'last_error' => null,
                ]);

            $countsBySource = $this->synchronize($sources->all());

            foreach ($sources as $source) {
                $counts = $countsBySource[$source->getKey()];

                $source->forceFill([
                    'last_status' => 'success',
                    'last_success_at' => now(),
                    'last_rows' => $counts['rows'],
                    'last_added' => $counts['added'],
                    'last_updated' => $counts['updated'],
                    'last_deleted' => $counts['deleted'],
                    'last_error' => null,
                ])->save();

                ResidentCsvSyncRun::query()
                    ->where('batch_id', $this->batchId)
                    ->where('source_key', $source->source_key)
                    ->update([
                        'status' => 'success',
                        'finished_at' => now(),
                        'rows' => $counts['rows'],
                        'added' => $counts['added'],
                        'updated' => $counts['updated'],
                        'deleted' => $counts['deleted'],
                        'error' => null,
                    ]);
            }
        } finally {
            $lock->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        $error = mb_substr($exception->getMessage(), 0, 4000);

        ResidentCsvSource::query()
            ->whereIn('id', $this->sourceIds)
            ->update([
                'last_status' => 'failed',
                'last_error' => $error,
                'updated_at' => now(),
            ]);

        foreach (ResidentCsvSource::query()->whereIn('id', $this->sourceIds)->get() as $source) {
            $run = ResidentCsvSyncRun::query()->firstOrNew(
                [
                    'batch_id' => $this->batchId,
                    'source_key' => $source->source_key,
                ],
            );

            $run->fill([
                'status' => 'failed',
                'started_at' => $run->started_at ?? now(),
                'finished_at' => now(),
                'error' => $error,
            ])->save();
        }
    }

    /**
     * @param  array<int, ResidentCsvSource>  $sources
     * @return array<int, array{rows: int, added: int, updated: int, deleted: int}>
     */
    private function synchronize(array $sources): array
    {
        $snapshots = [];
        $incomingOwners = [];

        foreach ($sources as $source) {
            $response = Http::accept('text/csv, text/plain, */*')
                ->connectTimeout(10)
                ->timeout(60)
                ->retry(3, 500, throw: false)
                ->get($source->url);

            $response->throw();
            $csv = $response->body();
            $maximumBytes = (int) config('services.resident_csv.max_bytes', 25 * 1024 * 1024);

            if ($csv === '' || strlen($csv) > $maximumBytes) {
                throw new RuntimeException("CSV {$source->source_key} kosong atau melebihi batas ukuran yang diizinkan.");
            }

            $records = $this->parseCsv($csv, $source->source_key);

            if ($records === []) {
                throw new RuntimeException("CSV {$source->source_key} tidak memiliki baris data; seluruh cluster tidak diubah.");
            }

            foreach ($records as $normalizedBarcode => $record) {
                if (isset($incomingOwners[$normalizedBarcode])) {
                    throw new RuntimeException('codebarcode yang sama ditemukan di lebih dari satu CSV cluster.');
                }

                $incomingOwners[$normalizedBarcode] = $source->source_key;
            }

            $snapshots[$source->getKey()] = $records;
        }

        return $this->reconcile($sources, $snapshots, $incomingOwners);
    }

    /**
     * @param  array<int, ResidentCsvSource>  $sources
     * @param  array<int, array<string, array{codebarcode: string, namakaryawan: string, dob: ?string, status: int}>>  $snapshots
     * @param  array<string, string>  $incomingOwners
     * @return array<int, array{rows: int, added: int, updated: int, deleted: int}>
     */
    private function reconcile(array $sources, array $snapshots, array $incomingOwners): array
    {
        return DB::connection('mst_vip')->transaction(function () use ($sources, $snapshots, $incomingOwners): array {
            $connection = DB::connection('mst_vip');
            $currentBySource = [];

            foreach ($sources as $source) {
                $sourceRows = $connection->table('penghuni')
                    ->where('csv_name', $source->source_key)
                    ->get(['ID', 'codebarcode', 'namakaryawan', 'dob', 'status']);

                $currentBySource[$source->getKey()] = [];

                foreach ($sourceRows as $row) {
                    $normalizedBarcode = $this->normalizeBarcode((string) $row->codebarcode);

                    if (isset($currentBySource[$source->getKey()][$normalizedBarcode])) {
                        throw new RuntimeException("Ditemukan codebarcode ganda pada data aktif {$source->source_key}.");
                    }

                    $currentBySource[$source->getKey()][$normalizedBarcode] = $row;
                }
            }

            foreach (array_chunk(array_keys($incomingOwners), 500) as $barcodeChunk) {
                $barcodes = [];

                foreach ($barcodeChunk as $normalizedBarcode) {
                    foreach ($snapshots as $records) {
                        if (isset($records[$normalizedBarcode])) {
                            $barcodes[] = $records[$normalizedBarcode]['codebarcode'];

                            break;
                        }
                    }
                }

                $existingRows = $connection->table('penghuni')
                    ->whereIn('codebarcode', $barcodes)
                    ->get(['ID', 'codebarcode', 'csv_name']);

                foreach ($existingRows as $existingRow) {
                    $incomingOwner = $incomingOwners[$this->normalizeBarcode((string) $existingRow->codebarcode)] ?? null;

                    if ($incomingOwner !== null && $existingRow->csv_name !== $incomingOwner) {
                        throw new RuntimeException('codebarcode ditemukan pada sumber CSV lain; seluruh cluster tidak diubah.');
                    }
                }
            }

            $countsBySource = [];

            foreach ($sources as $source) {
                $sourceId = (int) $source->getKey();
                $records = $snapshots[$sourceId];
                $currentRows = $currentBySource[$sourceId];
                $insertRows = [];
                $updated = 0;

                foreach ($records as $normalizedBarcode => $record) {
                    $current = $currentRows[$normalizedBarcode] ?? null;

                    if ($current === null) {
                        $insertRows[] = [
                            ...$record,
                            'iddivisi' => '',
                            'csv_name' => $source->source_key,
                        ];

                        continue;
                    }

                    $changes = [];

                    foreach (['codebarcode', 'namakaryawan', 'dob', 'status'] as $field) {
                        $currentValue = $current->{$field};
                        $incomingValue = $record[$field];

                        if ($field === 'status') {
                            $currentValue = (int) $currentValue;
                            $incomingValue = (int) $incomingValue;
                        } elseif ($field === 'dob') {
                            $currentValue = $currentValue === null ? null : (string) $currentValue;
                        } else {
                            $currentValue = (string) $currentValue;
                        }

                        if ($currentValue !== $incomingValue) {
                            $changes[$field] = $incomingValue;
                        }
                    }

                    if ($changes !== []) {
                        $connection->table('penghuni')->where('ID', $current->ID)->update($changes);
                        $updated++;
                    }
                }

                foreach (array_chunk($insertRows, 100) as $insertChunk) {
                    $connection->table('penghuni')->insert($insertChunk);
                }

                $deletedIds = [];

                foreach ($currentRows as $normalizedBarcode => $current) {
                    if (! isset($records[$normalizedBarcode])) {
                        $deletedIds[] = (int) $current->ID;
                    }
                }

                foreach (array_chunk($deletedIds, 500) as $idChunk) {
                    $connection->table('penghuni')
                        ->where('csv_name', $source->source_key)
                        ->whereIn('ID', $idChunk)
                        ->delete();
                }

                $countsBySource[$sourceId] = [
                    'rows' => count($records),
                    'added' => count($insertRows),
                    'updated' => $updated,
                    'deleted' => count($deletedIds),
                ];
            }

            return $countsBySource;
        });
    }

    /**
     * @return array<string, array{codebarcode: string, namakaryawan: string, dob: ?string, status: int}>
     */
    private function parseCsv(string $csv, string $sourceKey): array
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('CSV tidak dapat dibaca.');
        }

        fwrite($stream, $csv);
        rewind($stream);

        $records = [];
        $lineNumber = 0;

        try {
            while (($columns = fgetcsv($stream, null, ',', '"', '')) !== false) {
                $lineNumber++;
                $columns = array_map(static fn (?string $value): string => trim($value ?? ''), $columns);

                if ($lineNumber === 1 && isset($columns[0])) {
                    $columns[0] = ltrim($columns[0], "\xEF\xBB\xBF");
                }

                if (count($columns) === 1 && $columns[0] === '') {
                    continue;
                }

                if (count($columns) < 4 || array_filter(array_slice($columns, 4), static fn (string $value): bool => $value !== '') !== []) {
                    throw new RuntimeException("Format CSV {$sourceKey} tidak valid pada baris {$lineNumber}; diperlukan empat kolom data.");
                }

                [$barcode, $name, $dateOfBirth, $status] = array_slice($columns, 0, 4);

                if ($barcode === '' || mb_strlen($barcode) > 400) {
                    throw new RuntimeException("codebarcode kosong atau terlalu panjang pada {$sourceKey}, baris {$lineNumber}.");
                }

                if ($name === '' || mb_strlen($name) > 50) {
                    throw new RuntimeException("Nama kosong atau melebihi 50 karakter pada {$sourceKey}, baris {$lineNumber}.");
                }

                if (! in_array($status, ['0', '1'], true)) {
                    throw new RuntimeException("Status harus bernilai 0 atau 1 pada {$sourceKey}, baris {$lineNumber}.");
                }

                $normalizedBarcode = $this->normalizeBarcode($barcode);

                if (isset($records[$normalizedBarcode])) {
                    throw new RuntimeException("codebarcode ganda ditemukan pada {$sourceKey}, baris {$lineNumber}.");
                }

                $records[$normalizedBarcode] = [
                    'codebarcode' => $barcode,
                    'namakaryawan' => $name,
                    'dob' => $this->parseDateOfBirth($dateOfBirth, $sourceKey, $lineNumber),
                    'status' => (int) $status,
                ];
            }
        } finally {
            fclose($stream);
        }

        return $records;
    }

    private function normalizeBarcode(string $barcode): string
    {
        return 'barcode:'.mb_strtolower($barcode);
    }

    private function parseDateOfBirth(string $value, string $sourceKey, int $lineNumber): ?string
    {
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Ymd', $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Ymd') !== $value
        ) {
            throw new RuntimeException("Format dob harus YYYYMMDD pada {$sourceKey}, baris {$lineNumber}.");
        }

        return $date->format('Y-m-d');
    }
}
