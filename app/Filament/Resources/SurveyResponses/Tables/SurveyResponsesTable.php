<?php

namespace App\Filament\Resources\SurveyResponses\Tables;

use App\Enums\AttributionMethod;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Services\Surveys\DemographicQuestions;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResponsesTable
{
    /**
     * Sentinel for "the person did not answer this one".
     *
     * Almost every response so far has no gender or sector, because the
     * questions were added later. Filtering for them is how you find the
     * answers that predate the questions.
     */
    private const NOT_ANSWERED = '__not_answered__';

    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('Reference')->limit(8)->searchable(),
                TextColumn::make('site.name')->label('Site')->searchable()->sortable(),
                TextColumn::make('site.province')->label('Province')->toggleable(),
                TextColumn::make('supplier.name')->label('Supplier')->toggleable(),
                TextColumn::make('overall_rating')->label('Rating')->placeholder('—')->sortable(),
                TextColumn::make('issue_category')->label('Issue')->toggleable(),
                // Gender and sector, as the person answered them, not as the
                // survey stored them.
                TextColumn::make('gender')
                    ->label('Gender')
                    ->formatStateUsing(fn (?string $state) => DemographicQuestions::label('gender', $state) ?? '—')
                    ->placeholder('—')
                    ->toggleable()
                    ->sortable(),
                TextColumn::make('sector')
                    ->label('Sector')
                    ->formatStateUsing(fn (?string $state) => DemographicQuestions::label('sector', $state) ?? '—')
                    ->placeholder('—')
                    ->toggleable()
                    ->sortable(),
                // FR-REP-006: verified and unverified must stay distinguishable.
                TextColumn::make('attribution_method')
                    ->label('Attribution')
                    ->formatStateUsing(fn (AttributionMethod $state) => $state->label())
                    ->badge()
                    ->color(fn (AttributionMethod $state) => $state->isVerified() ? 'success' : 'gray')
                    ->sortable(),
                TextColumn::make('submitted_at')->dateTime()->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('attribution_method')->options(AttributionMethod::options()),
                SelectFilter::make('site')->relationship('site', 'name'),
                self::demographicFilter('gender', 'Gender'),
                self::demographicFilter('sector', 'Sector'),
            ])
            // Responses are read-only in this phase (SurveyResponsePolicy).
            ->recordActions([])
            ->toolbarActions([]);
    }

    /**
     * Filter on one of the demographic answers, including the people who did
     * not answer it.
     */
    private static function demographicFilter(string $column, string $label): SelectFilter
    {
        return SelectFilter::make($column)
            ->label($label)
            ->options([
                ...DemographicQuestions::optionLabels()[$column] ?? [],
                self::NOT_ANSWERED => 'Did not answer',
            ])
            ->query(function (Builder $query, array $data) use ($column): Builder {
                // A select filter hands over its choice as "value".
                $value = $data['value'] ?? null;

                if ($value === null) {
                    return $query;
                }

                return $value === self::NOT_ANSWERED
                    ? $query->whereNull($column)
                    : $query->where($column, $value);
            });
    }

    /**
     * Filter helper reused by exports and future reports.
     *
     * @param  Builder<SurveyResponse>  $query
     */
    public static function applyScope(Builder $query, User $user): Builder
    {
        return $query->visibleTo($user);
    }
}
