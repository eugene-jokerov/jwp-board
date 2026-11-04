/**
 * Уровень доски: сворачиваемая шапка и свимлайны.
 *
 * Свёрнутый уровень — одна строка с названием и общей численностью карточек.
 * Развёрнутый — свимлайны уровня и кнопка создания карточки уровня
 * (её открывает экран, отсюда передаём только обработчик).
 *
 * На широком экране каждый свимлайн — отдельная строка: заголовок слева и четыре
 * колонки (беклог, в работе, готово, отмена) справа. Заголовок живёт в той же
 * строке, что и карточки, поэтому всегда стоит напротив своих карточек.
 * На телефоне — аккордеон.
 */

import { Pressable, Text, View } from 'react-native';

import { BoardCard } from './BoardCard';
import { Swimlane, SwimlaneHeader } from './Swimlane';
import { LEVEL_COLORS } from '../colors';
import { COLUMNS, type BoardLevel as BoardLevelModel, type Card, type ColumnId, type MoveDirection } from '../types';

/** Ячейка колонки в строке свимлайна: фиксированная ширина, чтобы четыре колонки влезали. */
const COLUMN_CELL_CLASS = 'w-36 shrink-0 gap-2 rounded-xl bg-surface p-2';

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
  // Кнопка «+» в заголовке свимлайна создаёт карточку в первой колонке доски.
  const firstColumnId = COLUMNS[0].id;

  const laneForm = (
    <View className="flex-row items-center gap-2">
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ disabled: !canCreate }}
        disabled={!canCreate}
        onPress={onCreateInLevel}
        className={['rounded-xl px-3 py-2', canCreate ? 'bg-app active:opacity-90' : 'bg-border opacity-70'].join(' ')}
      >
        <Text className={['text-xs font-semibold', canCreate ? 'text-white' : 'text-muted'].join(' ')}>
          {createLabel}
        </Text>
      </Pressable>

      {createHint ? <Text className="flex-1 text-[11px] text-muted">{createHint}</Text> : null}
    </View>
  );

  const emptyNotice =
    lanes.length === 0 ? (
      <View className="items-center rounded-xl bg-card px-3 py-6">
        <Text className="text-sm text-muted">На этом уровне пока нечего показать</Text>
        <Text className="mt-1 text-center text-xs text-subtle">
          Свимлайны появляются из карточек предыдущего уровня в колонке «В работе»
        </Text>
      </View>
    ) : null;

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
          {laneForm}
          {emptyNotice}

          {compact ? (
            lanes.map((lane) => (
              <Swimlane
                key={lane.id}
                laneId={lane.id}
                title={lane.title}
                color={lane.color}
                cards={lane.cards}
                expandedColumns={expandedColumns}
                onToggleColumn={onToggleColumn}
                isCardMoveAllowed={isCardMoveAllowed}
                onMoveCard={onMoveCard}
                onAddCard={(columnId) => onAddCard(lane.parentId, columnId)}
              />
            ))
          ) : (
            <View className="flex-col gap-2">
              {lanes.map((lane) => (
                <View key={lane.id} className="flex-row items-start gap-2">
                  {/* Заголовок свимлайна стоит напротив своих карточек: он живёт в той же
                      строке, поэтому длинная колонка не «уводит» подпись вниз. */}
                  <View className="w-36 shrink-0 self-stretch rounded-xl bg-card px-3 py-2">
                    <SwimlaneHeader
                      title={lane.title}
                      color={lane.color}
                      count={lane.cards.length}
                      addLabel="Добавить карточку"
                      canAdd
                      onAddCard={() => onAddCard(lane.parentId, firstColumnId)}
                    />
                  </View>

                  {COLUMNS.map((column) => {
                    const columnCards = lane.cards.filter((card) => card.column === column.id);

                    return (
                      <View key={column.id} className={COLUMN_CELL_CLASS}>
                        <Text className="text-xs font-semibold uppercase tracking-wide text-muted">{column.label}</Text>

                        <View className="gap-2">
                          {columnCards.length === 0 ? (
                            <View className="items-center rounded-xl bg-card px-2 py-3">
                              <Text className="text-xs text-subtle">Пусто</Text>
                            </View>
                          ) : null}

                          {columnCards.map((card) => (
                            <BoardCard
                              key={card.id}
                              title={card.title}
                              color={card.color}
                              canMovePrev={isCardMoveAllowed(card, 'prev')}
                              canMoveNext={isCardMoveAllowed(card, 'next')}
                              onMove={(direction) => onMoveCard(card, direction)}
                              onBlocked={(direction) => onMoveCard(card, direction)}
                            />
                          ))}
                        </View>
                      </View>
                    );
                  })}
                </View>
              ))}
            </View>
          )}
        </View>
      )}
    </View>
  );
}
