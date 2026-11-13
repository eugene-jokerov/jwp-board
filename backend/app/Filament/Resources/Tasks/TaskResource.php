<?php

namespace App\Filament\Resources\Tasks;

use App\Filament\Resources\Tasks\Pages\CreateTask;
use App\Filament\Resources\Tasks\Pages\EditTask;
use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Filament\Resources\Tasks\Pages\ViewTask;
use App\Filament\Resources\Tasks\Schemas\TaskForm;
use App\Filament\Resources\Tasks\Schemas\TaskInfolist;
use App\Filament\Resources\Tasks\Tables\TasksTable;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Карточки доски в админке.
 *
 * Один ресурс на все три уровня (инициатива, проект, задача): в базе это одна
 * таблица, и модератору нужен единый список с фильтрами по типу и колонке,
 * а не три почти одинаковых раздела. Удаление карточки — через команду
 * DeleteTaskForAdmin: с каскадом (всё поддерево) или без него.
 */
class TaskResource extends Resource
{
    protected static ?string $model = EloquentTaskModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $navigationLabel = 'Карточки';

    protected static ?string $modelLabel = 'Карточка';

    protected static ?string $pluralModelLabel = 'Карточки';

    protected static string|UnitEnum|null $navigationGroup = 'Доска';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return TaskForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TaskInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TasksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTasks::route('/'),
            'create' => CreateTask::route('/create'),
            'view' => ViewTask::route('/{record}'),
            'edit' => EditTask::route('/{record}/edit'),
        ];
    }
}
