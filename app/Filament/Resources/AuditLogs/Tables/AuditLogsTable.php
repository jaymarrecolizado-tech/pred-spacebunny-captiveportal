<?php

namespace App\Filament\Resources\AuditLogs\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime()->sortable(),
                TextColumn::make('actor.name')->label('Actor')->placeholder('system')->searchable(),
                TextColumn::make('action')->badge()->sortable(),
                TextColumn::make('entity_type')->label('Entity'),
                TextColumn::make('entity_id')->label('ID'),
                TextColumn::make('summary')
                    ->label('Change')
                    ->formatStateUsing(fn (?array $state) => $state === null ? '—' : json_encode($state))
                    ->wrap()
                    ->limit(120),
                TextColumn::make('correlation_id')->label('Correlation ID')->copyable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')->options([
                    'created' => 'Created',
                    'updated' => 'Updated',
                    'deleted' => 'Deleted',
                ]),
            ])
            // Append-only: no edit, no delete, ever.
            ->recordActions([])
            ->toolbarActions([]);
    }
}
