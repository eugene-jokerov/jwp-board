/**
 * Уровень доски: сворачиваемая шапка и свимлайны.
 *
 * Свёрнутый уровень — одна строка с названием и общей численностью карточек.
 * Развёрнутый — свимлайны уровня и кнопка создания карточки уровня
 * (её открывает экран, отсюда передаём только обработчик).
 *
 * На широком экране уровень — таблица: заголовки колонок печатаются один раз
 * сверху, ниже идут колонки, выложенные строками по свимлайнам (у каждой группы
 * своя высота), поэтому карточки стоят напротив своего названия, а не уезжают
 * потоком вниз.
 *
 * Строка свимлайна — один flex-ряд (`items-stretch`): название и группы карточек
 * всех колонок лежат в нём вместе. Высоту ряда задаёт самая высокая группа, а
 * ячейка названия тянется по ней силами флексбокса — без измерения через
 * `onLayout`. Раньше название и колонки были двумя независимыми стеками, и высоту
 * названия синхронизировал `onLayout` группы карточек: при добавлении карточки в
 * пустой свимлайн его размер приходил с устаревшими данными, повторного layout
 * не было, и название оставалось минимальной высоты — до перезагрузки страницы.
 *
 * Фон и рамку колонок рисует подложка, растянутая за рядами на всю высоту уровня:
 * ряды прозрачны, поэтому колонка выглядит одной плитой, а не набором ячеек.
 *
 * Колонки делят свободную ширину поровну и растут вместе с окном (см. COLUMN_PANEL).
 * Любую колонку можно свернуть — она схлопывается в узкую полосу с вертикальным
 * названием и шевроном «развернуть».
 * На телефоне — аккордеон (сворачивание колонок там не нужно: они и так в строках).
 */

import { Pressable, Text, View } from 'react-native';

import { BoardCard } from './BoardCard';
import { ChevronLeftIcon, ChevronRightIcon, MenuIcon, SettingsIcon } from './BoardIcons';
import { Swimlane, SwimlaneHeader } from './Swimlane';
import { COLUMN_MIN_HEIGHT, type CollapsedColumns } from '../columns';
import { columnCount, type CardAction } from '../derive';
import { hasParent } from '../mappers';
import {
  COLUMNS,
  KIND_LEVEL,
  type BoardLevel as BoardLevelModel,
  type Card,
  type CardKind,
  type ClosedTotals,
  type ColumnId,
  type LevelId,
} from '../types';

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

/**
 * Тип карточки уровня по его id: тот же словарь, что ключи `closedTotals`.
 *
 * Обратное к `KIND_LEVEL` отображение, а не вторая таблица: уровни и типы
 * карточек совпадают один в один, поэтому у каждого уровня всегда есть тип.
 */
function levelKindOf(level: LevelId): CardKind {
  const entry = (Object.entries(KIND_LEVEL) as [CardKind, LevelId][]).find(([, id]) => id === level);

  // Недостижимо при полном KIND_LEVEL, но ветка нужна: find возвращает undefined.
  return entry?.[0] ?? 'initiative';
}

/** Минимальная высота строки свимлайна: не ниже строки названия до первой карточки. */
const LANE_ROW_MIN_HEIGHT = 56;

/** Ряд свимлайна: название и группы всех колонок в одной flex-строке. */
const LANE_ROW = 'w-full flex-row items-stretch gap-2';

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

/** Ячейка названия свимлайна: тянется по высоте ряда силами флексбокса. */
const LANE_TITLE_CELL = 'justify-start rounded-lg bg-surface px-2.5 py-2';

/**
 * Колонка в ряду свимлайна: только отступы и раскладка карточек.
 *
 * Фон и рамку рисует подложка колонки за рядами (`COLUMN_BACKDROP`), поэтому
 * здесь их нет: иначе на стыках строк появились бы двойные рамки и ячейки
 * выглядели бы отдельными плитками вместо одной колонки на всю высоту доски.
 */
const LANE_COLUMN_CELL = 'gap-2 p-2';

/**
 * Подложка колонки: одна плитка на всю высоту доски, нарисованная за рядами.
 *
 * Ряды свимлайнов (название + группы карточек) лежат поверх неё абсолютным слоем
 * и сами прозрачны — так колонка читается как монолит, но высоту названия
 * по-прежнему даёт флексбокс ряда, без измерения через `onLayout`.
 */
