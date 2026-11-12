/**
 * Уровень доски: сворачиваемая шапка и свимлайны.
 *
 * Свёрнутый уровень — одна строка с названием и общей численностью карточек.
 * Развёрнутый — свимлайны уровня и кнопка создания карточки уровня
 * (её открывает экран, отсюда передаём только обработчик).
 *
 * На широком экране уровень — таблица: заголовки колонок печатаются один раз
 * сверху, ниже идут открытые колонки (Беклог, В работе), выложенные строками по
 * свимлайнам (у каждой группы своя высота), поэтому карточки стоят напротив
 * своего названия, а не уезжают потоком вниз.
 *
 * Строка свимлайна — один flex-ряд (`items-stretch`): название и группы карточек
 * открытых колонок лежат в нём вместе. Высоту ряда задаёт самая высокая группа, а
 * ячейка названия тянется по ней силами флексбокса — без измерения через
 * `onLayout`. Раньше название и колонки были двумя независимыми стеками, и высоту
 * названия синхронизировал `onLayout` группы карточек: при добавлении карточки в
 * пустой свимлайн его размер приходил с устаревшими данными, повторного layout
 * не было, и название оставалось минимальной высоты — до перезагрузки страницы.
 *
 * Закрытые колонки (Готово, Отмена) свимлайнами не разложены: это простые списки,
 * которые идут сверху вниз поверх рядов, поэтому положение карточки там не зависит
 * от того, в каком свимлайне она лежит. Высота их списков и задаёт высоту плиты
 * колонки (`closedColumnsHeight`): подложка и открытые колонки растягиваются по ней.
 *
 * Фон и рамку колонок рисует подложка, растянутая за рядами на всю высоту уровня:
 * ряды прозрачны, поэтому колонка выглядит одной плитой, а не набором ячеек.
 *
 * Колонки делят свободную ширину поровну и растут вместе с окном (см. COLUMN_PANEL).
 * Любую колонку можно свернуть — она схлопывается в узкую полосу с вертикальным
 * названием и шевроном «развернуть».
 * На телефоне — аккордеон (сворачивание колонок там не нужно: они и так в строках).
 */

import { useCallback, useState } from 'react';
import { Pressable, Text, View } from 'react-native';

import { BoardCard } from './BoardCard';
import { ChevronLeftIcon, ChevronRightIcon, MenuIcon, SettingsIcon } from './BoardIcons';
import { ShowMoreButton } from './ShowMoreButton';
import { Swimlane, SwimlaneHeader } from './Swimlane';
import { COLUMN_MIN_HEIGHT, type CollapsedColumns } from '../columns';
import {
  closedHasMore,
  closedPageOf,
  columnCount,
  isClosedColumn,
  sortBacklogCards,
  type CardAction,
  type CardBreadcrumb,
  type ClosedPages,
} from '../derive';
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
 * `COLUMN_PANEL_MAX_WIDTH`: пока экран узкий, доска занимает его целиком, а на
 * больших мониторах колонки упираются в предел и справа остаётся свободное место.
 * Пропорции и предел задаются шириной числами (`sizeOfColumn`), а не классами:
 * на web `flex-1` превращается в `flex: 1` и перебивает `max-width` по порядку
 * каскада, а флексбокс с `max-width` свободное место вообще не распределяет.
 * Один класс используют и шапка колонок, и тело — иначе заголовки разъедутся
 * с самими колонками.
 *
 * Рядом лежат те же размеры числами: слой списков закрытых колонок позиционный,
 * ему нужны явные отступы и ширина распорок (см. `CLOSED_COLUMN_SLOT`). Панель
 * названий переключается по ширине окна (классы элементов), а числа для слоя
 * считаются от ширины контейнера колонок.
 */
const LANE_TITLE_BASE_WIDTH = 160;
const LANE_TITLE_WIDE_WIDTH = 210;
const LANE_TITLE_WIDTH = 'shrink-0 w-40';
const LANE_TITLE_WIDTH_WIDE = 'shrink-0 w-[210px]';
const COLUMN_PANEL_MAX_WIDTH = 380;
const COLUMN_PANEL = 'shrink-0 min-w-0';

