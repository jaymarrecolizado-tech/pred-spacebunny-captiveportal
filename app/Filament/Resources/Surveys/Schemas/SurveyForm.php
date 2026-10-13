<?php

namespace App\Filament\Resources\Surveys\Schemas;

use App\Enums\SurveyStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SurveyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(2000),

                Select::make('status')
                    ->options(SurveyStatus::options())
                    ->default(SurveyStatus::Draft->value)
                    ->required()
                    ->helperText('Only an active survey with a published version accepts feedback.'),

                Select::make('scope_type')
                    ->label('Scope')
                    ->options([
                        'global' => 'All sites',
                        'site' => 'Specific sites only (via assignments)',
                    ])
                    ->default('global')
                    ->required(),
            ]);
    }
}
