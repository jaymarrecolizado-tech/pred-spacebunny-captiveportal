<?php

namespace App\Filament\Resources\Suppliers\Schemas;

use App\Enums\RecordStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupplierForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Supplier code')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true)
                    ->helperText('Short stable identifier, e.g. SUP-ALPHA.'),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Select::make('status')
                    ->options(RecordStatus::options())
                    ->default(RecordStatus::Active->value)
                    ->required(),

                TextInput::make('technical_contact_name')
                    ->label('Technical contact')
                    ->maxLength(255),

                TextInput::make('technical_contact_email')
                    ->label('Technical contact email')
                    ->email()
                    ->maxLength(255)
                    // Spec 10.1: restricted field, not exposed to supplier-facing views.
                    ->dehydrated(),

                Textarea::make('notes')
                    ->rows(3)
                    ->maxLength(2000),
            ]);
    }
}
