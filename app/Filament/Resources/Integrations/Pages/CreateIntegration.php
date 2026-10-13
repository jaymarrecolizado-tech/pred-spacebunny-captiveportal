<?php

namespace App\Filament\Resources\Integrations\Pages;

use App\Filament\Resources\Integrations\IntegrationResource;
use App\Models\AuditLog;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateIntegration extends CreateRecord
{
    protected static string $resource = IntegrationResource::class;

    protected function afterCreate(): void
    {
        // The supplier must be able to configure the gateway, so the secret is
        // shown once here and never again without an audited reveal.
        AuditLog::record('created', $this->record, ['note' => 'Integration registered'], auth()->id());

        Notification::make()
            ->title('Integration created')
            ->body('Integration ID: '.$this->record->public_identifier)
            ->persistent()
            ->success()
            ->send();
    }
}
