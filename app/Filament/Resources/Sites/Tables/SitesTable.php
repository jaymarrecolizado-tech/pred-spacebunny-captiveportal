<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),

                // One column that answers "will this actually do anything?".
                // Three separate columns let someone tick a switch and see
                // nothing happen with no explanation.
                TextColumn::make('survey_status')
                    ->label('Surveying')
                    ->badge()
                    ->state(fn (Site $record) => $record->surveyStatus()['label'])
                    ->color(fn (Site $record) => $record->surveyStatus()['tone'])
                    ->description(fn (Site $record) => $record->surveyStatus()['hint']),

                TextColumn::make('portal_last_seen_at')
                    ->label('Last connection seen')
                    ->since()
                    ->placeholder('Never')
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('omada_site_id')
                    ->label('On controller')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => $state ? 'Yes' : 'Not linked')
                    ->color(fn (?string $state) => $state ? 'info' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('province')->searchable()->sortable(),
                TextColumn::make('supplier.name')->label('Supplier')->searchable()->sortable(),
                TextColumn::make('integration_mode')
                    ->formatStateUsing(fn (IntegrationMode $state) => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('integration_status')
                    ->formatStateUsing(fn (IntegrationStatus $state) => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('responses_count')->counts('responses')->label('Responses')->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (RecordStatus $state) => $state->label())
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')->options(RecordStatus::options()),
                SelectFilter::make('integration_mode')->options(IntegrationMode::options()),
                SelectFilter::make('integration_status')->options(IntegrationStatus::options()),
                SelectFilter::make('supplier')->relationship('supplier', 'name'),
                // With 193 imported locations, "which ones are actually live" is
                // the filter people will actually use.
                SelectFilter::make('survey_trigger_mode')
                    ->label('Asking for feedback')
                    ->options(SurveyTriggerMode::options()),

                SelectFilter::make('portal_last_seen_at')
                    ->label('Controller connection')
                    ->options([
                        'seen' => 'Has connected at least once',
                        'never' => 'Has never connected',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'seen' => $query->whereNotNull('portal_last_seen_at'),
                            'never' => $query->whereNull('portal_last_seen_at'),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('qr')
                    ->label('QR poster')
                    ->icon('heroicon-o-qr-code')
                    ->url(fn ($record) => route('site.qr', ['siteCode' => $record->site_code]))
                    ->openUrlInNewTab(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // FR-SITE-007: soft delete only, history is never erased.
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
