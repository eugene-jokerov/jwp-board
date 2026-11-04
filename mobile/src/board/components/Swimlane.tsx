/**
 * Свимлайн уровня: заголовок (цветная точка, название, счётчик) и колонки.
 *
 * На широком экране колонки идут в ряд и прокручиваются горизонтально, если
 * не помещаются; на телефоне — вертикальный аккордеон колонок.
 */

import { ScrollView, Text, View } from 'react-native';

import { BoardColumn } from './BoardColumn';
import { NEUTRAL_COLOR } from '../colors';
import { COLUMNS, type Card, type ColumnId, type MoveDirection } from '../types';

interface SwimlaneProps {
  laneId: string;
  title: string;
  color: string | null;
  cards: readonly Card[];
  compact: boolean;
  /** Состояние раскрытых колонок аккордеона: ключ — `${laneId}:${columnId}`. */
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  isCardMoveAllowed: (card: Card, direction: MoveDirection) => boolean;
  onMoveCard: (card: Card, direction: MoveDirection) => void;
  onAddCard: (columnId: ColumnId) => void;
}

export function Swimlane({
  laneId,
  title,
  color,
  cards,
  compact,
  expandedColumns,
  onToggleColumn,
  isCardMoveAllowed,
  onMoveCard,
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

      {compact ? (
        <View className="gap-2">
          {COLUMNS.map((column) => {
            const key = `${laneId}:${column.id}`;

            return (
              <BoardColumn
                key={column.id}
                column={column}
                cards={cards.filter((card) => card.column === column.id)}
                variant="accordion"
                expanded={expandedColumns[key] === true}
                onToggle={() => onToggleColumn(key)}
                canCreate
                addLabel="+ добавить"
                isCardMoveAllowed={isCardMoveAllowed}
                onMoveCard={onMoveCard}
                onAddCard={() => onAddCard(column.id)}
              />
            );
          })}
        </View>
      ) : (
        <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="flex-row gap-2">
          {COLUMNS.map((column) => (
            <BoardColumn
              key={column.id}
              column={column}
              cards={cards.filter((card) => card.column === column.id)}
              variant="row"
              expanded
              onToggle={() => undefined}
              canCreate
              addLabel="+ добавить"
              isCardMoveAllowed={isCardMoveAllowed}
              onMoveCard={onMoveCard}
              onAddCard={() => onAddCard(column.id)}
            />
          ))}
        </ScrollView>
      )}
    </View>
  );
}
