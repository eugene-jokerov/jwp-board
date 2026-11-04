/**
 * Колонка доски: заголовок со счётчиком, карточки и кнопка «+».
 *
 * Два вида:
 * - `row` — колонка в сетке уровня (планшет, web);
 * - `accordion` — свёрнутый в строку блок (телефон): заголовок раскрывает карточки.
 */

import { Pressable, Text, View } from 'react-native';

import { AddCardButton } from './AddCardButton';
import { BoardCard } from './BoardCard';
import type { BoardColumn as BoardColumnModel, Card, MoveDirection } from '../types';

interface BoardColumnProps {
  column: BoardColumnModel;
  cards: readonly Card[];
  variant: 'row' | 'accordion';
  expanded: boolean;
  onToggle: () => void;
  canCreate: boolean;
  addLabel: string;
  isCardMoveAllowed: (card: Card, direction: MoveDirection) => boolean;
  onMoveCard: (card: Card, direction: MoveDirection) => void;
  onAddCard: () => void;
}

export function BoardColumn({
  column,
  cards,
  variant,
  expanded,
  onToggle,
  canCreate,
  addLabel,
  isCardMoveAllowed,
  onMoveCard,
  onAddCard,
}: BoardColumnProps) {
  const titleRow = (
    <View className="flex-row items-center gap-2">
      <Text className="text-xs font-semibold uppercase tracking-wide text-muted">{column.label}</Text>

      <View className="rounded-full bg-surface px-2 py-0.5">
        <Text className="text-[11px] font-semibold text-muted">{cards.length}</Text>
      </View>
    </View>
  );

  const body = (
    <View className="gap-2">
      {cards.length === 0 ? (
        <View className="items-center rounded-xl bg-surface px-2 py-3">
          <Text className="text-xs text-subtle">Пусто</Text>
        </View>
      ) : null}

      {cards.map((card) => (
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

      <AddCardButton label={addLabel} onPress={onAddCard} disabled={!canCreate} />
    </View>
  );

  if (variant === 'accordion') {
    return (
      <View className="overflow-hidden rounded-xl bg-card">
        <Pressable
          accessibilityRole="button"
          accessibilityState={{ expanded }}
          onPress={onToggle}
          className="flex-row items-center justify-between px-3 py-2.5 active:bg-surface"
        >
          {titleRow}
          <Text className="text-sm text-subtle">{expanded ? '▾' : '▸'}</Text>
        </Pressable>

        {expanded ? <View className="border-t border-border px-2 py-2">{body}</View> : null}
      </View>
    );
  }

  return (
    <View className="min-w-56 flex-1 gap-2 rounded-xl bg-card p-2">
      {titleRow}
      {body}
    </View>
  );
}
