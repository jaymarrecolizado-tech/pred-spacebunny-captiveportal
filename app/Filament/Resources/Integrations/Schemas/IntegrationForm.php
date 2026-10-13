<?php

namespace App\Filament\Resources\Integrations\Schemas;

use App\Enums\IntegrationState;
use App\Models\Site;
use App\Models\Supplier;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class IntegrationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable(),

                Select::make('site_id')
                    ->label('Site')
                    // Null allows an approved supplier-level integration.
                    ->options(fn () => Site::query()->orderBy('site_code')->pluck('site_code', 'id'))
                    ->searchable()
                    ->helperText('Leave empty only for an approved supplier-level integration.'),

                Select::make('status')
                    ->options(IntegrationState::options())
                    ->default(IntegrationState::Active->value)
                    ->required(),

                Toggle::make('is_test_mode')
                    ->label('Test mode')
                    ->default(true)
                    ->helperText('A test integration cannot represent a production access decision.'),

                TextInput::make('last_error_code')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }
}
