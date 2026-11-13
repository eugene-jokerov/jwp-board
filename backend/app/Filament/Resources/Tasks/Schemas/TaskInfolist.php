<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Filament\Resources\Directions\DirectionResource;
use App\Filament\Resources\Tasks\TaskResource;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Карточка просмотра карточки в админке.
 *
 * Показываются все поля карточки и её место в иерархии: родительские карточки
 * выводятся по названию, потому что модератору важно видеть дерево, а не id.
 */
class TaskInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Карточка')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('title')
                            ->label('Название'),
                        TextEntry::make('slug')
                            ->label('Кодовое название')
                            ->placeholder('—'),
                        TextEntry::make('type')
                            ->label('Тип')
                            ->formatStateUsing(fn (CardType $state): string => ucfirst($state->label())),
                        TextEntry::make('column')
                            ->label('Колонка')
                            ->formatStateUsing(fn (CardColumn $state): string => $state->label()),
                        TextEntry::make('priority')
                            ->label('Приоритет')
                            ->formatStateUsing(fn (CardPriority $state): string => $state->label()),
                        IconEntry::make('idea')
                            ->label('Идея')
                            ->boolean(),
                        TextEntry::make('description')
                            ->label('Описание')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                Section::make('Иерархия')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('user.email')
                            ->label('Владелец'),
                        // Ссылки на связанные карточки: модератору нужно переходить
                        // по дереву, не разыскивая родителя в общем списке.
                        TextEntry::make('direction.title')
                            ->label('Направление')
                            ->placeholder('—')
                            ->url(fn (Task $record): ?string => $record->direction_id === null
                                ? null
                                : DirectionResource::getUrl('view', ['record' => $record->direction_id])),
                        TextEntry::make('initiative.title')
                            ->label('Инициатива')
                            ->placeholder('—')
                            ->url(fn (Task $record): ?string => $record->initiative_id === null
                                ? null
                                : TaskResource::getUrl('view', ['record' => $record->initiative_id])),
                        TextEntry::make('project.title')
                            ->label('Проект')
                            ->placeholder('—')
                            ->url(fn (Task $record): ?string => $record->project_id === null
                                ? null
                                : TaskResource::getUrl('view', ['record' => $record->project_id])),
                    ]),
                Section::make('Служебное')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID'),
                        TextEntry::make('deadline')
                            ->label('Дедлайн')
                            ->dateTime('d.m.Y')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Создана')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('updated_at')
                            ->label('Обновлена')
                            ->dateTime('d.m.Y H:i'),
                        TextEntry::make('children_count')
                            ->label('Дочерних карточек')
                            ->state(fn (Task $record): int => EloquentTaskModel::query()
                                ->where('initiative_id', $record->id)
                                ->orWhere('project_id', $record->id)
                                ->count()),
                    ]),
            ]);
    }
}
