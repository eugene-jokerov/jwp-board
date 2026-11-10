/**
 * Колонка телефона: свёрнутый в строку блок (аккордеон) — заголовок со счётчиком
 * раскрывает карточки. На широком экране колонки раскладывает `BoardLevel`:
 * ячейки сетки шире и место для кнопки создания там не нужно.
 *
 * Кнопка создания карточки есть только в «Беклоге»: новая карточка всегда
 * попадает в первую колонку доски, поэтому под остальными колонками она
 * вводила бы в заблуждение. Создание идёт через «+» в заголовке свимлайна и
 * кнопку создания на уровне.
 *
 * Бейдж показывает полное число карточек колонки: в «Готово» и «Отмене» загружена
 * лишь порция списка, поэтому число приходит снаружи (см. `columnCount`), а не
 * считается по массиву карточек. Остальные карточки закрытой колонки догружаются
 * кнопкой «Показать ещё» — она одна на пару «уровень + колонка», поэтому её
 * показывает только последний свимлайн (см. `showMore`).
 */

import { Pressable, Text, View } from 'react-native';

import { AddCardButton } from './AddCardButton';
import { BoardCard } from './BoardCard';
import { ShowMoreButton } from './ShowMoreButton';
import type { CardAction, CardBreadcrumb } from '../derive';
import { isClosedColumn, sortBacklogCards } from '../derive';
import { hasParent } from '../mappers';
import type { BoardColumn as BoardColumnModel, Card } from '../types';

interface BoardColumnProps {
  column: BoardColumnModel;
  cards: readonly Card[];
  /**
   * Число карточек колонки для бейджа. В «Готово» и «Отмене» это полное число
   * из `meta.closed_totals`, а не длина загруженной порции карточек.
   */
  count: number;
  expanded: boolean;
  onToggle: () => void;
  canCreate: boolean;
  /** Подпись кнопки создания; кнопка есть только в «Беклоге» (см. шапку файла). */
  addLabel: string;
  /**
   * «Показать ещё» под карточками закрытой колонки; null — кнопки нет (у
   * открытых колонок всё загружено, у закрытых — последний свимлайн ещё не
   * дошёл до ленты).
   */
  showMore: { loading: boolean; error: string | null } | null;
  /** Догрузить следующую страницу закрытой колонки. */
  onShowMore: () => void;
  /** Действие карточки по её колонке; null — иконки нет. */
  cardAction: (card: Card) => CardAction | null;
  /** Путь карточки по иерархии для её крошек (считает `cardBreadcrumbs`). */
  cardBreadcrumbs: (card: Card) => CardBreadcrumb[];
  /** Выполнить действие карточки (перевод в следующую колонку). */
  onCardAction: (card: Card) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  onAddCard: () => void;
}

export function BoardColumn({
  column,
  cards,
  count,
  expanded,
  onToggle,
  canCreate,
  addLabel,
  showMore,
  onShowMore,
  cardAction,
  cardBreadcrumbs,
  onCardAction,
  onOpenCard,
  onAddCard,
}: BoardColumnProps) {
  const titleRow = (
    <View className="flex-row items-center gap-2">
      <Text className="text-[11px] font-semibold uppercase tracking-wide text-muted">{column.label}</Text>

      <View className="rounded-full bg-lane px-1.5 py-0.5">
        <Text className="text-[10px] font-semibold text-muted">{count}</Text>
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

      {/* В беклоге карточки «на реализацию» стоят выше идей (см. `sortBacklogCards`),
          остальные колонки выводятся в порядке состояния доски. */}
      {sortBacklogCards(cards, column.id).map((card) => (
        <BoardCard
          key={card.id}
          title={card.title}
          color={card.color}
          breadcrumbs={cardBreadcrumbs(card)}
          slug={card.slug}
          hasParent={hasParent(card)}
          kind={card.kind}
          deadline={card.deadline}
          priority={card.priority}
          closedColumn={isClosedColumn(column.id)}
          idea={card.idea}
          backlogColumn={column.id === 'backlog'}
          action={cardAction(card)}
          onAction={() => onCardAction(card)}
          onPress={() => onOpenCard(card)}
        />
      ))}

      {/* «Показать ещё» закрытой колонки стоит после загруженной порции: догрузка
          продолжает список вниз, как лента. */}
      {showMore === null ? null : (
        <ShowMoreButton onPress={onShowMore} loading={showMore.loading} error={showMore.error} />
      )}

      {/* Создать карточку можно только из «Беклога»: остальные колонки живут
          переносом карточки действием на самой карточке, а создание идёт
          «+» в заголовке свимлайна и кнопкой создания на уровне. */}
      {column.id === 'backlog' ? <AddCardButton label={addLabel} onPress={onAddCard} disabled={!canCreate} /> : null}
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
