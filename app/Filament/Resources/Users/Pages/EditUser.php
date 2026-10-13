<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Only a super administrator reaches this page; deactivation is the
            // normal way to revoke access (FR-ADM-007).
            DeleteAction::make(),
        ];
    }
}
