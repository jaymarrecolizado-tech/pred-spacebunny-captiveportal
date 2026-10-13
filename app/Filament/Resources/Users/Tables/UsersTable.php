<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->formatStateUsing(fn (UserRole $state) => $state->label())
                    ->badge()
                    ->sortable(),
                TextColumn::make('scope_type')
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->badge()
                    ->color('gray'),
                TextColumn::make('scopeSupplier.name')->label('Supplier scope'),
                TextColumn::make('scope_provinces')->label('Provinces')->placeholder('—'),
                // FR-ADM-007
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('role')->options(UserRole::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
