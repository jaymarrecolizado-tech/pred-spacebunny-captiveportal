<?php

namespace App\Filament\Resources\Sites\Tables;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SitesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('site_code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
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
