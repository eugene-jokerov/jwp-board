<?php

namespace App\Filament\Resources\Directions\RelationManagers;

use App\Filament\Resources\Tasks\TaskResource;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use BackedEnum;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Карточки направления на странице направления.
 *
 * Список задуман как проверка перед удалением направления: здесь видно, что
 * именно лежит в направлении, и понятно, почему оно не удаляется. Правка и
 * удаление карточек — в разделе «Карточки», чтобы модерация не дублировалась.
 *
 * Блок показывается только на странице просмотра: в форме редактирования он
 * занимает место под формой и мешает работе с самим направлением, а роль
 * «посмотреть карточки перед удалением» относится именно к просмотру.
 */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Карточки';

    protected static string|BackedEnum|null $icon = Heroicon::OutlinedSquares2x2;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return is_a($pageClass, ViewRecord::class, true);
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user')->orderBy('id'))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    // Кликабельный id ведёт на просмотр карточки: из направления
                    // модератор попадает в карточку, не переключаясь в общий список.
                    ->url(fn (Task $record): string => TaskResource::getUrl('view', ['record' => $record])),
                TextColumn::make('title')
                    ->label('Карточка')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (CardType $state): string => ucfirst($state->label())),
                TextColumn::make('column')
                    ->label('Колонка')
                    ->badge()
                    ->formatStateUsing(fn (CardColumn $state): string => $state->label()),
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->formatStateUsing(fn (CardPriority $state): string => $state->label()),
                IconColumn::make('idea')
                    ->label('Идея')
                    ->boolean(),
                TextColumn::make('user.email')
                    ->label('Владелец')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id');
    }
}
