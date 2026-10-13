<?php

namespace App\Filament\Resources\Suppliers\Tables;

use App\Enums\RecordStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->sortable()->searchable(),
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('sites_count')->counts('sites')->label('Sites')->sortable(),
                TextColumn::make('technical_contact_name')->label('Technical contact')->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (RecordStatus $state) => $state->label())
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('status')->options(RecordStatus::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // Bulk delete is blocked by SupplierPolicy::deleteAny.
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
