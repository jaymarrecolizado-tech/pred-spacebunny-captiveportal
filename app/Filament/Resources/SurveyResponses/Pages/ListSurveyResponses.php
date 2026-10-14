<?php

namespace App\Filament\Resources\SurveyResponses\Pages;

use App\Filament\Exports\SurveyResponseExporter;
use App\Filament\Resources\SurveyResponses\SurveyResponseResource;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListSurveyResponses extends ListRecords
{
    protected static string $resource = SurveyResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // FR-REP-004. The table is already scoped by visibleTo(), and the
            // exporter reads the same query, so the file can never contain rows
            // the user could not see on screen.
            ExportAction::make()
                ->exporter(SurveyResponseExporter::class)
                // Spec 7.1: stream rather than loading every response at once.
                ->chunkSize(500),
        ];
    }
}
