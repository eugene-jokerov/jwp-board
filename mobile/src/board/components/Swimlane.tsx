/**
 * Свимлайн уровня: заголовок (цветная точка, название) и колонки.
 *
 * На телефоне — вертикальный аккордеон колонок, заголовок сверху.
 * На широком экране заголовок выносится в левую колонку уровня
 * (`SwimlaneHeader`), а колонки уровня раскладывает `BoardLevel` общей сеткой.
 */

import { Pressable, Text, View } from 'react-native';

import { BoardColumn } from './BoardColumn';
import {
  closedHasMore,
  closedPageOf,
  columnCount,
  isClosedColumn,
  type CardAction,
  type CardBreadcrumb,
  type ClosedPages,
} from '../derive';
import { COLUMNS, type Card, type CardKind, type ClosedTotals, type ColumnId } from '../types';

interface SwimlaneHeaderProps {
  title: string;
  color: string | null;
  /** Подпись кнопки создания подсказывает тип карточки, например «+ Задача». */
  addLabel: string;
  canAdd: boolean;
  /**
   * Можно ли создать карточку из этого свимлайна. У обычного свимлайна родитель
   * известен, у служебного «без родителя» — нет: там форма открывается с выбором
   * родителя, поэтому кнопка тоже активна.
   */
  canAddInLane: boolean;
  onAddCard: () => void;
}

/**
 * Заголовок свимлайна для широкой раскладки: слева от сетки колонок.
 *
 * В одну строку название обрезалось (панель шириной 160 px), поэтому блок
 * разложен вертикально: название с цветной точкой сверху, «+» — под ним.
 * «+» создаёт карточку в колонке, которую передал `BoardLevel`. У служебного
 * свимлайна «без родителя» родитель не предопределён: форма открывается с его
 * выбором и с опцией «без родителя».
 */
export function SwimlaneHeader({ title, color, addLabel, canAdd, canAddInLane, onAddCard }: SwimlaneHeaderProps) {
  const addDisabled = !canAdd || !canAddInLane;

  return (
    <View className="gap-1.5">
      <View className="flex-row items-start gap-1.5">
        <View className="mt-1.5 h-2 w-2 shrink-0 rounded-full" style={{ backgroundColor: color }} />

        <Text className="flex-1 text-[13px] font-semibold leading-4 text-ink" numberOfLines={3}>
          {title}
        </Text>
      </View>

      <View className="flex-row items-center justify-end gap-2">
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
  /**
   * Состояние догрузки закрытых колонок по парам «колонка + уровень»: по нему
   * кнопка «Показать ещё» показывает индикатор и текст ошибки.
   */
  closedPages: ClosedPages;
  /**
   * Полный набор карточек уровня: по нему считается, остались ли у закрытой
   * колонки незагруженные карточки. Свимлайн видит только свои карточки, а
   * лента «показать ещё» — одна на уровень, поэтому решение «есть ещё» берётся
   * из полного набора.
   */
  levelCards: readonly Card[];
  /**
   * Показывать ли «Показать ещё» закрытых колонок в этом свимлайне. Флаг
   * включает только последний свимлайн уровня: лента у пары «уровень +
   * колонка» одна, и кнопка не должна повторяться под каждым свимлайном.
   */
  showClosedButtons: boolean;
  /** Догрузить следующую страницу закрытой колонки уровня. */
  onLoadMoreClosed: (column: ColumnId) => void;
  /** Состояние раскрытых колонок аккордеона: ключ — `${laneId}:${columnId}`. */
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  /** Действие карточки по её колонке; null — иконки нет. */
  cardAction: (card: Card) => CardAction | null;
  /** Путь карточки по иерархии для её крошек (считает `cardBreadcrumbs`). */
  cardBreadcrumbs: (card: Card) => CardBreadcrumb[];
  /** Выполнить действие карточки (перевод в следующую колонку). */
  onCardAction: (card: Card) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  /**
   * Можно ли создавать карточки в колонках свимлайна: то же правило, что у
   * кнопки «+» в заголовке — служебный свимлайн создаёт карточку без родителя.
   * Кнопка внизу колонки есть только у «Беклога» (см. `BoardColumn`).
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
  closedPages,
  levelCards,
  showClosedButtons,
  onLoadMoreClosed,
  expandedColumns,
  onToggleColumn,
  canCreate,
  cardAction,
  cardBreadcrumbs,
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
      </View>

      <View className="gap-2">
        {COLUMNS.map((column) => {
          const key = `${laneId}:${column.id}`;
          const laneCards = cards.filter((card) => card.column === column.id);
          const page = closedPageOf(closedPages, column.id, kind);
          // «Есть ещё» считается по карточкам уровня целиком: лента закрытой
          // колонки общая для всех свимлайнов, а этот блок лишь её последний пункт.
          const hasMore = showClosedButtons && closedHasMore(levelCards, column.id, kind, closedTotals, closedPages);

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
              showMore={isClosedColumn(column.id) && (hasMore || page.error !== null) ? page : null}
              onShowMore={() => onLoadMoreClosed(column.id)}
              cardAction={cardAction}
              cardBreadcrumbs={cardBreadcrumbs}
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