const COLUMN_BACKDROP = 'rounded-lg border border-card-line bg-card';

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
      className="pointer-events-auto shrink-0 self-stretch overflow-hidden rounded-lg border border-card-line bg-card"
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
  /**
   * Оттенок точки свимлайна (HEX), а не ключ палитры: компоненты рисуют его как есть.
   * У свимлайна проекта/задачи без родителя — служебный оттенок c0.
   */
  color: string;
  cards: Card[];
}

/**
 * Подсказка под кнопкой уровня, если создать карточку нельзя.
 *
 * Текст разбит на три части, чтобы ссылкой было только одно слово внутри фразы
 * (например, «направление»), а остальное осталось обычным текстом. Клик по
 * ссылке сразу открывает экран, где нужную сущность создают.
 */
export interface LevelCreateHintModel {
  /** Текст перед ссылкой. */
  before: string;
  /** Текст ссылки; null — ссылки нет, подсказка показывается обычным текстом. */
  link: string | null;
  /** Текст после ссылки. */
  after: string;
  /** Клик по ссылке: открыть экран настроек. */
  onPressLink: () => void;
}

interface BoardLevelProps {
  level: BoardLevelModel;
  lanes: readonly SwimlaneModel[];
  /**
   * Полное число карточек закрытых колонок по парам «уровень + колонка»
   * (`meta.closed_totals`). В «Готово» и «Отмене» сервер отдаёт лишь порцию
   * карточек, поэтому бейдж колонки берёт счётчик отсюда.
   */
  closedTotals: ClosedTotals;
  collapsed: boolean;
  onToggle: () => void;
  compact: boolean;
  /** Ширина окна: от неё зависит ширина панели названий свимлайнов. */
  width: number;
  /**
   * Свёрнутые колонки уровня. Состояние приходит извне (`useBoardSettings`):
   * оно сохраняется на сервере и не сбрасывается при перезагрузке страницы.
   */
  collapsedColumns: CollapsedColumns;
  /** Свернуть или развернуть колонку широкой раскладки этого уровня. */
  onToggleCollapsed: (columnId: ColumnId) => void;
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
  /** Можно ли создать карточку уровня (например, нет направлений). */
  canCreate: boolean;
  /** Подсказка под кнопкой уровня, если создать карточку нельзя; null — подсказки нет. */
  createHint: LevelCreateHintModel | null;
}

