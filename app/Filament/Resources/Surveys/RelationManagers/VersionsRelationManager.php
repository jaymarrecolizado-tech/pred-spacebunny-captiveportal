<?php

namespace App\Filament\Resources\Surveys\RelationManagers;

use App\Enums\SurveyVersionStatus;
use App\Models\Survey;
use App\Models\SurveyVersion;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * FR-SUR-003: questions live in a version. Editing a published version is not
 * possible; "New version" copies the current schema into the next draft.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('privacy_notice_version')
                    ->label('Privacy notice version')
                    ->maxLength(64)
                    ->helperText('Shown to the public before submission (FR-SUR-007).'),

                Repeater::make('schema_json')
                    ->label('Questions')
                    ->hiddenLabel()
                    ->schema([
                        TextInput::make('key')
                            ->required()
                            ->alphaDash()
                            ->maxLength(64)
                            ->helperText('Stable key, e.g. rating, issue_type.'),

                        Select::make('type')
                            ->options([
                                'rating' => 'Rating scale',
                                'single_choice' => 'Single choice',
                                'short_text' => 'Short text (optional)',
                                'info' => 'Information / privacy notice',
                            ])
                            ->required()
                            ->live(),

                        TextInput::make('label')->required()->maxLength(500),
                        TextInput::make('help')->maxLength(500),
                        Toggle::make('required'),

                        TextInput::make('min')->numeric()->default(1)->visible(fn ($get) => $get('type') === 'rating'),
                        TextInput::make('max')->numeric()->default(5)->visible(fn ($get) => $get('type') === 'rating'),

                        Repeater::make('options')
                            ->schema([
                                TextInput::make('value')->required()->maxLength(64),
                                TextInput::make('label')->required()->maxLength(255),
                            ])
                            ->columns(2)
                            ->visible(fn ($get) => $get('type') === 'single_choice'),

                        Toggle::make('reports_as_issue_category')
                            ->label('Counts as issue category in reports')
                            ->visible(fn ($get) => $get('type') === 'single_choice'),

                        TextInput::make('max_length')
                            ->numeric()
                            ->default(500)
                            ->visible(fn ($get) => $get('type') === 'short_text'),
                    ])
                    ->columns(2)
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('Add question'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('version_number')
            ->columns([
                TextColumn::make('version_number')->label('Version')->sortable(),
                TextColumn::make('question_count')
                    ->label('Questions')
                    ->state(fn (SurveyVersion $record) => count($record->questions())),
                TextColumn::make('privacy_notice_version')->label('Privacy notice'),
                TextColumn::make('status')
                    ->formatStateUsing(fn (SurveyVersionStatus $state) => $state->label())
                    ->badge(),
                TextColumn::make('published_at')->dateTime()->placeholder('—'),
            ])
            ->defaultSort('version_number', 'desc')
            ->headerActions([
                Action::make('newVersion')
                    ->label('New version')
                    ->icon('heroicon-o-plus')
                    ->visible(fn () => auth()->user()?->role->managesSurveys() ?? false)
                    ->action(function (): void {
                        /** @var Survey $survey */
                        $survey = $this->getOwnerRecord();
                        $latest = $survey->versions()->orderByDesc('version_number')->first();

                        $survey->versions()->create([
                            'version_number' => ($latest?->version_number ?? 0) + 1,
                            'schema_json' => $latest?->schema_json ?? ['questions' => []],
                            'privacy_notice_version' => $latest?->privacy_notice_version,
                            'status' => SurveyVersionStatus::Draft,
                        ]);

                        Notification::make()->title('Draft version created.')->success()->send();
                    }),
            ])
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (SurveyVersion $record) => $record->status === SurveyVersionStatus::Draft
                        && (auth()->user()?->role->managesSurveys() ?? false))
                    ->requiresConfirmation()
                    ->modalDescription('Publishing freezes this version. Later changes create a new version, so past answers keep their meaning.')
                    ->action(function (SurveyVersion $record): void {
                        /** @var Survey $survey */
                        $survey = $this->getOwnerRecord();

                        if ($record->questions() === []) {
                            Notification::make()
                                ->title('This version has no questions yet.')
                                ->body('Add at least one question before publishing.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update([
                            'status' => SurveyVersionStatus::Published,
                            'published_at' => now(),
                            'published_by' => auth()->id(),
                        ]);

                        // First published version becomes the live one.
                        if ($survey->published_version_id === null) {
                            $survey->update(['published_version_id' => $record->id]);
                        }

                        Notification::make()->title('Version '.$record->version_number.' published.')->success()->send();
                    }),

                Action::make('makeLive')
                    ->label('Make live')
                    ->visible(fn (SurveyVersion $record) => $record->status === SurveyVersionStatus::Published
                        && (auth()->user()?->role->managesSurveys() ?? false))
                    ->requiresConfirmation()
                    ->action(function (SurveyVersion $record): void {
                        $this->getOwnerRecord()->update(['published_version_id' => $record->id]);
                        Notification::make()->title('Live version updated.')->success()->send();
                    }),

                Action::make('delete')
                    ->label('Delete')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    // Published versions are protected by SurveyVersion::booted().
                    ->visible(fn (SurveyVersion $record) => $record->status === SurveyVersionStatus::Draft
                        && (auth()->user()?->role->managesSurveys() ?? false))
                    ->requiresConfirmation()
                    ->action(fn (SurveyVersion $record) => $record->delete()),
            ]);
    }
}
