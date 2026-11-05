/**
 * Уровень доски: сворачиваемая шапка и свимлайны.
 *
 * Свёрнутый уровень — одна строка с названием и общей численностью карточек.
 * Развёрнутый — свимлайны уровня и кнопка создания карточки уровня
 * (её открывает экран, отсюда передаём только обработчик).
 *
 * На широком экране уровень — таблица: заголовки колонок печатаются один раз
 * сверху, ниже идут четыре монолитные колонки на всю высоту. Карточки внутри
 * колонок выложены строками по свимлайнам (у каждой группы своя высота), поэтому
 * карточки стоят напротив своего названия, а не уезжают потоком вниз.
 * Колонки делят свободную ширину поровну и растут вместе с окном (см. COLUMN_PANEL).
 * Любую колонку можно свернуть — она схлопывается в узкую полосу с вертикальным
 * названием и шевроном «развернуть».
 * На телефоне — аккордеон (сворачивание колонок там не нужно: они и так в строках).
 */

import { useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { BoardCard } from './BoardCard';
import { ChevronLeftIcon, ChevronRightIcon, MenuIcon, SettingsIcon } from './BoardIcons';
import { Swimlane, SwimlaneHeader } from './Swimlane';
import { COLUMN_MIN_HEIGHT, type CollapsedColumns } from '../columns';
import type { CardAction } from '../derive';
import { hasParent } from '../mappers';
import { COLUMNS, type BoardLevel as BoardLevelModel, type Card, type ColumnId } from '../types';

/**
 * Раскладка широкого режима.
 *
 * Панель названий свимлайнов — базовые 160 px; на окнах шире `WIDE_MIN_WIDTH`
 * она получает ещё 50 px. Колонки делят оставшееся место поровну и растут до
 * `max-w-[380px]`: пока экран узкий, доска занимает его целиком, а на больших
 * мониторах колонки упираются в предел и справа остаётся свободное место.
 * Один класс используют и шапка колонок, и тело — иначе заголовки разъедутся
 * с самими колонками.
 */
const LANE_TITLE_WIDTH = 'shrink-0 w-40';
const LANE_TITLE_WIDTH_WIDE = 'shrink-0 w-[210px]';
const COLUMN_PANEL = 'min-w-0 flex-1 max-w-[380px]';

/** С этой ширины окна панель названий свимлайнов шире на 50 px. */
const WIDE_MIN_WIDTH = 1280;

function widthOfLaneTitle(width: number): string {
  return width > WIDE_MIN_WIDTH ? LANE_TITLE_WIDTH_WIDE : LANE_TITLE_WIDTH;
}

/** Минимальная высота ячейки названия свимлайна до первого измерения раскладки. */
const LANE_TITLE_MIN_HEIGHT = 56;

/** Заголовок колонки: название и счётчик слева, иконки меню и сворачивания справа. */
const COLUMN_TITLE_CLASS = 'flex-row items-center justify-between gap-1 px-0.5';

/** Кнопка-иконка в заголовке колонки. */
const COLUMN_ICON_BUTTON = 'h-5 w-5 items-center justify-center rounded active:bg-lane';

/**
 * Ширина свёрнутой колонки.
 *
 * Внутри — надпись, повёрнутая на 90°: после поворота её ширина становится
 * видимой высотой полосы, поэтому колонка должна быть выше строки текста.
 */
const COLLAPSED_COLUMN_WIDTH = 40;

/**
 * Ширина блока надписи в свёрнутой колонке.
 *
 * На web react-native-web ужимает `Text` по ширине родителя, поэтому блок
 * надписи получает собственную ширину (заметно больше узкой полосы). После
 * поворота на 90° она становится видимой высотой надписи, а сам блок
 * центрируется по полосе.
 */
const COLLAPSED_LABEL_WIDTH = 200;

/** Высота строки надписи: после поворота это её видимая толщина. */
const COLLAPSED_LABEL_HEIGHT = 16;

/**
 * Свёрнутая колонка: узкая полоса на всю высоту доски.
 *
 * Внутри только название, повёрнутое на 90° и читаемое сверху вниз
 * (кнопка «развернуть» стоит над полосой, в шапке уровня, счётчик не показываем).
 *
 * На web react-native-web ужимает `Text` по ширине родителя, поэтому надпись
 * лежит в блоке собственной ширины (COLLAPSED_LABEL_WIDTH): иначе строка
 * рвалась бы на столбик букв по ширине узкой полосы.
 */
function CollapsedColumn({ label, onExpand }: { label: string; onExpand: () => void }) {
  return (
    <View
      className="shrink-0 overflow-hidden rounded-lg border border-card-line bg-card"
      style={{ width: COLLAPSED_COLUMN_WIDTH, minHeight: COLUMN_MIN_HEIGHT }}
    >
      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Развернуть колонку «${label}»`}
        onPress={onExpand}
        hitSlop={4}
        className="flex-1 p-2 active:bg-lane"
      >
        {/* Надпись центрируется по полосе и поворачивается на 90°: ширина блока
            после поворота становится её высотой, поэтому центрирование идёт
            по краям этого блока. */}
        <View
          className="absolute"
          style={{
            width: COLLAPSED_LABEL_WIDTH,
            left: '50%',
            top: '50%',
            transform: [
              { translateX: -COLLAPSED_LABEL_WIDTH / 2 },
              { translateY: -COLLAPSED_LABEL_HEIGHT / 2 },
              { rotate: '90deg' },
            ],
          }}
        >
          <Text
            className="text-[11px] font-semibold uppercase tracking-wide text-muted"
            numberOfLines={1}
            style={{ writingDirection: 'ltr' }}
          >
            {label}
          </Text>
        </View>
      </Pressable>
    </View>
  );
}

export interface SwimlaneModel {
  /**
   * Ключ свимлайна для UI: `direction-<id>`, `card-<id>` или `no_parent`.
   * Ключ строковый, потому что у свимлайна «без родителя» числового id нет.
   */
  id: string;
  /** id родительской карточки/направления; у свимлайна «без родителя» — null. */
  parentId: number | null;
  title: string;
  /** Оттенок точки свимлайна (HEX), а не ключ палитры: компоненты рисуют его как есть. */
  color: string | null;
  cards: Card[];
}

interface BoardLevelProps {
  level: BoardLevelModel;
  lanes: readonly SwimlaneModel[];
  collapsed: boolean;
  onToggle: () => void;
  compact: boolean;
  /** Ширина окна: от неё зависит ширина панели названий свимлайнов. */
  width: number;
  expandedColumns: Record<string, boolean>;
  onToggleColumn: (key: string) => void;
  /** Действие карточки по её колонке; null — иконки нет. */
  cardAction: (card: Card) => CardAction | null;
  /** Выполнить действие карточки (перевод в следующую колонку). */
  onCardAction: (card: Card) => void;
  /** Клик по карточке — открыть её на редактирование. */
  onOpenCard: (card: Card) => void;
  /** Родитель из свимлайна: id карточки/направления или null («без родителя»). */
  onAddCard: (parentId: number | null, columnId: ColumnId) => void;
  onCreateInLevel: () => void;
  /** Открыть меню «Настройки уровня» (иконка в строке с кнопкой создания). */
  onOpenSettings: () => void;
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
  width,
  expandedColumns,
  onToggleColumn,
  cardAction,
  onCardAction,
  onOpenCard,
  onAddCard,
  onCreateInLevel,
  onOpenSettings,
  createLabel,
  canCreate,
  createHint,
}: BoardLevelProps) {
  const total = lanes.reduce((sum, lane) => sum + lane.cards.length, 0);
  // Широкая панель названий на больших окнах: шапка и тело берут один класс.
  const laneTitleWidth = widthOfLaneTitle(width);
  // Кнопка «+» в заголовке свимлайна создаёт карточку в первой колонке доски.
  const firstColumnId = COLUMNS[0].id;

  /**
   * Измеренные высоты блоков карточек по свимлайнам.
   *
   * Карточки переменной высоты (название в 1–3 строки), поэтому высоту ячейки
   * названия нельзя посчитать заранее — её даёт раскладка группы карточек
   * в самой широкой колонке свимлайна.
   */
  const [laneHeights, setLaneHeights] = useState<Record<string, number>>({});

  /**
   * Свёрнутые колонки уровня.
   *
   * Состояние живёт внутри уровня: свернув «Беклог» на стратегическом,
   * на тактическом и операционном он остаётся развёрнутым.
   */
  const [collapsedColumns, setCollapsedColumns] = useState<CollapsedColumns>({});

  const toggleColumnCollapsed = (columnId: ColumnId) => {
    setCollapsedColumns((prev) => ({ ...prev, [columnId]: !prev[columnId] }));
  };

  /** Запоминаем высоту группы карточек: берём максимальную по всем колонкам. */
  const reportLaneHeight = (laneId: string, height: number) => {
    setLaneHeights((prev) => {
      // Ячейка названия должна покрыть самую высокую группу карточек свимлайна,
      // поэтому меньшее измерение игнорируем: повторный layout не должен её сжимать.
      if (prev[laneId] !== undefined && prev[laneId] >= height) {
        return prev;
      }

      return { ...prev, [laneId]: height };
    });
  };

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

      {/* Подсказка занимает свободное место, поэтому иконка настроек прижата
          к правому краю колонки свимлайнов — над их правой границей. */}
      {createHint ? <Text className="flex-1 text-[11px] text-muted">{createHint}</Text> : <View className="flex-1" />}

      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`Настройки уровня «${level.label}»`}
        onPress={onOpenSettings}
        hitSlop={6}
        className="h-7 w-7 items-center justify-center rounded-lg active:bg-lane"
      >
        <SettingsIcon />
      </Pressable>
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
                cardAction={cardAction}
                onCardAction={onCardAction}
                onOpenCard={onOpenCard}
                onAddCard={(columnId) => onAddCard(lane.parentId, columnId)}
              />
            ))
          ) : (
            <>
              {/* Шапка печатается один раз на уровень: дальше колонки идут сквозь
                  все свимлайны без повторения названий. Счётчик стоит рядом с
                  названием, у правого края — меню (пока заглушка) и сворачивание.
                  У свёрнутой колонки в шапке остаётся только шеврон «развернуть»:
                  он стоит над узкой полосой. */}
              <View className="w-full flex-row gap-2">
                <View className={laneTitleWidth} />

                {COLUMNS.map((column) => {
                  const count = lanes.reduce(
                    (sum, lane) => sum + lane.cards.filter((card) => card.column === column.id).length,
                    0,
                  );
                  const isCollapsed = collapsedColumns[column.id] === true;

                  if (isCollapsed) {
                    return (
                      <View
                        key={column.id}
                        className="shrink-0 items-center px-1"
                        style={{ width: COLLAPSED_COLUMN_WIDTH }}
                      >
                        <Pressable
                          accessibilityRole="button"
                          accessibilityLabel={`Развернуть колонку «${column.label}»`}
                          onPress={() => toggleColumnCollapsed(column.id)}
                          hitSlop={4}
                          className={COLUMN_ICON_BUTTON}
                        >
                          <ChevronRightIcon />
                        </Pressable>
                      </View>
                    );
                  }

                  return (
                    <View key={column.id} className={['px-2', COLUMN_PANEL].join(' ')}>
                      <View className={COLUMN_TITLE_CLASS}>
                        <View className="flex-1 flex-row items-center gap-1.5">
                          <Text className="text-[11px] font-semibold uppercase tracking-wide text-muted">
                            {column.label}
                          </Text>

                          <View className="rounded-full bg-card px-1.5 py-0.5">
                            <Text className="text-[10px] font-semibold text-muted">{count}</Text>
                          </View>
                        </View>

                        <Pressable
                          accessibilityRole="button"
                          accessibilityLabel={`Свернуть колонку «${column.label}»`}
                          onPress={() => toggleColumnCollapsed(column.id)}
                          hitSlop={4}
                          className={COLUMN_ICON_BUTTON}
                        >
                          <ChevronLeftIcon />
                        </Pressable>

                        {/* Меню колонки — пока без действий, поэтому только иконка.
                            Стоит последней, чтобы быть у самого правого края. */}
                        <View
                          accessibilityLabel={`Меню колонки «${column.label}»`}
                          accessibilityState={{ disabled: true }}
                          className={[COLUMN_ICON_BUTTON, 'opacity-50'].join(' ')}
                        >
                          <MenuIcon />
                        </View>
                      </View>
                    </View>
                  );
                })}
              </View>

              {/* Тело: панель названий и четыре колонки-панели на всю высоту.
                  Каждый свимлайн в колонке — группа с собственной высотой, поэтому
                  карточки стоят напротив своего названия, а не потоком вниз. */}
              <View className="w-full flex-row items-start gap-2">
                <View className={['gap-2', laneTitleWidth].join(' ')}>
                  {lanes.map((lane) => (
                    <View
                      key={lane.id}
                      style={{ minHeight: laneHeights[lane.id] ?? LANE_TITLE_MIN_HEIGHT }}
                      className="justify-start rounded-lg bg-surface px-2.5 py-2"
                    >
                      <SwimlaneHeader
                        title={lane.title}
                        color={lane.color}
                        count={lane.cards.length}
                        addLabel="Добавить карточку"
                        canAdd
                        onAddCard={() => onAddCard(lane.parentId, firstColumnId)}
                      />
                    </View>
                  ))}
                </View>

                {COLUMNS.map((column) => {
                  // Свёрнутая колонка — узкая полоса на всю высоту доски
                  // с вертикальным названием (см. CollapsedColumn).
                  if (collapsedColumns[column.id] === true) {
                    return (
                      <CollapsedColumn
                        key={column.id}
                        label={column.label}
                        onExpand={() => toggleColumnCollapsed(column.id)}
                      />
                    );
                  }

                  return (
                    <View
                      key={column.id}
                      className={['gap-2 rounded-lg border border-card-line bg-card p-2', COLUMN_PANEL].join(' ')}
                      style={{ minHeight: COLUMN_MIN_HEIGHT }}
                    >
                      {lanes.map((lane) => {
                        const laneCards = lane.cards.filter((card) => card.column === column.id);

                        // Пустая группа сохраняет высоту строки: иначе карточки следующих
                        // свимлайнов поднимутся и уедут из-под своего названия.
                        if (laneCards.length === 0) {
                          return (
                            <View key={lane.id} style={{ minHeight: laneHeights[lane.id] ?? LANE_TITLE_MIN_HEIGHT }} />
                          );
                        }

                        return (
                          <View
                            key={lane.id}
                            className="gap-2"
                            style={laneHeights[lane.id] === undefined ? undefined : { minHeight: laneHeights[lane.id] }}
                            onLayout={(event) => reportLaneHeight(lane.id, event.nativeEvent.layout.height)}
                          >
                            {laneCards.map((card) => (
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
                          </View>
                        );
                      })}
                    </View>
                  );
                })}
              </View>
            </>
          )}
        </View>
      )}
    </View>
  );
}
