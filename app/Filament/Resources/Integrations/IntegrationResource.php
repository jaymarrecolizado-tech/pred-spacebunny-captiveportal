<?php

namespace App\Filament\Resources\Integrations;

use App\Filament\Resources\Integrations\Pages\CreateIntegration;
use App\Filament\Resources\Integrations\Pages\EditIntegration;
use App\Filament\Resources\Integrations\Pages\ListIntegrations;
use App\Filament\Resources\Integrations\Schemas\IntegrationForm;
use App\Filament\Resources\Integrations\Tables\IntegrationsTable;
use App\Models\Integration;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class IntegrationResource extends Resource
{
    protected static ?string $model = Integration::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLink;

    protected static ?string $navigationLabel = 'Gateway integrations';

    protected static ?string $recordTitleAttribute = 'public_identifier';

    public static function form(Schema $schema): Schema
    {
        return IntegrationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return IntegrationsTable::configure($table);
    }

    /**
     * FR-CAP-003: registration and credential handling are DICT-side work.
     */
    public static function canViewAny(): bool
    {
        return auth()->user()?->role->managesRegistry() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    protected static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['public_identifier'] = Integration::generateIdentifier();
        $data['secret'] = Integration::generateSecret();

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListIntegrations::route('/'),
            'create' => CreateIntegration::route('/create'),
            'edit' => EditIntegration::route('/{record}/edit'),
        ];
    }
}