/**
 * Ширина развёрнутой колонки — числом, а не флексом.
 *
 * Флексбоксу отдать эту роль нельзя. Доля поровну получается из
 * `flex-basis: 0`, но `flex` — это shorthand: на web Tailwind печатает
 * `.flex-1{flex:1}` раньше `.max-w-[380px]`, и `flex-basis: 0%` перебивает
 * предел. Если перенести предел во встроенный стиль, флексбокс всё равно
 * не раздаёт место: при `max-width` на элементе свободное пространство
 * остаётся нераспределённым, и колонка сразу получает предел. В ряду шириной
 * 1215 px «Беклог» и «В работе» стояли по 380 px вместо положенных поровну
 * ~260 px и налезали на соседние колонки.
 *
 * Поэтому ширину задаём числом (см. `sizeOfColumn`): свободное место после
 * панели названий делится поровну, но не больше `COLUMN_PANEL_MAX_WIDTH`.
 * Точно такую же формулу использует `layoutOfClosedColumns`, поэтому шапка,
 * подложка, ряды свимлайнов и слой закрытых колонок совпадают до пикселя.
 */
function sizeOfColumn(width: number): number {
  const available = Math.max(0, width - laneTitleSizeOf(width) - LAYOUT_GAP * COLUMNS.length);

  return Math.min(available / COLUMNS.length, COLUMN_PANEL_MAX_WIDTH);
}

/** Порядок колонок доски по id: индекс — позиция в раскладке. */
const COLUMN_IDS = COLUMNS.map((column) => column.id);

/** Зазор между панелью названий, колонками и рядами (`gap-2`). */
const LAYOUT_GAP = 8;

/** С этой ширины окна панель названий свимлайнов шире на 50 px. */
const WIDE_MIN_WIDTH = 1280;

function widthOfLaneTitle(width: number): string {
  return width > WIDE_MIN_WIDTH ? LANE_TITLE_WIDTH_WIDE : LANE_TITLE_WIDTH;
}

