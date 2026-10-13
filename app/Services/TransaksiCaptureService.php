<?php

namespace App\Services;

use App\Models\Transaksi;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

class TransaksiCaptureService
{
    /**
     * Load captures from the monthly, direction-specific TCM tables.
     * A row can have both entry and exit timestamps (anti-passback) or only one.
     *
     * @return array{events: array<int, array{direction: string, position: string, time: string, captures: array<int, array{camera: string, src: string}> , message: ?string}>, message: ?string}
     */
    public function forTransaction(Transaksi $transaction): array
    {
        $events = [];

        foreach ([
            ['field' => 'TimeIn', 'direction' => 'Masuk', 'positionRelation' => 'posIn', 'suffix' => 'i', 'cameraColumns' => ['CamIn1', 'CamIn2', 'CamIn3']],
            ['field' => 'TimeOut', 'direction' => 'Keluar', 'positionRelation' => 'posOut', 'suffix' => 'o', 'cameraColumns' => ['CamOut1', 'CamOut2']],
        ] as $event) {
            $rawTimestamp = $transaction->getAttribute($event['field']);
            $position = $transaction->{$event['positionRelation']}?->PosName ?: "Pintu {$event['direction']}";

            if (blank($rawTimestamp) || str_starts_with((string) $rawTimestamp, '0000-00-00')) {
                continue;
            }

            try {
                $timestamp = CarbonImmutable::parse($rawTimestamp);
            } catch (Throwable) {
                $events[] = [
                    'direction' => $event['direction'],
                    'position' => $position,
                    'time' => (string) $rawTimestamp,
                    'captures' => [],
                    'message' => 'Waktu transaksi tidak dapat dibaca.',
                ];

                continue;
            }

            $captures = [];
            $partitionReadSucceeded = false;
            $imagePayloadFound = false;

            // Some sites partition captures by processing month, which can differ
            // from the transaction timestamp around midnight or month boundaries.
            foreach ([0, 1, -1] as $monthOffset) {
                $partitionDate = $timestamp->addMonths($monthOffset);
                $table = 'tr'.$partitionDate->format('Ym').$event['suffix'];

                try {
                    $rows = DB::connection('tcm_vip')
                        ->table($table)
                        ->where('TrsID', $transaction->getKey())
                        ->get($event['cameraColumns']);

                    $partitionReadSucceeded = true;

                    foreach ($rows as $row) {
                        foreach ($event['cameraColumns'] as $cameraColumn) {
                            $contents = $row->{$cameraColumn} ?? null;

                            if (! is_string($contents) || $contents === '') {
                                continue;
                            }

                            $imagePayloadFound = true;
                            $image = $this->normalizeImage($contents);

                            if ($image === null) {
                                continue;
                            }

                            $captures[] = [
                                'camera' => $cameraColumn,
                                'src' => 'data:'.$image['mime'].';base64,'.base64_encode($image['contents']),
                            ];
                        }
                    }
                } catch (QueryException) {
                    // A monthly partition may not exist; keep checking the adjacent month.
                    continue;
                }

                if ($captures !== []) {
                    break;
                }
            }

            $events[] = [
                'direction' => $event['direction'],
                'position' => $position,
                'time' => $timestamp->format('d/m/Y H:i:s'),
                'captures' => $captures,
                'message' => match (true) {
                    $captures !== [] => null,
                    $imagePayloadFound => 'Data capture ditemukan, tetapi format fotonya tidak dikenali.',
                    ! $partitionReadSucceeded => 'Tabel capture untuk sesi ini tidak ditemukan atau tidak dapat dibaca.',
                    default => 'Tidak ada foto capture untuk sesi ini.',
                },
            ];
        }

        return [
            'events' => $events,
            'message' => $events === []
                ? 'Transaksi ini belum memiliki waktu masuk atau keluar.'
                : null,
        ];
    }

