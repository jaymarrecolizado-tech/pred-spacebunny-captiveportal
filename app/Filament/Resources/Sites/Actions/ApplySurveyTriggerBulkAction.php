<?php

namespace App\Filament\Resources\Sites\Actions;

use App\Enums\SurveyTriggerMode;
use App\Models\Site;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;

/**
 * Applies the same survey trigger to many locations at once.
 *
 * DICT pilots a handful of sites and then rolls out. Doing that one edit at a
 * time across 193 registered locations is the kind of chore that stops
 * happening halfway, so this is a first-class action rather than a spreadsheet.
 */
class ApplySurveyTriggerBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('surveyTrigger')
            ->label('Set how often these locations ask for feedback')
            ->icon('heroicon-o-speedometer')
            ->deselectRecordsAfterCompletion()
            // Set on every selected site at once, which is the whole point.
            ->requiresConfirmation()
            ->modalHeading('Set survey frequency')
            ->modalDescription('This overwrites the current setting on every selected location.')
            ->form([
                Select::make('mode')
                    ->label('When to ask people for feedback')
                    ->options(SurveyTriggerMode::options())
                    ->required(),

                TextInput::make('days')
                    ->label('Days between asking the same person')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(365)
                    ->default(7)
                    ->required()
                    ->suffix('days')
                    ->visible(fn ($get) => $get('mode') === SurveyTriggerMode::Interval->value),
            ])
            ->action(function (Collection $records, array $data): void {
                $mode = SurveyTriggerMode::from($data['mode']);
                $days = (int) ($data['days'] ?? 7);

                $records->each(function (Site $site) use ($mode, $days): void {
                    // update() rather than save() so updated_at still moves and
                    // the site's own audit trail records who changed what.
                    $site->forceFill([
                        'survey_trigger_mode' => $mode->value,
                        'survey_trigger_days' => $days,
                    ])->save();
                });

                Notification::make()
                    ->title(sprintf(
                        'Updated %d location%s',
                        $records->count(),
                        $records->count() === 1 ? '' : 's',
                    ))
                    ->body($mode->label())
                    ->success()
                    ->send();
            });
    }
}
