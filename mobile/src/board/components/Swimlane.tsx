/**
 * Свимлайн уровня: заголовок (цветная точка, название, счётчик) и колонки.
 *
 * На телефоне — вертикальный аккордеон колонок, заголовок сверху.
 * На широком экране заголовок выносится в левую колонку уровня
 * (`SwimlaneHeader`), а колонки уровня раскладывает `BoardLevel` общей сеткой.
 */

import { Pressable, Text, View } from 'react-native';

import { BoardColumn } from './BoardColumn';
import { columnCount, type CardAction } from '../derive';
import { COLUMNS, type Card, type CardKind, type ClosedTotals, type ColumnId } from '../types';

interface SwimlaneHeaderProps {
  title: string;
  color: string | null;
  /** Общее число карточек в свимлайне. */
  count: number;
  /** Подпись кнопки создания подсказывает тип карточки, например «+ Задача». */
  addLabel: string;
  canAdd: boolean;
  /** Свимлайн «без родителя»: кнопки «+» нет, родителя выбирают в форме уровня. */
  canAddInLane: boolean;
  onAddCard: () => void;
}

/**
 * Заголовок свимлайна для широкой раскладки: слева от сетки колонок.
 *
 * В одну строку название обрезалось (панель шириной 160 px), поэтому блок
 * разложен вертикально: название с цветной точкой сверху, счётчик и «+» — под ним.
 * «+» создаёт карточку в колонке, которую передал `BoardLevel`. У свимлайна
 * «без родителя» кнопки нет: там нет родителя для предопределения.
 */
export function SwimlaneHeader({
  title,
  color,
  count,
  addLabel,
  canAdd,
  canAddInLane,
  onAddCard,
}: SwimlaneHeaderProps) {
  const addDisabled = !canAdd || !canAddInLane;

  return (
    <View className="gap-1.5">
      <View className="flex-row items-start gap-1.5">
        <View className="mt-1.5 h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: color }} />

        <Text className="flex-1 text-[13px] font-semibold leading-4 text-ink" numberOfLines={3}>
          {title}
        </Text>
      </View>

      <View className="flex-row items-center gap-2">
        <Text className="flex-1 text-[11px] text-subtle">{count}</Text>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${addLabel}: ${title}`}
          accessibilityState={{ disabled: addDisabled }}
          disabled={addDisabled}
          onPress={onAddCard}
          hitSlop={6}
          className={[
            'h-6 w-6 items-center justify-center rounded',
            addDisabled ? 'bg-border opacity-70' : 'bg-app active:opacity-90',
          ].join(' ')}
        >
          <Text className={['text-sm font-semibold leading-6', addDisabled ? 'text-muted' : 'text-white'].join(' ')}>
            +
          </Text>
        </Pressable>
      </View>
    </View>
  );
}

interface SwimlaneProps {
  laneId: string;
  title: string;
  color: string | null;
  cards: readonly Card[];
  /** Тип карточки уровня: по нему в `closedTotals` лежит счётчик закрытых колонок. */
  kind: CardKind;
  /**
   * Полное число карточек закрытых колонок по парам «уровень + колонка»:
   * в «Готово» и «Отмене» загружены не все карточки, поэтому бейдж берёт
   * счётчик отсюда, а не из длины массива.
   */
  closedTotals: ClosedTotals;
  /** Состояние раскрытых колонок аккордеона: ключ — `${laneId}:${columnId}`. */
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  /** Действие карточки по её колонке; null — иконки нет. */
  cardAction: (card: Card) => CardAction | null;
  /** Выполнить действие карточки (перевод в следующую колонку). */
  onCardAction: (card: Card) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  /**
   * Можно ли создавать карточки в колонках свимлайна: у служебного свимлайна
   * «без родителя» кнопка создания заблокирована.
   */
  canCreate: boolean;
  onAddCard: (columnId: ColumnId) => void;
}

/** Свимлайн телефона: заголовок и вертикальный аккордеон колонок. */
export function Swimlane({
  laneId,
  title,
  color,
  cards,
  kind,
  closedTotals,
  expandedColumns,
  onToggleColumn,
  canCreate,
  cardAction,
  onCardAction,
  onOpenCard,
  onAddCard,
}: SwimlaneProps) {
  return (
    <View className="gap-2">
      <View className="flex-row items-center gap-2">
        <View className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color }} />

        <Text className="flex-1 text-sm font-semibold text-app" numberOfLines={1}>
          {title}
        </Text>

        <Text className="text-[11px] text-subtle">{cards.length}</Text>
      </View>

      <View className="gap-2">
        {COLUMNS.map((column) => {
          const key = `${laneId}:${column.id}`;
          const laneCards = cards.filter((card) => card.column === column.id);

          return (
            <BoardColumn
              key={column.id}
              column={column}
              cards={laneCards}
              count={columnCount(laneCards.length, column.id, kind, closedTotals)}
              expanded={expandedColumns[key] === true}
              onToggle={() => onToggleColumn(key)}
              canCreate={canCreate}
              addLabel="+ добавить"
              cardAction={cardAction}
              onCardAction={onCardAction}
              onOpenCard={onOpenCard}
              onAddCard={() => onAddCard(column.id)}
            />
          );
        })}
      </View>
    </View>
  );
}
