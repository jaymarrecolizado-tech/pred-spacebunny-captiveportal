<?php

namespace App\Filament\Resources\Sites\Pages;

use App\Filament\Resources\Sites\Actions\ApplySurveyTriggerBulkAction;
use App\Filament\Resources\Sites\SiteResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSites extends ListRecords
{
    protected static string $resource = SiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderBulkActions(): array
    {
        return [
            // How DICT turns surveying on across many locations, or switches a
            // group off while leaving them registered and reporting.
            BulkActionGroup::make([
                ApplySurveyTriggerBulkAction::make(),
            ])->label('Survey frequency'),
        ];
    }
}
