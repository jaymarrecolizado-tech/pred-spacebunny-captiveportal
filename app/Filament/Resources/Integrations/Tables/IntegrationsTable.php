<?php

namespace App\Filament\Resources\Integrations\Tables;

use App\Enums\IntegrationState;
use App\Models\AuditLog;
use App\Models\Integration;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class IntegrationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.site_code')->label('Site')->placeholder('all sites')->searchable(),
                TextColumn::make('supplier.name')->label('Supplier'),
                // Shown truncated: it is an identifier, not a secret.
                TextColumn::make('public_identifier')
                    ->label('Integration ID')
                    ->formatStateUsing(fn (string $state) => substr($state, 0, 8).'…')
                    ->copyable()
                    ->searchable(),
                IconColumn::make('is_test_mode')->label('Test mode')->boolean(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (IntegrationState $state) => $state->label())
                    ->badge(),
                TextColumn::make('last_success_at')->label('Last success')->placeholder('never'),
                TextColumn::make('last_error_code')->label('Last error')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('id')
            ->filters([
                TernaryFilter::make('is_test_mode')->label('Test mode'),
            ])
            ->recordActions([
                Action::make('reveal')
                    ->label('Show secret')
                    ->icon('heroicon-o-eye')
                    // Shown only on demand, and every reveal is audited.
                    ->action(function (Integration $record): void {
                        $this->recordAudit($record, 'revealed');
                        Notification::make()
                            ->title('Integration secret')
                            ->body($record->secret)
                            ->persistent()
                            ->success()
                            ->send();
                    }),

                Action::make('rotate')
                    ->label('Rotate secret')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('The previous secret stops working immediately. Update the gateway before rotating if it cannot be updated in the same window.')
                    ->action(function (Integration $record): void {
                        $record->update(['secret' => Integration::generateSecret()]);
                        $this->recordAudit($record, 'rotated');
                        Notification::make()->title('Secret rotated.')->success()->send();
                    }),

                Action::make('revoke')
                    ->label('Revoke')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Integration $record) => $record->revoked_at === null)
                    ->action(function (Integration $record): void {
                        $record->update(['revoked_at' => now(), 'status' => IntegrationState::Revoked]);
                        $this->recordAudit($record, 'revoked');
                        Notification::make()->title('Integration revoked.')->success()->send();
                    }),

                EditAction::make(),
            ]);
    }

    /**
     * Credential events are security-relevant, so they land in the audit trail
     * alongside ordinary changes.
     */
    protected function recordAudit(Integration $record, string $action): void
    {
        AuditLog::record($action, $record, ['note' => 'Integration credential event']);
    }
}
