<?php

namespace App\Filament\Resources\SurveyResponses\Tables;

use App\Enums\AttributionMethod;
use App\Models\SurveyResponse;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResponsesTable
{
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
            ])
            // Responses are read-only in this phase (SurveyResponsePolicy).
            ->recordActions([])
            ->toolbarActions([]);
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
