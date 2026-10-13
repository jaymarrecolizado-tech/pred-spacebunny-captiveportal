<?php

namespace App\Filament\Resources\SurveyResponses;

use App\Filament\Resources\SurveyResponses\Tables\SurveyResponsesTable;
use App\Models\SurveyResponse;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'Feedback responses';

    protected static ?string $modelLabel = 'feedback response';

    public static function table(Table $table): Table
    {
        return SurveyResponsesTable::configure($table);
    }

    /**
     * FR-REP-005/FR-ADM-002: rows follow the signed-in user's scope, exactly as
     * any future CSV export will.
     *
     * @return Builder<SurveyResponse>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->visibleTo(auth()->user())
            ->with(['site', 'supplier']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSurveyResponses::route('/'),
        ];
    }
}
