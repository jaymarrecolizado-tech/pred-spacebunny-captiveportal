<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Models\Supplier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SiteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Site identity')->schema([
                    Grid::make(2)->schema([
                        // FR-SITE-002/006: immutable registry identity.
                        TextInput::make('site_code')
                            ->label('Official site code')
                            ->required()
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->helperText('Immutable. Cannot be changed after the site is registered.'),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('region')->maxLength(255),
                        TextInput::make('province')->maxLength(255),
                        TextInput::make('municipality')->label('Municipality / city')->maxLength(255),
                        TextInput::make('address_or_landmark')->label('Address or landmark')->maxLength(255),
                    ]),
                ]),

                Section::make('Ownership and integration')->schema([
                    Grid::make(2)->schema([
                        Select::make('supplier_id')
                            ->label('Assigned supplier')
                            ->options(fn () => Supplier::query()->orderBy('name')->pluck('name', 'id'))
                            ->required()
                            ->searchable(),

                        Select::make('status')
                            ->options(RecordStatus::options())
                            ->default(RecordStatus::Active->value)
                            ->required()
                            ->helperText('Inactive sites stop accepting feedback; history is kept.'),

                        Select::make('integration_mode')
                            ->options(IntegrationMode::options())
                            ->default(IntegrationMode::QrCode->value)
                            ->required()
                            ->helperText('Only a captive portal can later produce verified attribution.'),

                        Select::make('integration_status')
                            ->options(IntegrationStatus::options())
                            ->default(IntegrationStatus::Draft->value)
                            ->required(),

                        TextInput::make('gateway_vendor')->label('Gateway vendor')->maxLength(255),
                        TextInput::make('gateway_model')->label('Gateway model')->maxLength(255),
                        DateTimePicker::make('commissioned_at')->label('Commissioned at'),
                        DateTimePicker::make('last_integration_test_at')->label('Last integration test'),
                    ]),
                ]),

                Section::make('Notes')->schema([
                    Textarea::make('notes')->rows(2)->maxLength(2000)->hiddenOn('table'),
                ]),
            ]);
    }
}
