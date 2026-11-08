<?php

namespace App\Filament\Resources\SiteActions\Schemas;

use App\Enums\ActionStatus;
use App\Models\Site;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class SiteActionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('site_id')
                    ->label('Site')
                    ->options(fn (): array => Site::query()
                        ->visibleTo(auth()->user())
                        ->orderBy('site_code')
                        ->get()
                        ->mapWithKeys(fn (Site $site): array => [
                            $site->id => $site->site_code.' — '.$site->name,
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true),

                Select::make('owner_id')
                    ->label('Owner')
                    ->options(fn (): array => User::query()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->placeholder('Unassigned'),

                Select::make('status')
                    ->options(ActionStatus::options())
                    ->default(ActionStatus::Open->value)
                    ->required()
                    ->helperText('Resolved means someone fixed it; Verified means someone checked it after the fix.'),

                DatePicker::make('due_date')
                    ->label('Due date'),

                Textarea::make('notes')
                    ->label('What needs to be done')
                    ->rows(4)
                    ->maxLength(2000)
                    ->required(),

                Textarea::make('resolution_notes')
                    ->label('What was done')
                    ->rows(4)
                    ->maxLength(2000),
            ]);
    }
}
