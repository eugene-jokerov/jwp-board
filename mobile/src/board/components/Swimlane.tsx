/**
 * Свимлайн уровня: заголовок (цветная точка, название, счётчик) и колонки.
 *
 * На телефоне — вертикальный аккордеон колонок, заголовок сверху.
 * На широком экране заголовок выносится в левую колонку уровня
 * (`SwimlaneHeader`), а колонки уровня раскладывает `BoardLevel` общей сеткой.
 */

import { Pressable, Text, View } from 'react-native';

import { BoardColumn } from './BoardColumn';
import { NEUTRAL_COLOR } from '../colors';
import { COLUMNS, type Card, type ColumnId, type MoveDirection } from '../types';

interface SwimlaneHeaderProps {
  title: string;
  color: string | null;
  /** Общее число карточек в свимлайне. */
  count: number;
  /** Подпись кнопки создания подсказывает тип карточки, например «+ Задача». */
  addLabel: string;
  canAdd: boolean;
  onAddCard: () => void;
}

/**
 * Заголовок свимлайна для широкой раскладки: слева от сетки колонок.
 *
 * В одну строку название обрезалось (панель шириной 160 px), поэтому блок
 * разложен вертикально: название с цветной точкой сверху, счётчик и «+» — под ним.
 * «+» создаёт карточку в колонке, которую передал `BoardLevel`.
 */
export function SwimlaneHeader({ title, color, count, addLabel, canAdd, onAddCard }: SwimlaneHeaderProps) {
  return (
    <View className="gap-1.5">
      <View className="flex-row items-start gap-1.5">
        <View className="mt-1.5 h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: color ?? NEUTRAL_COLOR }} />

        <Text className="flex-1 text-[13px] font-semibold leading-4 text-ink" numberOfLines={3}>
          {title}
        </Text>
      </View>

      <View className="flex-row items-center gap-2">
        <Text className="flex-1 text-[11px] text-subtle">{count}</Text>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`${addLabel}: ${title}`}
          accessibilityState={{ disabled: !canAdd }}
          disabled={!canAdd}
          onPress={onAddCard}
          hitSlop={6}
          className={[
            'h-6 w-6 items-center justify-center rounded',
            canAdd ? 'bg-app active:opacity-90' : 'bg-border opacity-70',
          ].join(' ')}
        >
          <Text className={['text-sm font-semibold leading-6', canAdd ? 'text-white' : 'text-muted'].join(' ')}>+</Text>
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
  /** Состояние раскрытых колонок аккордеона: ключ — `${laneId}:${columnId}`. */
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  isCardMoveAllowed: (card: Card, direction: MoveDirection) => boolean;
  onMoveCard: (card: Card, direction: MoveDirection) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  onAddCard: (columnId: ColumnId) => void;
}

/** Свимлайн телефона: заголовок и вертикальный аккордеон колонок. */
export function Swimlane({
  laneId,
  title,
  color,
  cards,
  expandedColumns,
  onToggleColumn,
  isCardMoveAllowed,
  onMoveCard,
  onOpenCard,
  onAddCard,
}: SwimlaneProps) {
  return (
    <View className="gap-2">
      <View className="flex-row items-center gap-2">
        <View className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: color ?? NEUTRAL_COLOR }} />

        <Text className="flex-1 text-sm font-semibold text-app" numberOfLines={1}>
          {title}
        </Text>

        <Text className="text-[11px] text-subtle">{cards.length}</Text>
      </View>

      <View className="gap-2">
        {COLUMNS.map((column) => {
          const key = `${laneId}:${column.id}`;

          return (
            <BoardColumn
              key={column.id}
              column={column}
              cards={cards.filter((card) => card.column === column.id)}
              expanded={expandedColumns[key] === true}
              onToggle={() => onToggleColumn(key)}
              canCreate
              addLabel="+ добавить"
              isCardMoveAllowed={isCardMoveAllowed}
              onMoveCard={onMoveCard}
              onOpenCard={onOpenCard}
              onAddCard={() => onAddCard(column.id)}
            />
          );
        })}
      </View>
    </View>
  );
}
