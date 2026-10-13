<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\ScopeType;
use App\Enums\UserRole;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255)->unique(ignoreRecord: true),

                Select::make('role')
                    ->options(UserRole::options())
                    ->default(UserRole::Analyst->value)
                    ->required()
                    ->live()
                    ->helperText('Role decides what a user may do; scope decides which rows they may see.'),

                Select::make('scope_type')
                    ->label('Organizational scope')
                    ->options(ScopeType::options())
                    ->default(ScopeType::All->value)
                    ->required()
                    ->live(),

                Select::make('scope_supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id'))
                    ->visible(fn ($get) => $get('scope_type') === ScopeType::Supplier->value)
                    ->required(fn ($get) => $get('scope_type') === ScopeType::Supplier->value),

                TagsInput::make('scope_provinces')
                    ->label('Provinces')
                    ->placeholder('Add province names')
                    ->visible(fn ($get) => $get('scope_type') === ScopeType::Province->value),

                Select::make('is_active')
                    ->label('Active')
                    ->options(['1' => 'Active', '0' => 'Deactivated'])
                    ->default('1')
                    ->required(),

                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText('Leave blank when editing to keep the current password.'),
            ]);
    }
}
