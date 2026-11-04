/**
 * Уровень доски: сворачиваемая шапка и свимлайны.
 *
 * Свёрнутый уровень — одна строка с названием и общей численностью карточек.
 * Развёрнутый — свимлайны уровня и кнопка создания карточки уровня
 * (её открывает экран, отсюда передаём только обработчик).
 */

import { Pressable, Text, View } from 'react-native';

import { Swimlane } from './Swimlane';
import { LEVEL_COLORS } from '../colors';
import type { BoardLevel as BoardLevelModel, Card, ColumnId, MoveDirection } from '../types';

export interface SwimlaneModel {
  /** id родительской карточки; у свимлайна «без родителя» — служебный ключ. */
  id: string;
  /** Родительская карточка, у свимлайна «без родителя» — null. */
  parentId: string | null;
  title: string;
  color: string | null;
  cards: Card[];
}

interface BoardLevelProps {
  level: BoardLevelModel;
  lanes: readonly SwimlaneModel[];
  collapsed: boolean;
  onToggle: () => void;
  compact: boolean;
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  isCardMoveAllowed: (card: Card, direction: MoveDirection) => boolean;
  onMoveCard: (card: Card, direction: MoveDirection) => void;
  onAddCard: (parentId: string | null, columnId: ColumnId) => void;
  onCreateInLevel: () => void;
  /** Подпись кнопки уровня, например «+ Инициатива». */
  createLabel: string;
  /** Можно ли создать карточку уровня (например, нет инициатив в работе). */
  canCreate: boolean;
  createHint: string | null;
}

export function BoardLevel({
  level,
  lanes,
  collapsed,
  onToggle,
  compact,
  expandedColumns,
  onToggleColumn,
  isCardMoveAllowed,
  onMoveCard,
  onAddCard,
  onCreateInLevel,
  createLabel,
  canCreate,
  createHint,
}: BoardLevelProps) {
  const total = lanes.reduce((sum, lane) => sum + lane.cards.length, 0);
  const color = LEVEL_COLORS[level.id];

  return (
    <View className="overflow-hidden rounded-2xl border border-border bg-surface">
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ expanded: !collapsed }}
        onPress={onToggle}
        className="flex-row items-center gap-2.5 px-4 py-3 active:bg-surface"
      >
        <View className="h-7 w-1.5 rounded-full" style={{ backgroundColor: color }} />

        <Text className="flex-1 text-base font-bold text-app">{level.label}</Text>

        <Text className="text-xs text-muted">{total}</Text>

        <Text className="text-sm text-muted">{collapsed ? '▸' : '▾'}</Text>
      </Pressable>

      {collapsed ? null : (
        <View className="gap-4 border-t border-border px-3 py-3">
          <View className="flex-row items-center gap-2">
            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: !canCreate }}
              disabled={!canCreate}
              onPress={onCreateInLevel}
              className={['rounded-xl px-3 py-2', canCreate ? 'bg-app active:opacity-90' : 'bg-border opacity-70'].join(
                ' ',
              )}
            >
              <Text className={['text-xs font-semibold', canCreate ? 'text-white' : 'text-muted'].join(' ')}>
                {createLabel}
              </Text>
            </Pressable>

            {createHint ? <Text className="flex-1 text-[11px] text-muted">{createHint}</Text> : null}
          </View>

          {lanes.length === 0 ? (
            <View className="items-center rounded-xl bg-card px-3 py-6">
              <Text className="text-sm text-muted">На этом уровне пока нечего показать</Text>
              <Text className="mt-1 text-center text-xs text-subtle">
                Свимлайны появляются из карточек предыдущего уровня в колонке «В работе»
              </Text>
            </View>
          ) : null}

          {lanes.map((lane) => (
            <Swimlane
              key={lane.id}
              laneId={lane.id}
              title={lane.title}
              color={lane.color}
              cards={lane.cards}
              compact={compact}
              expandedColumns={expandedColumns}
              onToggleColumn={onToggleColumn}
              isCardMoveAllowed={isCardMoveAllowed}
              onMoveCard={onMoveCard}
              onAddCard={(columnId) => onAddCard(lane.parentId, columnId)}
            />
          ))}
        </View>
      )}
    </View>
  );
}
