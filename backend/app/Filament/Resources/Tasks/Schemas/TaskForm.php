<?php

namespace App\Filament\Resources\Tasks\Schemas;

use App\Filament\Resources\Tasks\Support\TaskOptions;
use App\Modules\Task\Domain\Task;
use App\Modules\Task\Domain\ValueObjects\CardColumn;
use App\Modules\Task\Domain\ValueObjects\CardPriority;
use App\Modules\Task\Domain\ValueObjects\CardType;
use App\Modules\Task\Domain\ValueObjects\TaskDescription;
use App\Modules\Task\Domain\ValueObjects\TaskSlug;
use App\Modules\Task\Domain\ValueObjects\TaskTitle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * Форма карточки в админке.
 *
 * Один ресурс на все три уровня доски: уровень задаётся типом карточки, а не
 * отдельной формой. Набор ссылок иерархии зависит от типа — у инициативы это
 * только направление, у проекта — направление и инициатива, у задачи — проект.
 * Согласованность ссылок всё равно проверяет домен (TaskPlacement): форма не
 * дублирует правило, а лишь подсказывает, что заполнять.
 *
 * Владелец задаётся при создании и не меняется при правке: карточка привязана
 * к доске своего пользователя, а ссылки иерархии работают в его границах.
 */
class TaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::placementSection(),
                self::hierarchySection(),
                self::contentSection(),
                self::propertiesSection(),
            ]);
    }

    /**
     * Где карточка: владелец, тип и колонка.
     */
    private static function placementSection(): Section
    {
        return Section::make('Где карточка')
            ->columns(3)
            ->schema([
                Select::make('user_id')
                    ->label('Владелец')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->live()
                    ->helperText('Владелец не меняется после создания карточки.'),
                Select::make('type')
                    ->label('Тип')
                    ->options(TaskOptions::types())
                    ->default(CardType::Task->value)
                    ->required()
                    ->disabledOn('edit')
                    ->dehydrated()
                    ->live()
                    ->helperText('Тип задаёт уровень доски и набор родительских ссылок.'),
                Select::make('column')
                    ->label('Колонка')
                    ->options(TaskOptions::columns())
                    ->default(CardColumn::Backlog->value)
                    ->required(),
            ]);
    }

    /**
     * Ссылки иерархии: какие поля видны, зависит от типа карточки.
     */
    private static function hierarchySection(): Section
    {
        return Section::make('Иерархия')
            ->columns(3)
            ->schema([
                Select::make('direction_id')
                    ->label('Направление')
                    ->relationship(
                        name: 'direction',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => self::ownedBy($query, $get('user_id')),
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => self::isInitiativeOrProject($get('type')))
                    ->afterStateHydrated(fn (Select $component): mixed => $component->state($component->getState())),
                Select::make('initiative_id')
                    ->label('Инициатива')
                    ->relationship(
                        name: 'initiative',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => self::ownedBy($query, $get('user_id'))
                            ->where('type', CardType::Initiative->value),
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('type') === CardType::Project->value)
                    ->afterStateHydrated(fn (Select $component): mixed => $component->state($component->getState()))
                    ->helperText('Проект без инициативы допустим — тогда и направления у него нет.'),
                Select::make('project_id')
                    ->label('Проект')
                    ->relationship(
                        name: 'project',
                        titleAttribute: 'title',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => self::ownedBy($query, $get('user_id'))
                            ->where('type', CardType::Project->value),
                    )
                    ->searchable()
                    ->preload()
                    ->visible(fn (Get $get): bool => $get('type') === CardType::Task->value)
                    ->afterStateHydrated(fn (Select $component): mixed => $component->state($component->getState()))
                    ->helperText('Направление и инициатива задачи наследуются от проекта.'),
            ]);
    }

    /**
     * Текстовые поля карточки.
     */
    private static function contentSection(): Section
    {
        return Section::make('Содержание')
            ->schema([
                TextInput::make('title')
                    ->label('Название')
                    ->required()
                    ->maxLength(TaskTitle::MAX_LENGTH),
                Textarea::make('description')
                    ->label('Описание')
                    ->rows(4)
                    ->maxLength(TaskDescription::MAX_LENGTH),
            ]);
    }

    /**
     * Свойства: приоритет, степень проработки, метка и срок.
     */
    private static function propertiesSection(): Section
    {
        return Section::make('Свойства')
            ->columns(3)
            ->schema([
                Select::make('priority')
                    ->label('Приоритет')
                    ->options(TaskOptions::priorities())
                    ->default(CardPriority::default()->value)
                    ->required(),
                Toggle::make('idea')
                    ->label('Идея')
                    ->helperText('Степень проработки имеет смысл только в беклоге.'),
                TextInput::make('slug')
                    ->label('Кодовое название')
                    ->maxLength(TaskSlug::MAX_LENGTH),
                DatePicker::make('deadline')
                    ->label('Дедлайн'),
            ]);
    }

    /**
     * Инициатива или проект: у них направление выбирается явно.
     */
    private static function isInitiativeOrProject(mixed $type): bool
    {
        return in_array($type, [CardType::Initiative->value, CardType::Project->value], true);
    }

    /**
     * Ограничить выборку ссылок иерархии карточками выбранного владельца.
     *
     * Без выбранного владельца список пуст: чужие карточки в родители не годятся,
     * а показывать их «на всякий случай» — значит дать выбрать заведомо
     * недопустимую ссылку.
     *
     * @param  Builder<Task>  $query
     * @return Builder<Task>
     */
    private static function ownedBy(Builder $query, mixed $userId): Builder
    {
        if (blank($userId)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('user_id', (int) $userId);
    }
}
