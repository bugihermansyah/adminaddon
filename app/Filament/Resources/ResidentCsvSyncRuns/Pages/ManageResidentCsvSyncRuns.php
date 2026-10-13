<?php

namespace App\Filament\Resources\ResidentCsvSyncRuns\Pages;

use App\Filament\Resources\ResidentCsvSyncRuns\ResidentCsvSyncRunResource;
use Filament\Resources\Pages\ManageRecords;

class ManageResidentCsvSyncRuns extends ManageRecords
{
    protected static string $resource = ResidentCsvSyncRunResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
