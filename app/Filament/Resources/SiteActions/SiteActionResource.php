<?php

namespace App\Filament\Resources\SiteActions;

use App\Filament\Resources\SiteActions\Pages\CreateSiteAction;
use App\Filament\Resources\SiteActions\Pages\EditSiteAction;
use App\Filament\Resources\SiteActions\Pages\ListSiteActions;
use App\Filament\Resources\SiteActions\Schemas\SiteActionForm;
use App\Filament\Resources\SiteActions\Tables\SiteActionsTable;
use App\Models\SiteAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SiteActionResource extends Resource
{
    protected static ?string $model = SiteAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Action items';

    protected static ?int $sort = 7;

    public static function form(Schema $schema): Schema
    {
        return SiteActionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SiteActionsTable::configure($table);
    }

    /**
     * Row-level scope is not enough for actions: the linked site must also be
     * within the user's scope.
     *
     * @return Builder<SiteAction>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['site:id,site_code,name,province,municipality', 'owner:id,name'])
            ->whereHas('site', fn (Builder $query): Builder => $query->visibleTo(auth()->user()));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSiteActions::route('/'),
            'create' => CreateSiteAction::route('/create'),
            'edit' => EditSiteAction::route('/{record}/edit'),
        ];
    }
}
