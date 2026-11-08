<?php

namespace App\Filament\Resources\SiteActions\Pages;

use App\Filament\Resources\SiteActions\SiteActionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSiteActions extends ListRecords
{
    protected static string $resource = SiteActionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
