<?php

namespace App\Services;

use App\Jobs\SyncResidentCsvSource;
use App\Models\ResidentCsvSetting;
use App\Models\ResidentCsvSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ResidentCsvSyncDispatcher
{
    /**
     * @return array{queued_sources: int, skipped_sources: int, queued_jobs: int}
     */
    public function dispatchDue(): array
    {
        return $this->dispatch(manual: false);
    }

    /**
     * @return array{queued_sources: int, skipped_sources: int, queued_jobs: int}
     */
    public function dispatchManually(): array
    {
        return $this->dispatch(manual: true);
    }

    /**
     * @return array{queued_sources: int, skipped_sources: int, queued_jobs: int}
     */
    private function dispatch(bool $manual): array
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

        $sources = ResidentCsvSource::query()
            ->where('is_enabled', true)
            ->orderBy('id')
            ->get();

        if ($sources->isEmpty()) {
            return ['queued_sources' => 0, 'skipped_sources' => 0, 'queued_jobs' => 0];
        }

        return $settings->atomicity === 'all_files'
            ? $this->dispatchAsOneBatch($sources, (int) $settings->interval_minutes, $manual)
            : $this->dispatchPerFile($sources, (int) $settings->interval_minutes, $manual);
    }

    /**
     * @param  Collection<int, ResidentCsvSource>  $sources
     * @return array{queued_sources: int, skipped_sources: int, queued_jobs: int}
     */
    private function dispatchPerFile(Collection $sources, int $intervalMinutes, bool $manual): array
    {
        $queuedSources = 0;
        $skippedSources = 0;

        foreach ($sources as $source) {
            $wasQueued = DB::transaction(function () use ($source, $intervalMinutes, $manual): bool {
                $lockedSource = ResidentCsvSource::query()
                    ->lockForUpdate()
                    ->findOrFail($source->getKey());

                if (! $lockedSource->is_enabled || $this->isActive($lockedSource)) {
                    return false;
                }

                if (! $manual && $lockedSource->next_sync_at !== null && ! $lockedSource->next_sync_at->isPast()) {
                    return false;
                }

                $this->queue([$lockedSource], $intervalMinutes);

                return true;
            });

            if ($wasQueued) {
                $queuedSources++;
            } else {
                $skippedSources++;
            }
        }

        return [
            'queued_sources' => $queuedSources,
            'skipped_sources' => $skippedSources,
            'queued_jobs' => $queuedSources,
        ];
    }

    /**
     * @param  Collection<int, ResidentCsvSource>  $sources
     * @return array{queued_sources: int, skipped_sources: int, queued_jobs: int}
     */
    private function dispatchAsOneBatch(Collection $sources, int $intervalMinutes, bool $manual): array
    {
        return DB::transaction(function () use ($sources, $intervalMinutes, $manual): array {
            $lockedSources = ResidentCsvSource::query()
                ->whereIn('id', $sources->modelKeys())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $hasActiveSync = $lockedSources->contains(fn (ResidentCsvSource $source): bool => $this->isActive($source));

            if ($lockedSources->isEmpty() || $hasActiveSync) {
                return [
                    'queued_sources' => 0,
                    'skipped_sources' => $sources->count(),
                    'queued_jobs' => 0,
                ];
            }

            $hasDueSource = $lockedSources->contains(
                fn (ResidentCsvSource $source): bool => $source->next_sync_at === null || $source->next_sync_at->isPast(),
            );

            if (! $manual && ! $hasDueSource) {
                return [
                    'queued_sources' => 0,
                    'skipped_sources' => $sources->count(),
                    'queued_jobs' => 0,
                ];
            }

            $this->queue($lockedSources, $intervalMinutes);

            return [
                'queued_sources' => $lockedSources->count(),
                'skipped_sources' => max(0, $sources->count() - $lockedSources->count()),
                'queued_jobs' => 1,
            ];
        });
    }

    /**
     * @param  array<int, ResidentCsvSource>|Collection<int, ResidentCsvSource>  $sources
     */
    private function queue(array|Collection $sources, int $intervalMinutes): void
    {
        $sourceIds = [];
        $nextSyncAt = Carbon::now()->addMinutes(max(1, $intervalMinutes));

        foreach ($sources as $source) {
            $source->forceFill([
                'next_sync_at' => $nextSyncAt,
                'last_status' => 'queued',
                'last_error' => null,
            ])->save();

            $sourceIds[] = (int) $source->getKey();
        }

        SyncResidentCsvSource::dispatch($sourceIds)
            ->onConnection('database')
            ->onQueue('resident-sync');
    }

    private function isActive(ResidentCsvSource $source): bool
    {
        return in_array($source->last_status, ['queued', 'running'], true);
    }
}