function laneTitleSizeOf(width: number): number {
  return width > WIDE_MIN_WIDTH ? LANE_TITLE_WIDE_WIDTH : LANE_TITLE_BASE_WIDTH;
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
 * Контейнер колонок: шапка и ряды свимлайнов.
 *
 * `min-w-0` — колонки не должны быть шире своей области: у флекс-элемента
 * минимальная ширина по умолчанию равна содержимому (`min-width: auto`), поэтому
 * длинные карточки закрытых колонок расширяли ряд и колонки выезжали за плитку —
 * правый отступ уходил за край уровня. `overflow-x-hidden` страхует от того же на
 * уровне обрезки по горизонтали.
 */
const COLUMN_CONTAINER = 'relative min-w-0 overflow-x-hidden gap-2';

/**
 * Ряд колонок в шапке уровня: та же ширина, что у рядов свимлайнов.
 */
const COLUMN_HEADER_ROW = 'z-10 w-full min-w-0 flex-row gap-2';

/**
 * Колонка в ряду свимлайна: только отступы и раскладка карточек.
 *
 * Фон и рамку рисует подложка колонки за рядами (`COLUMN_BACKDROP`), поэтому
 * здесь их нет: иначе на стыках строк появились бы двойные рамки и ячейки
 * выглядели бы отдельными плитками вместо одной колонки на всю высоту доски.
 */
const LANE_COLUMN_CELL = 'gap-2 p-2';

/**
 * Слот закрытой колонки в слое списков.
 *
 * Слой позиционный, а не флексбокс: флексбокс раздаёт место только своим детям и
 * не знает о колонках, которые остались в рядах свимлайнов, из-за чего списки
 * съезжали влево на первые колонки. Поэтому каждая колонка получает место
 * абсолютно (`left`/`width` из `closedColumnBox`), а список внутри прозрачен
 * для кликов: нажатия не должны перекрывать ряды свимлайнов под слоем. Клики
 * возвращает себе только сама плитка карточки — она помечена `pointerEvents="auto"`
 * в `BoardCard`, а пустые места слота остаются прозрачными.
 *
 * Прозрачность задаётся пропом `pointerEvents`, а не классом `pointer-events-none`:
 * на web NativeWind передаёт `className` в react-native-web как `style`, где CSS
 * `pointer-events` игнорируется (см. объяснение в `notifications/NotificationHost`).
 */
const CLOSED_COLUMN_SLOT = 'absolute top-0';

/**
 * Слой списков закрытых колонок.
 *
 * Лежит в теле доски абсолютом поверх рядов свимлайнов, поэтому ему нужен
 * собственный уровень наложения: без `z-10` он делил бы `z-index: 0` с рядами,
 * а их рисует порядок DOM — и тогда карточки «Готово»/«Отмены» оказывались под
 * рядами ниже по документу: строка свимлайна перекрывала кнопку названия, и
 * клик до карточки не доходил (курсор «рука», но реакции нет). Тот же уровень
 * у шапки колонок (`COLUMN_HEADER_ROW`), поэтому закрытые колонки не перекрывают
 * свёрнутые колонки подложки, которые поднимают себя сами.
 *
 * Клики по-прежнему не перехватываются: слой прозрачен (`pointerEvents="box-none"`),
 * а кликабельна только плитка карточки (`pointerEvents="auto"` в `BoardCard`).
 */
const CLOSED_COLUMNS_LAYER = 'absolute inset-0 z-10';

/**
 * Места развёрнутых закрытых колонок в слое списков: левый край и ширина.
 *
 * Повторяет раскладку шапки и рядов: свободное место после панели названий
 * делится поровну между колонками, но не больше `COLUMN_PANEL_MAX_WIDTH`;
 * свёрнутая колонка занимает узкую полосу `COLLAPSED_COLUMN_WIDTH`. Ширина
 * контейнера берётся его собственная, поэтому посчитанные места совпадают с
 * плитками подложки, которые раскладывает флексбокс.
 */
function layoutOfClosedColumns(
  containerWidth: number,
  collapsedColumns: CollapsedColumns,
): Partial<Record<ColumnId, { left: number; width: number }>> {
  // Слой слотов лежит в том же контейнере, что и слой подложки, у которого нет
  // собственных отступов: место колонки отсчитывается от панели названий и
  // зазора после неё. Внутренний отступ колонки (`p-2`) сюда не входит — его
  // даёт поле самой колонки в ряду, а не внешняя раскладка.
  const available = Math.max(0, containerWidth - laneTitleSizeOf(containerWidth) - LAYOUT_GAP * COLUMNS.length);
  const titleWidth = laneTitleSizeOf(containerWidth);
  const expandedWidth = Math.min(available / COLUMNS.length, COLUMN_PANEL_MAX_WIDTH);
  const boxes: Partial<Record<ColumnId, { left: number; width: number }>> = {};
  let offset = 0;

  COLUMNS.forEach((column, index) => {
    const collapsed = collapsedColumns[column.id] === true;
    const width = collapsed ? COLLAPSED_COLUMN_WIDTH : expandedWidth;
    // Первая колонка стоит сразу за панелью названий, каждая следующая — через зазор.
    const left = titleWidth + LAYOUT_GAP + offset;

    if (isClosedColumn(column.id) && !collapsed) {
      boxes[column.id] = { left, width };
    }

    // Свёрнутая колонка нарисована полосой в подложке, но её ширину всё равно
    // надо учесть: следующие колонки стоят за ней. Зазор прибавляется к каждой,
    // включая последнюю: во флексбоксе `gap-2` одинаков между всеми колонками.
    offset += width + LAYOUT_GAP;
  });

  return boxes;
}

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
      // Полоса лежит в слое подложки, прозрачном для кликов (`box-none`):
      // без `auto` кнопка «развернуть» на web не получала нажатий.
      pointerEvents="auto"
      className="shrink-0 self-stretch overflow-hidden rounded-lg border border-card-line bg-card"
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
   * Ключ свимлайна для UI: `direction-<id>`, `card-<id>` или `no_parent-<уровень>`.
   * Ключ строковый, потому что у служебного свимлайна числового id нет.
   */
  id: string;
  /** id родительской карточки/направления; у служебного свимлайна — null. */
  parentId: number | null;
  /**
   * Служебный свимлайн «Без инициативы» / «Без проекта»: родителя у карточки нет.
   * Кнопка создания в нём активна, но родителя не предопределяет — его выбирают
   * (или оставляют пустым) в форме.
   */
  service: boolean;
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
  /**
   * Состояние догрузки закрытых колонок по парам «колонка + уровень»: по нему
   * кнопка «Показать ещё» показывает индикатор и текст ошибки.
   */
  closedPages: ClosedPages;
  /**
   * Карточки уровня в исходном порядке доски (как в состоянии клиента).
   *
   * Списки закрытых колонок строятся по этому массиву, а не по свимлайнам:
   * свимлайны группируют карточки по родителю «в работе», и закрытая карточка
   * со старым родителем уезжает в служебный свимлайн — при догрузке такой
   * карточки появлялись бы в середине списка. В общем массиве догрузка всегда
   * добавляется в конец, поэтому лента закрытой колонки остаётся в порядке
   * «свежие сверху, догруженные — ниже».
   */
  levelCards: readonly Card[];
  /**
   * Догрузить следующую страницу закрытой колонки уровня. Пара «уровень +
   * колонка» адресуется типом карточки уровня — его `BoardLevel` знает сам.
   */
  onLoadMoreClosed: (columnId: ColumnId, kind: CardKind) => void;
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
  /** Путь карточки по иерархии для её крошек (считает `cardBreadcrumbs`). */
  cardBreadcrumbs: (card: Card) => CardBreadcrumb[];
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
  closedPages,
  levelCards,
  onLoadMoreClosed,
  collapsed,
  onToggle,
  compact,
  width,
  collapsedColumns,
  onToggleCollapsed,
  expandedColumns,
  onToggleColumn,
  cardAction,
  cardBreadcrumbs,
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
  // Кнопка «+» в заголовке свимлайна создаёт карточку в первой колонке доски.
  const firstColumnId = COLUMNS[0].id;

  // Открытые колонки раскладываются по свимлайнам, закрытые — простыми списками.
  const openColumns = COLUMNS.filter((column) => !isClosedColumn(column.id));

  /**
   * Лента закрытой колонки в порядке «свежие сверху».
   *
   * Свимлайны для этого не подходят: они группируют карточки по родителю «в
   * работе», и закрытая карточка с родителем, вышедшим из работы, уезжает в
   * служебный свимлайн — при догрузке она вставала бы в середину списка.
   *
   * Сортируем по идентификатору по убыванию, а не берём порядок массива как
   * есть: так лента не зависит от того, как карточки попали в состояние
   * (первичная порция, страница догрузки, перенос колонки, создание), и каждая
   * следующая страница гарантированно продолжает список вниз — сервер тоже
   * отдаёт закрытые карточки свежими сверху.
   */
  const closedCards = levelCards
    .filter((card) => isClosedColumn(card.column))
    .sort((left, right) => right.id - left.id);

  /**
   * Ширина контейнера колонок — основа расчёта мест в слое списков.
   *
   * Его ширина меньше ширины окна (внешние отступы уровня и заголовки), а у
   * `useWindowDimensions` она ещё и округлена до целых пикселей. Поэтому
   * позицию слоя считаем по фактической ширине контейнера, а ширину окна берём
   * только до первого `onLayout` — до него слой ещё не стоит в плитке.
   */
  const [columnsContainerWidth, setColumnsContainerWidth] = useState<number | null>(null);
  const layoutWidth = columnsContainerWidth ?? width;

  // Широкая панель названий на больших окнах: шапка, ряды и расчёт слоя
  // закрытых колонок берут одну и ту же основу — ширину контейнера.
  const laneTitleWidth = widthOfLaneTitle(layoutWidth);

  /**
   * Ширина развёрнутой колонки в текущем окне.
   *
   * Одна на всю доску: её получают шапка, подложка и ячейки рядов, иначе
   * заголовки и карточки разъедутся. Считается от ширины контейнера (до первого
   * замера — от ширины окна), см. `sizeOfColumn`.
   */
  const columnWidth = sizeOfColumn(layoutWidth);

  /**
   * Высота каждого списка закрытой колонки: по ним считается высота уровня.
   *
   * Слой списков абсолютный, поэтому сам он тело не растягивает. Высоту меряем
   * через `onLayout` и берём максимум: плита «Готово»/«Отмены» растёт вместе с
   * карточками, а подложка и открытые колонки тянутся за ней. Хранится по
   * колонкам, чтобы уменьшение одной не затирало высоту другой.
   */
  const [closedColumnsHeights, setClosedColumnsHeights] = useState<Record<string, number>>({});

  /**
   * Места развёрнутых закрытых колонок в слое списков: левый край и ширина.
   *
   * Ширину каждой колонки задаёт флексбокс шапки и рядов: свободное место после
   * панели названий делится поровну между колонками, но не больше
   * `COLUMN_PANEL_MAX_WIDTH`; свёрнутая колонка занимает узкую полосу. Поэтому
   * место колонки считается явно — флексбокс слоя не знал бы о колонках,
   * оставшихся в рядах свимлайнов, и его дети съезжали бы влево.
   */
  const closedColumnBoxes = layoutOfClosedColumns(layoutWidth, collapsedColumns);

  /**
   * Высота уровня по спискам закрытых колонок: самая длинная из них.
   *
   * Пустой уровень (списки ещё не измерены) остаётся на `COLUMN_MIN_HEIGHT`.
   */
  const closedColumnsHeight = Math.max(0, ...Object.values(closedColumnsHeights));

  /**
   * Запомнить высоту списка закрытой колонки.
   *
   * Высота хранится по колонке, а не общим максимумом: если карточки убрали из
   * «Отмены», уровень не должен оставаться растянутым по её прежней высоте.
   */
  const handleClosedColumnLayout = useCallback((columnId: ColumnId, height: number) => {
    setClosedColumnsHeights((prev) => (prev[columnId] === height ? prev : { ...prev, [columnId]: height }));
  }, []);

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
   * Кнопка создания в свимлайне есть везде, где уровень разрешает создавать
   * карточки. У обычного свимлайна родитель известен из него самого, у
   * служебного («Без инициативы» / «Без проекта») родителя нет: форма
   * открывается с выбором родителя, и опция «без родителя» уже доступна.
   *
   * Флаг `service` в условие не входит: в служебном свимлайне родитель не
   * задан, но создавать из него можно — значит, решает только `canCreate`.
   */
  const canAddInLane = (lane: SwimlaneModel) => canCreate && (lane.service || lane.parentId !== null);

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
            lanes.map((lane, index) => (
              <Swimlane
                key={lane.id}
                laneId={lane.id}
                title={lane.title}
                color={lane.color}
                cards={lane.cards}
                kind={kindOfLevel}
                closedTotals={closedTotals}
                closedPages={closedPages}
                levelCards={levelCards}
                // Кнопка «Показать ещё» у пары «уровень + колонка» одна, поэтому
                // её рисует последний свимлайн: над ним закрытые колонки уровня
                // показаны целиком, и лента логично завершается внизу.
                showClosedButtons={index === lanes.length - 1}
                onLoadMoreClosed={(columnId) => onLoadMoreClosed(columnId, kindOfLevel)}
                expandedColumns={expandedColumns}
                onToggleColumn={onToggleColumn}
                canCreate={canAddInLane(lane)}
                cardAction={cardAction}
                cardBreadcrumbs={cardBreadcrumbs}
                onCardAction={onCardAction}
                onOpenCard={onOpenCard}
                onAddCard={(columnId) => onAddCard(lane.parentId, columnId)}
              />
            ))
          ) : (
            // Контейнер широкой раскладки держит подложку колонок: она лежит
            // абсолютом за рядами свимлайнов и тянется на всю их высоту. Его
            // ширина — основа расчёта мест закрытых колонок: своя ширина точнее
            // ширины окна, из которой ещё вычтены отступы уровня.
            <View
              className={COLUMN_CONTAINER}
              onLayout={(event) => setColumnsContainerWidth(event.nativeEvent.layout.width)}
            >
              {/* Шапка печатается один раз на уровень: дальше колонки идут сквозь
                  все свимлайны без повторения названий. Счётчик стоит рядом с
                  названием, у правого края — меню (пока заглушка) и сворачивание.
                  У свёрнутой колонки в шапке остаётся только шеврон «развернуть»:
                  он стоит над узкой полосой. */}
              <View className={COLUMN_HEADER_ROW}>
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
                    <View key={column.id} className={['px-2', COLUMN_PANEL].join(' ')} style={{ width: columnWidth }}>
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

              {/* Тело: строки по свимлайнам и списки закрытых колонок поверх подложки.
                  В каждом ряду название и группы карточек открытых колонок
                  лежат вместе (`items-stretch`), поэтому высоту названия даёт самая
                  высокая группа — флексбокс подтягивает её сразу, как только в
                  свимлайне появилась карточка.
                  Подложка (`COLUMN_BACKDROP`) растянута на всю высоту тела и кладётся
                  под рядами: рамка и заливка колонки не режутся на стыках строк,
                  поэтому колонки выглядят монолитными, как плита в Jira. Высота
                  тела не ниже `COLUMN_MIN_HEIGHT`: колонки высокие даже на почти
                  пустой доске. Если карточек в «Готово»/«Отмене» больше, чем в
                  беклоге, высоту задают их списки (`closedColumnsHeight`).
                  Подложка живёт внутри тела, а не на уровне целиком: иначе её
                  плитки накрыли бы шапку с названиями колонок и кнопками. */}
              <View
                className="relative w-full gap-2"
                style={{ minHeight: Math.max(COLUMN_MIN_HEIGHT, closedColumnsHeight) }}
              >
                {/* Слой подложки: развёрнутая колонка — одна плитка на всю высоту,
                    свёрнутая — одна полоса на всю высоту (см. CollapsedColumn).
                    `pointerEvents="box-none"` — слой декоративный и не должен
                    перехватывать клики по карточкам; свёрнутая полоса возвращает
                    себе клики через `pointerEvents="auto"`.
                    Проп, а не класс `pointer-events-none`: на web класс попадает
                    в `style`, где react-native-web игнорирует `pointer-events`.
                    Свёрнутая колонка живёт здесь, а не в рядах свимлайнов: она
                    одна на всю доску, с названием по центру высоты, а в ряду
                    остаётся только распорка её ширины. */}
                <View pointerEvents="box-none" className="absolute inset-x-0 bottom-0 top-0 flex-row gap-2">
                  <View className={laneTitleWidth} />

                  {COLUMNS.map((column) =>
                    collapsedColumns[column.id] === true ? (
                      <CollapsedColumn
                        key={column.id}
                        label={column.label}
                        onExpand={() => onToggleCollapsed(column.id)}
                      />
                    ) : (
                      <View
                        key={column.id}
                        className={[COLUMN_BACKDROP, COLUMN_PANEL].join(' ')}
                        style={{ width: columnWidth }}
                      />
                    ),
                  )}
                </View>

                {/* Списки закрытых колонок: карточки «Готово» и «Отмены» идут подряд
                    сверху вниз, независимо от свимлайнов, в которых они лежат.
                    Слой абсолютный и начинается от верха тела, поэтому первого
                    свимлайна карточки не ждут. Место каждой колонки считается явно
                    (`closedColumnBoxes`): флексбокс слоя не знал бы о колонках,
                    оставшихся в рядах свимлайнов, и списки съезжали влево,
                    накрывая первые колонки. Ширина слота фиксирована, поэтому
                    карточки не могут вытолкнуть его отступы наружу. */}
                <View pointerEvents="box-none" className={CLOSED_COLUMNS_LAYER}>
                  {COLUMN_IDS.map((columnId) => {
                    const box = closedColumnBoxes[columnId];

                    // Свёрнутая закрытая колонка рисуется полосой в подложке.
                    if (box === undefined) {
                      return null;
                    }

                    const columnCards = closedCards.filter((card) => card.column === columnId);
                    // «Есть ещё» — сравнение счётчика пары с загруженным уровнем
                    // целиком: лента закрытой колонки здесь одна на уровень, она
                    // не разрезана по свимлайнам.
                    const hasMore = closedHasMore(levelCards, columnId, kindOfLevel, closedTotals, closedPages);
                    const page = closedPageOf(closedPages, columnId, kindOfLevel);

                    return (
                      <View
                        key={columnId}
                        // Слот прозрачен для кликов мимо карточек: ряды свимлайнов
                        // лежат под слоем и должны получать нажатия сами.
                        pointerEvents="box-none"
                        className={CLOSED_COLUMN_SLOT}
                        style={{ left: box.left, width: box.width, overflow: 'hidden' }}
                      >
                        {/* Список не должен расширяться от содержимого: у
                            флекс-элемента по умолчанию `min-width: auto`, и
                            длинное название карточки растягивало её до своей
                            минимальной ширины. Тогда карточка занимала всю
                            ширину слота и `p-2` схлопывался — так «Отмена» с
                            длинными названиями оставалась без отступов.
                            `overflow: hidden` на слоте и `minWidth: 0` со
                            `width: 100%` здесь держат карточки в контентной
                            ширине списка. */}
                        <View
                          className="gap-2 p-2"
                          style={{ width: '100%', minWidth: 0 }}
                          onLayout={(event) => handleClosedColumnLayout(columnId, event.nativeEvent.layout.height)}
                        >
                          {columnCards.length === 0 ? (
                            // Та же плашка, что в аккордеоне телефона: пустая
                            // закрытая колонка не остаётся без подсказки, когда
                            // её высота стала самостоятельной.
                            <View className="items-center rounded border border-dashed border-card-line px-2 py-3">
                              <Text className="text-xs text-subtle">Пусто</Text>
                            </View>
                          ) : null}

                          {columnCards.map((card) => (
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
                              closedColumn={isClosedColumn(columnId)}
                              idea={card.idea}
                              backlogColumn={columnId === 'backlog'}
                              action={cardAction(card)}
                              onAction={() => onCardAction(card)}
                              onPress={() => onOpenCard(card)}
                            />
                          ))}

                          {/* «Показать ещё» закрытой колонки завершает список:
                              порция догружается вниз, а кнопка остаётся на месте,
                              пока у пары есть незагруженные карточки. Причина
                              неудачной догрузки показывается тостом. */}
                          {isClosedColumn(columnId) && (hasMore || page.error !== null) ? (
                            <ShowMoreButton
                              onPress={() => onLoadMoreClosed(columnId, kindOfLevel)}
                              loading={page.loading}
                            />
                          ) : null}
                        </View>
                      </View>
                    );
                  })}
                </View>

                <View className="w-full gap-2">
                  {lanes.map((lane) => (
                    <View key={lane.id} className={LANE_ROW} style={{ minHeight: LANE_ROW_MIN_HEIGHT, minWidth: 0 }}>
                      <View className={[laneTitleWidth, LANE_TITLE_CELL].join(' ')}>
                        <SwimlaneHeader
                          title={lane.title}
                          color={lane.color}
                          addLabel="Добавить карточку"
                          canAdd={canCreate}
                          canAddInLane={canAddInLane(lane)}
                          onAddCard={() => onAddCard(lane.parentId, firstColumnId)}
                        />
                      </View>

                      {/* В ряду только открытые колонки: закрытые нарисованы списками
                          поверх рядов и от свимлайна не зависят. */}
                      {openColumns.map((column) => {
                        // Свёрнутая колонка нарисована один раз в подложке на всю
                        // высоту доски, поэтому в ряду остаётся только распорка её
                        // ширины: без неё ячейки разъехались бы с подложкой.
                        if (collapsedColumns[column.id] === true) {
                          return (
                            <View key={column.id} className="shrink-0" style={{ width: COLLAPSED_COLUMN_WIDTH }} />
                          );
                        }

                        // В беклоге сверху идут карточки «на реализацию», ниже — идеи
                        // (см. `sortBacklogCards`); остальные колонки — как есть.
                        const laneCards = sortBacklogCards(
                          lane.cards.filter((card) => card.column === column.id),
                          column.id,
                        );

                        // Фон и рамку даёт подложка ряда, поэтому ячейка прозрачна:
                        // пустая группа просто растягивается по высоте ряда.
                        return (
                          <View
                            key={column.id}
                            className={[LANE_COLUMN_CELL, COLUMN_PANEL].join(' ')}
                            style={{ width: columnWidth }}
                          >
                            {laneCards.map((card) => (
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