export function BoardLevel({
  level,
  lanes,
  closedTotals,
  collapsed,
  onToggle,
  compact,
  width,
  collapsedColumns,
  onToggleCollapsed,
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
  // Тип карточки уровня — ключ пары в `closedTotals`.
  const kindOfLevel = levelKindOf(level.id);
  // Широкая панель названий на больших окнах: шапка и тело берут один класс.
  const laneTitleWidth = widthOfLaneTitle(width);
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

      {/* Подсказка занимает свободное место, поэтому иконка настроек прижата
          к правому краю колонки свимлайнов — над их правой границей.
          Ссылкой оформляется только фрагмент фразы (`createHint.link`): остальной
          текст остаётся обычным, а клик по ссылке сразу открывает нужный экран
          настроек — искать пункт в меню уровня не нужно. */}
      {createHint ? (
        <Text className="flex-1 text-[11px] text-muted">
          {createHint.before}
          {createHint.link !== null ? (
            <Text
              accessibilityRole="link"
              accessibilityLabel={createHint.link}
              onPress={createHint.onPressLink}
              suppressHighlighting
              className="font-medium text-app underline"
            >
              {createHint.link}
            </Text>
          ) : null}
          {createHint.after}
        </Text>
      ) : (
        <View className="flex-1" />
      )}

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

  /**
   * У свимлайн-кнопки «+» есть родитель: у направления и у карточки-родителя.
   * Свимлайны «Без инициативы» / «Без проекта» / «Без направления» служебные —
   * в них родителя не задать, поэтому кнопки создания нет: карточку без родителя
   * создают кнопкой уровня, где родителя выбирают в форме.
   */
  const canAddInLane = (lane: SwimlaneModel) => canCreate && lane.parentId !== null;

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
                kind={kindOfLevel}
                closedTotals={closedTotals}
                expandedColumns={expandedColumns}
                onToggleColumn={onToggleColumn}
                canCreate={canAddInLane(lane)}
                cardAction={cardAction}
                onCardAction={onCardAction}
                onOpenCard={onOpenCard}
                onAddCard={(columnId) => onAddCard(lane.parentId, columnId)}
              />
            ))
          ) : (
            // Контейнер широкой раскладки держит подложку колонок: она лежит
            // абсолютом за рядами свимлайнов и тянется на всю их высоту.
            <View className="relative gap-2">
              {/* Шапка печатается один раз на уровень: дальше колонки идут сквозь
                  все свимлайны без повторения названий. Счётчик стоит рядом с
                  названием, у правого края — меню (пока заглушка) и сворачивание.
                  У свёрнутой колонки в шапке остаётся только шеврон «развернуть»:
                  он стоит над узкой полосой. */}
              <View className="z-10 w-full flex-row gap-2">
                <View className={laneTitleWidth} />

                {COLUMNS.map((column) => {
                  const loaded = lanes.reduce(
                    (sum, lane) => sum + lane.cards.filter((card) => card.column === column.id).length,
                    0,
                  );
                  const count = columnCount(loaded, column.id, kindOfLevel, closedTotals);
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
                          onPress={() => onToggleCollapsed(column.id)}
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
                          onPress={() => onToggleCollapsed(column.id)}
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

              {/* Тело: строки по свимлайнам поверх подложки колонок.
                  В каждом ряду название и группы карточек всех четырёх колонок
                  лежат вместе (`items-stretch`), поэтому высоту названия даёт самая
                  высокая группа — флексбокс подтягивает её сразу, как только в
                  свимлайне появилась карточка.
                  Подложка (`COLUMN_BACKDROP`) растянута на всю высоту тела и кладётся
                  под рядами: рамка и заливка колонки не режутся на стыках строк,
                  поэтому колонки выглядят монолитными, как плита в Jira. Высота
                  тела не ниже `COLUMN_MIN_HEIGHT`: колонки высокие даже на почти
                  пустой доске.
                  Подложка живёт внутри тела, а не на уровне целиком: иначе её
                  плитки накрыли бы шапку с названиями колонок и кнопками. */}
              <View className="relative w-full gap-2" style={{ minHeight: COLUMN_MIN_HEIGHT }}>
                {/* Слой подложки: развёрнутая колонка — одна плитка на всю высоту,
                    свёрнутая — одна полоса на всю высоту (см. CollapsedColumn).
                    `pointer-events-none` — слой декоративный и не должен
                    перехватывать клики по карточкам; свёрнутая полоса возвращает
                    себе клики через `pointer-events-auto`.
                    Свёрнутая колонка живёт здесь, а не в рядах свимлайнов: она
                    одна на всю доску, с названием по центру высоты, а в ряду
                    остаётся только распорка её ширины. */}
                <View className="pointer-events-none absolute inset-x-0 bottom-0 top-0 flex-row gap-2">
                  <View className={laneTitleWidth} />

                  {COLUMNS.map((column) =>
                    collapsedColumns[column.id] === true ? (
                      <CollapsedColumn
                        key={column.id}
                        label={column.label}
                        onExpand={() => onToggleCollapsed(column.id)}
                      />
                    ) : (
                      <View key={column.id} className={[COLUMN_BACKDROP, COLUMN_PANEL].join(' ')} />
                    ),
                  )}
                </View>

                <View className="w-full gap-2">
                  {lanes.map((lane) => (
                    <View key={lane.id} className={LANE_ROW} style={{ minHeight: LANE_ROW_MIN_HEIGHT }}>
                      <View className={[laneTitleWidth, LANE_TITLE_CELL].join(' ')}>
                        <SwimlaneHeader
                          title={lane.title}
                          color={lane.color}
                          count={lane.cards.length}
                          addLabel="Добавить карточку"
                          canAdd={canCreate}
                          canAddInLane={canAddInLane(lane)}
                          onAddCard={() => onAddCard(lane.parentId, firstColumnId)}
                        />
                      </View>

                      {COLUMNS.map((column) => {
                        // Свёрнутая колонка нарисована один раз в подложке на всю
                        // высоту доски, поэтому в ряду остаётся только распорка её
                        // ширины: без неё ячейки разъехались бы с подложкой.
                        if (collapsedColumns[column.id] === true) {
                          return (
                            <View key={column.id} className="shrink-0" style={{ width: COLLAPSED_COLUMN_WIDTH }} />
                          );
                        }

                        const laneCards = lane.cards.filter((card) => card.column === column.id);

                        // Фон и рамку даёт подложка ряда, поэтому ячейка прозрачна:
                        // пустая группа просто растягивается по высоте ряда.
                        return (
                          <View key={column.id} className={[LANE_COLUMN_CELL, COLUMN_PANEL].join(' ')}>
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
                  ))}
                </View>
              </View>
            </View>
          )}
        </View>
      )}
    </View>
  );
}