    /** @return array{mime: string, contents: string} | null */
    private function normalizeImage(string $contents): ?array
    {
        if (preg_match('/^data:image\/(jpeg|png|gif|webp|bmp);base64,(.+)$/is', trim($contents), $matches) === 1) {
            $decoded = base64_decode($matches[2], strict: true);

            if ($decoded !== false && ($image = $this->detectImage($decoded)) !== null) {
                return $image;
            }
        }

        if (($image = $this->detectImage($contents)) !== null) {
            return $image;
        }

        if (($jpeg = $this->convertToJpeg($contents)) !== null) {
            return ['mime' => 'image/jpeg', 'contents' => $jpeg];
        }

        // Some camera SDKs prefix the encoded image with a small binary header.
        foreach (["\xFF\xD8\xFF", "\x89PNG\r\n\x1A\n", 'GIF87a', 'GIF89a'] as $signature) {
            $offset = strpos($contents, $signature);

            if ($offset !== false && $offset <= 4096) {
                $imageContents = substr($contents, $offset);

                if (($image = $this->detectImage($imageContents)) !== null) {
                    return $image;
                }

                if (($jpeg = $this->convertToJpeg($imageContents)) !== null) {
                    return ['mime' => 'image/jpeg', 'contents' => $jpeg];
                }
            }
        }

        // A few integrations store base64 text or a zlib/gzip-compressed image in the BLOB.
        $base64 = preg_replace('/[\s\x00]+/', '', $contents);

        if (is_string($base64)) {
            if (preg_match('/(?:\/9j\/|iVBORw0KGgo|R0lGOD|UklGR)[A-Za-z0-9+\/=]+/', $base64, $matches) === 1) {
                $base64 = $matches[0];
            } elseif (preg_match('/^[A-Za-z0-9+\/=]+$/', $base64) !== 1) {
                $base64 = '';
            }

            $decoded = $base64 === '' ? false : base64_decode($base64, strict: true);

            if ($decoded !== false) {
                if (($image = $this->detectImage($decoded)) !== null) {
                    return $image;
                }

                if (($jpeg = $this->convertToJpeg($decoded)) !== null) {
                    return ['mime' => 'image/jpeg', 'contents' => $jpeg];
                }
            }
        }

        foreach ([
            @gzdecode($contents, 15 * 1024 * 1024),
            @gzuncompress($contents, 15 * 1024 * 1024),
            @gzinflate($contents, 15 * 1024 * 1024),
        ] as $decoded) {
            if (is_string($decoded)) {
                if (($image = $this->detectImage($decoded)) !== null) {
                    return $image;
                }

                if (($jpeg = $this->convertToJpeg($decoded)) !== null) {
                    return ['mime' => 'image/jpeg', 'contents' => $jpeg];
                }
            }
        }

        return null;
    }

    /** @return array{mime: string, contents: string} | null */
    private function detectImage(string $contents): ?array
    {
        $signatures = [
            'image/jpeg' => "\xFF\xD8\xFF",
            'image/png' => "\x89PNG\r\n\x1A\n",
            'image/gif' => 'GIF87a',
            'image/webp' => 'RIFF',
            'image/bmp' => 'BM',
        ];

        foreach ($signatures as $mimeType => $signature) {
            if (! str_starts_with($contents, $signature)) {
                continue;
            }

            if (($mimeType !== 'image/webp') || (substr($contents, 8, 4) === 'WEBP')) {
                return ['mime' => $mimeType, 'contents' => $contents];
            }
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);

        if (in_array($mimeType, [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/bmp',
        ], true)) {
            return ['mime' => $mimeType, 'contents' => $contents];
        }

        return null;
    }

    private function convertToJpeg(string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $dimensions = @getimagesizefromstring($contents);

        if ($dimensions === false) {
            return null;
        }

        [$width, $height] = $dimensions;

        if ($width < 1 || $height < 1 || ($width * $height) > 40_000_000) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if (! ($image instanceof \GdImage)) {
            return null;
        }

        ob_start();
        $encoded = @imagejpeg($image, null, 90);
        $jpeg = ob_get_clean();
        imagedestroy($image);

        return ($encoded && is_string($jpeg) && $jpeg !== '') ? $jpeg : null;
    }
}
