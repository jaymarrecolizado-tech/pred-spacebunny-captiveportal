<?php

namespace App\Filament\Resources\SiteActions\Tables;

use App\Enums\ActionStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SiteActionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site.site_code')
                    ->label('Site')
                    ->description(fn ($record) => $record->site?->name)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ActionStatus $state) => $state->label())
                    ->color(fn (ActionStatus $state): string => match ($state) {
                        ActionStatus::Open => 'gray',
                        ActionStatus::InProgress => 'warning',
                        ActionStatus::Resolved => 'info',
                        ActionStatus::Verified => 'success',
                    })
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('Owner')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('due_date')
                    ->date()
                    ->placeholder('No due date')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('due_date')
            ->filters([
                SelectFilter::make('status')->options(ActionStatus::options()),
                SelectFilter::make('owner')->relationship('owner', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
