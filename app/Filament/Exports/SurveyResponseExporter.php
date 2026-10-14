<?php

namespace App\Filament\Exports;

use App\Models\SurveyResponse;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

/**
 * Raw response export (FR-REP-004, FR-REP-005).
 *
 * Columns are resolved per record, so the export carries the same site, rating
 * and attribution facts the admin table shows. Rows come from the resource's
 * query, which is already restricted by `visibleTo()` — a user cannot export
 * data their own table would not display.
 */
class SurveyResponseExporter extends Exporter
{
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('reference')
                ->label('Reference')
                ->state(fn (SurveyResponse $record) => substr($record->id, 0, 8)),

            ExportColumn::make('submitted_at')
                ->label('Submitted at')
                ->state(fn (SurveyResponse $record) => $record->submitted_at->toIso8601String()),

            ExportColumn::make('site_code')
                ->label('Site code')
                ->state(fn (SurveyResponse $record) => $record->site?->site_code),

            ExportColumn::make('site_name')
                ->label('Site name')
                ->state(fn (SurveyResponse $record) => $record->site?->name),

            ExportColumn::make('province')
                ->label('Province')
                ->state(fn (SurveyResponse $record) => $record->site?->province),

            ExportColumn::make('supplier')
                ->label('Supplier')
                ->state(fn (SurveyResponse $record) => $record->supplier?->name),

            ExportColumn::make('survey_version')
                ->label('Survey version')
                ->state(fn (SurveyResponse $record) => $record->surveyVersion?->version_number),

            ExportColumn::make('attribution_method')
                ->label('Attribution')
                ->state(fn (SurveyResponse $record) => $record->attribution_method->label()),

            ExportColumn::make('overall_rating')
                ->label('Rating')
                ->state(fn (SurveyResponse $record) => $record->overall_rating ?? 'unrated'),

            ExportColumn::make('issue_category')
                ->label('Issue')
                ->state(fn (SurveyResponse $record) => $record->issue_category ?? ''),

            // Free text is exported as stored; it may be sensitive, so the
            // export is an access-controlled action, not a public file.
            ExportColumn::make('comment')
                ->label('Comment')
                ->state(fn (SurveyResponse $record) => $record->answers_json['comment'] ?? ''),
        ];
    }

    public static function getModel(): string
    {
        return SurveyResponse::class;
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Your export is ready to download. It contains only the responses you are permitted to see.';
    }
}
