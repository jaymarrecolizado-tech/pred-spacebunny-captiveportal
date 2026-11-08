<?php

namespace App\Filament\Resources\SiteActions\Pages;

use App\Enums\ActionStatus;
use App\Filament\Resources\SiteActions\SiteActionResource;
use Filament\Resources\Pages\EditRecord;

class EditSiteAction extends EditRecord
{
    protected static string $resource = SiteActionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $status = $data['status'] ?? ActionStatus::Open->value;

        if (in_array($status, [ActionStatus::Resolved->value, ActionStatus::Verified->value], true) && blank($data['resolved_at'] ?? null)) {
            $data['resolved_at'] = now();
        }

        if ($status === ActionStatus::Verified->value && blank($data['verified_at'] ?? null)) {
            $data['verified_at'] = now();
        }

        return $data;
    }
}
