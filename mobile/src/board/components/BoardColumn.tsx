/**
 * Колонка телефона: свёрнутый в строку блок (аккордеон) — заголовок со счётчиком
 * раскрывает карточки. На широком экране колонки раскладывает `BoardLevel`:
 * ячейки сетки шире и место для кнопки создания там не нужно.
 */

import { Pressable, Text, View } from 'react-native';

import { AddCardButton } from './AddCardButton';
import { BoardCard } from './BoardCard';
import type { CardAction } from '../derive';
import { hasParent } from '../mappers';
import type { BoardColumn as BoardColumnModel, Card } from '../types';

interface BoardColumnProps {
  column: BoardColumnModel;
  cards: readonly Card[];
  expanded: boolean;
  onToggle: () => void;
  canCreate: boolean;
  addLabel: string;
  /** Действие карточки по её колонке; null — иконки нет. */
  cardAction: (card: Card) => CardAction | null;
  /** Выполнить действие карточки (перевод в следующую колонку). */
  onCardAction: (card: Card) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  onAddCard: () => void;
}

export function BoardColumn({
  column,
  cards,
  expanded,
  onToggle,
  canCreate,
  addLabel,
  cardAction,
  onCardAction,
  onOpenCard,
  onAddCard,
}: BoardColumnProps) {
  const titleRow = (
    <View className="flex-row items-center gap-2">
      <Text className="text-[11px] font-semibold uppercase tracking-wide text-muted">{column.label}</Text>

      <View className="rounded-full bg-lane px-1.5 py-0.5">
        <Text className="text-[10px] font-semibold text-muted">{cards.length}</Text>
      </View>
    </View>
  );

  const body = (
    <View className="gap-2">
      {cards.length === 0 ? (
        <View className="items-center rounded border border-dashed border-card-line px-2 py-3">
          <Text className="text-xs text-subtle">Пусто</Text>
        </View>
      ) : null}

      {cards.map((card) => (
        <BoardCard
          key={card.id}
          title={card.title}
          color={card.color}
          hasParent={hasParent(card)}
          kind={card.kind}
          action={cardAction(card)}
          onAction={() => onCardAction(card)}
          onPress={() => onOpenCard(card)}
        />
      ))}

      <AddCardButton label={addLabel} onPress={onAddCard} disabled={!canCreate} />
    </View>
  );

  return (
    <View className="overflow-hidden rounded-lg border border-card-line bg-lane">
      <Pressable
        accessibilityRole="button"
        accessibilityState={{ expanded }}
        onPress={onToggle}
        className="flex-row items-center justify-between px-3 py-2.5 active:bg-border"
      >
        {titleRow}
        <Text className="text-sm text-subtle">{expanded ? '▾' : '▸'}</Text>
      </Pressable>

      {expanded ? <View className="border-t border-card-line px-2 py-2">{body}</View> : null}
    </View>
  );
}
