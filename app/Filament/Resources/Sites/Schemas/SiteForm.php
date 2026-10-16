<?php

namespace App\Filament\Resources\Sites\Schemas;

use App\Enums\IntegrationMode;
use App\Enums\IntegrationStatus;
use App\Enums\RecordStatus;
use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use App\Models\Supplier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
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

                Section::make('Gateway link')
                    ->description('How this location is matched to the Wi-Fi controller.')
                    ->schema([
                        TextInput::make('omada_site_id')
                            ->label('Controller site ID')
                            ->maxLength(64)
                            ->unique(ignoreRecord: true)
                            ->helperText('Filled in automatically when the locations are imported from the controller. Leave blank for a location the controller does not know about.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Who gets surveyed here')
                    ->description('Controls how often someone must answer the survey before their internet works.')
                    ->schema([
                        Select::make('survey_trigger_mode')
                            ->label('When to ask people for feedback')
                            ->options(SurveyTriggerMode::options())
                            ->default(SurveyTriggerMode::Always->value)
                            ->required()
                            ->live()
                            // Filament v5 Select has no per-option description, so
                            // the chosen mode explains itself underneath.
                            ->helperText(fn ($get) => SurveyTriggerMode::tryFrom((string) $get('survey_trigger_mode'))
                                ?->description() ?? '')
                            ->columnSpanFull(),

                        TextInput::make('survey_trigger_days')
                            ->label('Days between asking the same person')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(7)
                            ->required()
                            ->suffix('days')
                            // Only meaningful for the interval mode; showing it
                            // otherwise would imply a setting that does nothing.
                            ->visible(fn ($get) => $get('survey_trigger_mode') === SurveyTriggerMode::Interval->value)
                            ->helperText('Someone who answered within this window gets straight back in without seeing the survey.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Turn this on in the Wi-Fi system')
                    ->description('One step, done once per location. The Wi-Fi system does not let our software do this for you.')
                    ->schema([
                        Placeholder::make('copy_url')
                            ->label('Step 1 — copy this address')
                            ->content(fn (?Site $record) => $record === null
                                ? 'Save the location first.'
                                : route('portal.omada', ['siteCode' => $record->site_code]))
                            // Selectable so it can be copied rather than retyped.
                            // A mistyped address fails silently, which is the whole
                            // problem this section exists to prevent.
                            ->extraAttributes(['style' => 'font-family:monospace;user-select:all;font-size:1rem']),

                        Placeholder::make('step2')
                            ->label('Step 2 — in the Wi-Fi system')
                            ->content('Open this location. Go to Network Config, then Authentication, then Portal. '
                                .'Click Create New Portal. Name it anything. '
                                .'Choose the Wi-Fi network. '
                                .'Under "Authentication Type" choose External Portal Server. '
                                .'Paste the address above into the box next to it, then Save.')
                            ->extraAttributes(['style' => 'white-space:normal']),

                        Placeholder::make('step3')
                            ->label('Step 3 — come back and check')
                            ->content(fn (?Site $record) => $record?->surveyStatus()['hint'] ?? '')
                            ->extraAttributes(['style' => 'white-space:normal']),
                    ]),

                Section::make('Notes')->schema([
                    Textarea::make('notes')->rows(2)->maxLength(2000)->hiddenOn('table'),
                ]),
            ]);
    }
}
