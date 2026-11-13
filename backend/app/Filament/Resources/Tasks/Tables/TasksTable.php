<?php

namespace App\Filament\Resources\Tasks\Tables;

use App\Filament\Resources\Tasks\Support\TaskOptions;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Infrastructure\Persistence\EloquentTaskModel;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Список карточек в админке.
 *
 * Таблица ведёт по доске: сверху фильтры «тип / колонка / владелец», дальше
 * карточки со ссылками на направление и владельца. Удаление карточки и её
 * поддерева — в действиях страниц (ListTasks / EditTask), здесь только выборка.
 */
class TasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['user', 'direction', 'initiative', 'project']))
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Карточка')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (CardType $state): string => ucfirst($state->label()))
                    ->sortable(),
                TextColumn::make('column')
                    ->label('Колонка')
                    ->badge()
                    ->formatStateUsing(fn (CardColumn $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Приоритет')
                    ->formatStateUsing(fn (CardPriority $state): string => $state->label())
                    ->toggleable(),
                IconColumn::make('idea')
                    ->label('Идея')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('user.email')
                    ->label('Владелец')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('direction.title')
                    ->label('Направление')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('deadline')
                    ->label('Дедлайн')
                    ->date('d.m.Y')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Обновлена')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Тип')
                    ->options(TaskOptions::types()),
                SelectFilter::make('column')
                    ->label('Колонка')
                    ->options(TaskOptions::columns()),
                SelectFilter::make('priority')
                    ->label('Приоритет')
                    ->options(TaskOptions::priorities()),
                TernaryFilter::make('idea')
                    ->label('Идея'),
                SelectFilter::make('user_id')
                    ->label('Владелец')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('id', 'desc')
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    /**
     * Сколько потомков у карточки — для подписи в диалоге удаления.
     */
    public static function descendantsCount(Task $task): int
    {
        return EloquentTaskModel::query()
            ->where('initiative_id', $task->id)
            ->orWhere('project_id', $task->id)
            ->count();
    }
}
