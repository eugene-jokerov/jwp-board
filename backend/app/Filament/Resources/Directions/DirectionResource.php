<?php

namespace App\Filament\Resources\Directions;

use App\Filament\Resources\Directions\Pages\CreateDirection;
use App\Filament\Resources\Directions\Pages\EditDirection;
use App\Filament\Resources\Directions\Pages\ListDirections;
use App\Filament\Resources\Directions\Pages\ViewDirection;
use App\Filament\Resources\Directions\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Directions\Schemas\DirectionForm;
use App\Filament\Resources\Directions\Schemas\DirectionInfolist;
use App\Filament\Resources\Directions\Tables\DirectionsTable;
use App\Modules\Direction\Infrastructure\Persistence\EloquentDirectionModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Направления доски в админке.
 *
 * Направление — корень иерархии карточки, поэтому здесь же видно, сколько
 * карточек в нём лежит: для модерации это основной признак «пустое или нет»
 * (пустое удаляется, непустое — только после чистки карточек).
 */
class DirectionResource extends Resource
{
    protected static ?string $model = EloquentDirectionModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static ?string $navigationLabel = 'Направления';

    protected static ?string $modelLabel = 'Направление';

    protected static ?string $pluralModelLabel = 'Направления';

    protected static string|UnitEnum|null $navigationGroup = 'Доска';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return DirectionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return DirectionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DirectionsTable::configure($table);
    }

    /**
     * Карточки направления — на странице просмотра (проверка перед удалением).
     *
     * @return array<int, class-string<TasksRelationManager>>
     */
    public static function getRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDirections::route('/'),
            'create' => CreateDirection::route('/create'),
            'view' => ViewDirection::route('/{record}'),
            'edit' => EditDirection::route('/{record}/edit'),
        ];
    }
}
