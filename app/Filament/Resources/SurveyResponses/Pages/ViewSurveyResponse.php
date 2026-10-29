<?php

namespace App\Filament\Resources\SurveyResponses\Pages;

use App\Filament\Resources\SurveyResponses\SurveyResponseResource;
use App\Models\SurveyResponse;
use App\Services\Surveys\DemographicQuestions;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSurveyResponse extends ViewRecord
{
    protected static string $resource = SurveyResponseResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Response')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('reference')
                            ->label('Reference')
                            ->state(fn (SurveyResponse $record) => substr($record->id, 0, 8)),

                        TextEntry::make('submitted_at')
                            ->label('Submitted at')
                            ->dateTime(),

                        TextEntry::make('site.name')
                            ->label('Site'),

                        TextEntry::make('site.province')
                            ->label('Province')
                            ->placeholder('—'),

                        TextEntry::make('supplier.name')
                            ->label('Supplier')
                            ->placeholder('—'),

                        TextEntry::make('attribution_method')
                            ->label('Attribution')
                            ->formatStateUsing(fn ($state) => $state->label())
                            ->badge(),
                    ]),

                Section::make('Answers')
                    ->schema([
                        TextEntry::make('overall_rating')
                            ->label('Rating')
                            ->placeholder('—')
                            ->suffix('/5'),

                        TextEntry::make('issue_category')
                            ->label('Issue')
                            ->formatStateUsing(fn (?string $state) => $state ? ucfirst(str_replace('_', ' ', $state)) : '—')
                            ->placeholder('—'),

                        TextEntry::make('gender')
                            ->label('Gender')
                            ->formatStateUsing(fn (?string $state) => DemographicQuestions::label('gender', $state) ?? '—')
                            ->placeholder('—'),

                        TextEntry::make('sector')
                            ->label('Sector')
                            ->formatStateUsing(fn (?string $state) => DemographicQuestions::label('sector', $state) ?? '—')
                            ->placeholder('—'),

                        KeyValueEntry::make('answers_json')
                            ->label('All answers')
                            ->keyLabel('Question')
                            ->valueLabel('Answer')
                            ->state(fn (SurveyResponse $record) => $this->answerLabels($record)),
                    ]),

                Section::make('Comments')
                    ->visible(fn (SurveyResponse $record) => filled($record->answers_json['comment'] ?? null))
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Written comment')
                            ->state(fn (SurveyResponse $record) => $record->answers_json['comment'] ?? '')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * The answers, keyed by their question label rather than their raw key.
     *
     * @return array<string, string>
     */
    private function answerLabels(SurveyResponse $record): array
    {
        $version = $record->surveyVersion;
        $questions = $version ? $version->questions() : [];
        $labels = collect($questions)->keyBy('key');
        $answers = $record->answers_json ?? [];

        $result = [];

        foreach ($answers as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $label = $labels->has($key) ? ($labels[$key]['label'] ?? $key) : $key;
            $result[$label] = is_array($value) ? implode(', ', $value) : (string) $value;
        }

        return $result;
    }
}
