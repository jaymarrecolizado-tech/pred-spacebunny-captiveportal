<?php

namespace App\Filament\Resources\Surveys\Tables;

use App\Enums\SurveyStatus;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('status')
                    ->formatStateUsing(fn (SurveyStatus $state) => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('publishedVersion.version_number')
                    ->label('Live version')
                    ->placeholder('none')
                    ->sortable(),
                TextColumn::make('versions_count')->counts('versions')->label('Versions'),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')->options(SurveyStatus::options()),
            ])
            ->recordActions([
                EditAction::make(),
                // FR-SUR-005: activate / pause / retire without losing history.
                Action::make('toggle-status')
                    ->label(fn ($record) => $record->status === SurveyStatus::Active ? 'Pause' : 'Activate')
                    ->icon(fn ($record) => $record->status === SurveyStatus::Active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->status === SurveyStatus::Active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $record->status = $record->status === SurveyStatus::Active
                            ? SurveyStatus::Paused
                            : SurveyStatus::Active;
                        $record->save();
                    })
                    // Edition of the survey content stays with survey administrators.
                    ->visible(fn () => auth()->user()?->role->managesSurveys() ?? false),
            ]);
    }
}
