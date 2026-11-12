/**
 * Главный экран: трёхуровневая канбан-доска.
 *
 * Данные приходят из backend API через `useBoard()` (src/board/store.ts):
 * карточки, направления и переходы между колонками сервер отдаёт готовыми.
 * Уровни сворачиваются в строку, колонки фиксированные. На телефоне колонки
 * внутри свимлайна раскладываются вертикально как аккордеон.
 *
 * Режим «только чтение» экран не моделирует: доска выглядит и ведёт себя как
 * обычная, любые действия отправляются на сервер, а запрет на запись приходит
 * его ответом (403 с текстом в `errors.account`) — тостом или внутри формы.
 * Единственное отличие — плашка `ReadOnlyBanner` над доской, которая сообщает
 * о режиме.
 */

import { useNavigation } from 'expo-router';
import { useCallback, useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Platform,
  Pressable,
  ScrollView,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';

import { useAuth } from '../src/auth/AuthContext';
import { BoardLevel, type LevelCreateHintModel, type SwimlaneModel } from '../src/board/components/BoardLevel';
import { HamburgerIcon } from '../src/board/components/BoardIcons';
import { CardEditModal } from '../src/board/components/CardEditModal';
import { CardFormModal, type CardDraft } from '../src/board/components/CardFormModal';
import { DirectionsModal } from '../src/board/components/DirectionsModal';
import { LevelSettingsMenu } from '../src/board/components/LevelSettingsMenu';
import { UNPARENTED_CARD_COLOR, directionColorHex } from '../src/board/directionColors';
import {
  cardAction,
  cardBreadcrumbs,
  checkDirectionDelete,
  isClosedColumn,
  parentCandidates,
} from '../src/board/derive';
import { parentOf } from '../src/board/mappers';
import { useBoardSettings } from '../src/board/settings';
import { useBoard } from '../src/board/store';
import {
  COLUMNS,
  DEFAULT_CARD_PRIORITY,
  LEVELS,
  PARENT_KIND,
  type BoardLevel as BoardLevelModel,
  type Card,
  type CardKind,
  type CardPriority,
  type ColumnId,
  type Direction,
  type LevelId,
} from '../src/board/types';
import { FormAlert } from '../src/components/FormAlert';
import { MainMenu } from '../src/components/MainMenu';
import { ReadOnlyBanner } from '../src/components/ReadOnlyBanner';
import { useNotifications } from '../src/notifications/NotificationContext';

/** Ниже этой ширины доска переключается в одноколоночный режим (телефон). */
const COMPACT_MAX_WIDTH = 900;

/** Колонка, в которую попадает карточка, созданная из шапки уровня: всегда «Беклог». */
const LEVEL_CREATE_COLUMN: ColumnId = COLUMNS[0].id;

const LEVEL_CREATE_LABEL: Record<LevelId, string> = {
  strategic: '+ Инициатива',
  tactical: '+ Проект',
  operational: '+ Задача',
};

/**
 * Подсказка под кнопкой уровня, если создать карточку нельзя.
 *
 * Кнопку блокирует только стратегический уровень: инициатива не может жить
 * вне направления. Проект без инициативы и задачу без проекта создавать можно —
 * сервер это разрешает (TaskPlacement), поэтому блокировать тактику и операционный
 * уровень из-за пустых свимлайнов не нужно.
 *
 * Текст подсказки тот же, что был, но слово «направление» — ссылка: клик сразу
 * открывает попап «Направления», где направление и добавляют.
 */
function createHintOf(level: LevelId, onOpenDirections: () => void): LevelCreateHintModel | null {
  if (level !== 'strategic') {
    return null;
  }

  return {
    before: 'Сначала добавьте ',
    link: 'направление',
    after: ': настройки уровня → «Направления»',
    onPressLink: onOpenDirections,
  };
}

/** Тип карточки, который создают на уровне. */
const LEVEL_CARD_KIND: Record<LevelId, CardKind> = {
  strategic: 'initiative',
  tactical: 'project',
  operational: 'task',
};

/** Черновик формы: откуда и какую карточку создают. */
interface FormContext {
  level: LevelId;
  kind: CardKind;
  /** Родитель из свимлайна; null — родителя выбирают вручную или он не нужен. */
  parentId: number | null;
  column: ColumnId;
}

function columnLabel(column: ColumnId): string {
  return COLUMNS.find((item) => item.id === column)?.label ?? column;
}

/**
 * Свимлайны уровня:
 * - стратегический — направления деятельности; служебного свимлайна здесь нет:
 *   инициатива без направления невозможна (сервер её не создаст);
 * - тактический/операционный — родительские карточки из «В работе»; карточки
 *   без родителя (проект без инициативы, задача без проекта) собираются в
 *   служебный свимлайн «Без инициативы» / «Без проекта».
 *
 * Служебный свимлайн показывается, только пока в нём есть карточки в активных
 * колонках: если он остался из одних закрытых, строка уровня пустует зря.
 */
function swimlanesOf(
  cards: readonly Card[],
  directions: readonly Direction[],
  level: BoardLevelModel,
): SwimlaneModel[] {
  if (level.id === 'strategic') {
    const initiatives = cards.filter((card) => card.kind === 'initiative');

    return directions.map((direction) => ({
      id: `direction-${direction.id}`,
      parentId: direction.id,
      service: false,
      title: direction.title,
      // У направления хранится ключ палитры, свимлайн рисуется оттенком.
      color: directionColorHex(direction.color),
      cards: initiatives.filter((card) => card.directionId === direction.id),
    }));
  }

  const parentKind = PARENT_KIND[level.id];
  const childKind = LEVEL_CARD_KIND[level.id];

  if (parentKind === null) {
    return [];
  }

  const parents = parentCandidates(cards, parentKind);
  const children = cards.filter((card) => card.kind === childKind);

  const lanes: SwimlaneModel[] = parents.map((parent) => ({
    id: `card-${parent.id}`,
    parentId: parent.id,
    service: false,
    title: parent.title,
    // У карточки-родителя хранится ключ палитры, свимлайн рисуется оттенком.
    // У проекта без инициативы цвет null — резолвер отдаёт служебный c0,
    // поэтому такой свимлайн не остаётся без точки цвета.
    color: directionColorHex(parent.color),
    // Дети свимлайна — карточки, у которых родитель именно эта карточка.
    cards: children.filter((card) => card.kind !== parent.kind && parentOf(card)?.id === parent.id),
  }));

  // Карточки без родителя (проект без инициативы, задача без проекта) и
  // карточки, чей родитель уже не в работе, показывать негде — их видно в
  // служебном свимлайне. На тактическом уровне второго случая не бывает:
  // сервер не выпускает инициативу из работы, пока у неё есть проекты в работе.
  const inLane = new Set(lanes.flatMap((lane) => lane.cards.map((card) => card.id)));
  const orphaned = children.filter((card) => !inLane.has(card.id));

  // Свимлайн показывается, только пока у него есть карточки в активных колонках:
  // из одних закрытых он даёт пустую строку и кнопку создания на пустом месте,
  // а сами закрытые карточки видны в плитах «Готово»/«Отмена» (они строятся по
  // карточкам уровня, а не по свимлайнам).
  const hasActive = orphaned.some((card) => !isClosedColumn(card.column));

  if (hasActive) {
    lanes.push({
      id: `no_parent-${level.id}`,
      parentId: null,
      service: true,
      title: childKind === 'project' ? 'Без инициативы' : 'Без проекта',
      // Свимлайн «без родителя» — это и есть служебный цвет c0.
      color: directionColorHex(UNPARENTED_CARD_COLOR),
      cards: orphaned,
    });
  }

  return lanes;
}

/** Черновик для модалки: вид формы зависит от того, откуда её открыли. */
function buildDraft(
  form: FormContext | null,
  cards: readonly Card[],
  directions: readonly Direction[],
): CardDraft | null {
  if (form === null) {
    return null;
  }

  const level = LEVELS.find((item) => item.id === form.level) ?? LEVELS[0];
  const parentKind = PARENT_KIND[form.level];
  const parent = cards.find((card) => card.id === form.parentId) ?? null;
  const direction = directions.find((item) => item.id === form.parentId) ?? null;
  // Родитель из свимлайна: цвет известен сразу, выбирать ничего не нужно.
  const fromLane = parent !== null || direction !== null;

  return {
    kind: form.kind,
    laneId: fromLane ? form.parentId : null,
    laneColor: parent?.color ?? direction?.color ?? null,
    laneTitle: parent?.title ?? direction?.title ?? null,
    // Уровень формы задаёт родителя: у стратегического это направление из списка,
    // у остальных — родительская карточка. Пустой список не блокирует форму:
    // проект без инициативы и задачу без проекта сервер разрешает.
    parentOptions: fromLane ? null : parentKind === null ? [...directions] : parentCandidates(cards, parentKind),
    // Родитель необязателен на тактическом (проект без инициативы) и
    // операционном (задача без проекта) уровнях. У инициативы направление обязательно.
    allowNoParent: !fromLane && form.level !== 'strategic',
    noParentLabel: form.level === 'tactical' ? 'Без инициативы (нейтральный цвет)' : 'Без проекта (нейтральный цвет)',
    columnLabel: columnLabel(form.column),
    columnId: form.column,
    // Приоритет новой карточки — средний: его же показывает форма по умолчанию,
    // и с него начинается состояние модалки (см. CardFormModal).
    priority: DEFAULT_CARD_PRIORITY,
    levelLabel: level.label,
  };
}

export default function HomeScreen() {
  const { session, signOut, isReadonly } = useAuth();
  const navigation = useNavigation();
  const { width } = useWindowDimensions();
  const compact = width < COMPACT_MAX_WIDTH;

  // Доска: карточки, направления и все изменения живут в API (src/board/store.ts).
  const board = useBoard();
  const { cards, directions, closedTotals, closedPages, isLoading, loadError, reload } = board;

  /**
   * Свёрнутые уровни и колонки — настройки пользователя: они сохраняются на
   * сервере (UserMeta) и восстанавливаются при следующем открытии доски.
   */
  const { collapsedLevels, collapsedColumns, toggleLevel, toggleColumn } = useBoardSettings();
  const [expandedColumns, setExpandedColumns] = useState<Record<string, boolean>>({});
  /** Повторная загрузка по кнопке: `reload` из хука сам индикатор не поднимает. */
  const [isRefreshing, setIsRefreshing] = useState(false);

  /** Перечитать доску с индикатором загрузки — кнопка «Повторить» при ошибке. */
  const reloadBoard = async () => {
    setIsRefreshing(true);
    await reload();
    setIsRefreshing(false);
  };
  const [form, setForm] = useState<FormContext | null>(null);
  /** Карточка, открытая на редактирование; null — попап закрыт. */
  const [editedCardId, setEditedCardId] = useState<number | null>(null);
  /** Подсказка над доской: запрет переноса по правилам иерархии карточек. */
  const [notice, setNotice] = useState<string | null>(null);
  /** Уровень, у которого открыто меню «Настройки уровня»; null — меню закрыто. */
  const [settingsLevel, setSettingsLevel] = useState<LevelId | null>(null);
  /** true — открыт попап настроек направлений. */
  const [directionsOpen, setDirectionsOpen] = useState(false);
  /** true — открыто главное меню (гамбургер в шапке). */
  const [mainMenuOpen, setMainMenuOpen] = useState(false);

  /**
   * Разовые сообщения доски, которые не зависят от ответа сервера (запрет
   * переноса по правилам иерархии), показываются строкой над доской: они
   * объясняют действие пользователя и остаются на экране, пока он не сделает
   * что-то осмысленное. Всплывающие уведомления (`src/notifications`) оставлены
   * для ответов сервера — например, для отказа записи в режиме «только чтение».
   */
  const { notifyError } = useNotifications();

  // Гамбургер главного меню — в правом углу шапки стека. Настройки шапки
  // задаются экраном (см. app/_layout.tsx), само меню — модалка MainMenu.
  useEffect(() => {
    navigation.setOptions({
      headerRight: () => (
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Главное меню"
          onPress={() => setMainMenuOpen(true)}
          hitSlop={8}
          className="h-9 w-9 items-center justify-center rounded-lg active:bg-surface"
        >
          <HamburgerIcon />
        </Pressable>
      ),
    });
  }, [navigation]);

  /** Аккордеон колонок на телефоне: раскрыть/свернуть карточки колонки свимлайна. */
  const toggleLaneColumn = (key: string) => {
    setExpandedColumns((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  /**
   * Крошки карточки: путь по карточкам иерархии от корня к её родителю.
   *
   * Путь собирается по уже загруженным карточкам — отдельного запроса к API не
   * нужно; направления в крошки не входят, поэтому у инициатив их нет. Функция
   * мемоизирована: она передаётся во все плитки доски, и новая ссылка на каждом
   * рендере ломала бы их перерисовку без причины.
   */
  const breadcrumbsOf = useCallback((card: Card) => cardBreadcrumbs(card, cards), [cards]);

  /** Действие над колонками: выполняем, если правило разрешает, иначе показываем причину. */
  const handleCardAction = (card: Card) => {
    const action = cardAction(card);

    if (action === null) {
      return;
    }

    if (action.blockReason === null) {
      setNotice(null);
      // Перевод в следующую колонку — PATCH /tasks/{id}; при 409 сервер объясняет запрет.
      void board.moveCard(card, action.target).then((error) => {
        if (error !== null) {
          notifyError(error, { title: 'Не удалось перенести карточку' });
        }
      });

      return;
    }

    // Причина посчитана по иерархии карточек, без запроса: её видно и над доской,
    // и в системном диалоге на native (на web Alert не работает).
    setNotice(action.blockReason);

    if (Platform.OS !== 'web') {
      Alert.alert('Перенос запрещён', action.blockReason);
    }
  };

  /**
   * «Показать ещё» в закрытой колонке: следующая страница пары «уровень + колонка».
   *
   * Ошибку показываем тостом: у самой кнопки она была бы мелкой подписью, а на
   * телефоне лента может оказаться ниже экрана — причина отказа должна быть
   * видна сразу после клика.
   */
  const handleLoadMoreClosed = (column: ColumnId, kind: CardKind) => {
    void board.loadMoreClosed(column, kind).then((error) => {
      if (error !== null) {
        notifyError(error, { title: 'Не удалось загрузить карточки' });
      }
    });
  };

  /** Форма из свимлайна: родитель предопределён. */
  const openLaneForm = (level: BoardLevelModel, parentId: number | null, column: ColumnId) => {
    setForm({ level: level.id, kind: LEVEL_CARD_KIND[level.id], parentId, column });
  };

  /** Форма из шапки уровня: родителя выбирают вручную, карточка — в беклог. */
  const openLevelForm = (level: BoardLevelModel) => {
    setForm({
      level: level.id,
      kind: LEVEL_CARD_KIND[level.id],
      parentId: null,
      column: LEVEL_CREATE_COLUMN,
    });
  };

  /**
   * Создание карточки: родителя, срок и кодовое название выбирает форма,
   * остальное (цвет, ссылки иерархии) считает сервер, а модалке возвращается
   * текст ошибки для показа внутри формы.
   */
  const createCard = async (
    cardDraft: CardDraft,
    title: string,
    slug: string | null,
    description: string | null,
    parentId: number | null,
    deadline: string | null,
    idea: boolean,
    priority: CardPriority,
  ) => {
    const error = await board.createCard(cardDraft, title, slug, description, parentId, deadline, idea, priority);

    if (error === null) {
      setForm(null);
      setNotice(null);
    }

    return error;
  };

  /**
   * Изменение карточки: название, кодовое название, описание, срок, приоритет
   * и статус. Ошибку возвращаем модалке — она показывается внутри попапа,
   * рядом с полями.
   */
  const saveCard = async (
    card: Card,
    title: string,
    slug: string | null | undefined,
    description: string | null,
    column: ColumnId,
    deadline: string | null | undefined,
    idea: boolean | undefined,
    priority: CardPriority | undefined,
  ) => {
    const error = await board.saveCard(card, title, slug, description, column, deadline, idea, priority);

    if (error === null) {
      setEditedCardId(null);
      setNotice(null);
    }

    return error;
  };

  /** Удаление карточки; 409 (есть дочерние карточки) возвращаем модалке. */
  const removeCard = async (card: Card) => {
    const error = await board.removeCard(card);

    if (error === null) {
      setEditedCardId(null);
      setNotice(null);
    }

    return error;
  };

  const draft = buildDraft(form, cards, directions);

  const editedCard = editedCardId === null ? null : (cards.find((card) => card.id === editedCardId) ?? null);
  // Сменить статус нельзя, если сервер разрешил только текущую колонку.
  const editedCardLockedReason =
    editedCard !== null && editedCard.allowedTransitions.length <= 1
      ? `Нельзя сменить статус «${editedCard.title}»: у неё есть зависимые карточки в работе. Сначала завершите или отмените их.`
      : null;

  const handleSignOut = async () => {
    setMainMenuOpen(false);
    await signOut();
  };

  return (
    <View className="flex-1 bg-surface">
      <ScrollView className="flex-1" contentContainerClassName="gap-3 px-3 py-3">
        {/* Режим «только чтение» — информационная плашка, единственный элемент,
            отличающий доску от обычной. */}
        {isReadonly ? <ReadOnlyBanner /> : null}

        {/* Запрет переноса по иерархии считается на клиенте, без ответа сервера,
            поэтому показывается здесь же — строкой над доской, а не тостом. */}
        {notice ? <FormAlert message={notice} /> : null}

        {/* Доска не загрузилась — это состояние экрана, а не разовое сообщение,
            поэтому в теле доски остаётся блок с кнопкой «Повторить» в том же
            оформлении, что и у ошибки загрузки карточки. */}
        {loadError !== null ? (
          <View className="items-center gap-3 rounded-xl border border-border bg-card p-4">
            <Text className="text-sm font-semibold text-app">Не удалось загрузить доску</Text>
            <Text className="text-center text-xs text-muted">Проверьте соединение с сервером и повторите.</Text>

            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: isRefreshing }}
              disabled={isRefreshing}
              onPress={() => void reloadBoard()}
              className={[
                'h-10 w-full items-center justify-center rounded-xl bg-app active:opacity-90',
                isRefreshing ? 'opacity-60' : '',
              ].join(' ')}
            >
              <Text className="text-sm font-semibold text-white">Повторить</Text>
            </Pressable>
          </View>
        ) : null}

        {isLoading ? (
          <View className="items-center gap-2 rounded-xl bg-card px-4 py-10">
            <ActivityIndicator color="#111827" />
            <Text className="text-xs text-muted">Загружаем доску…</Text>
          </View>
        ) : null}

        {!isLoading && loadError === null && directions.length === 0 && cards.length === 0 ? (
          <View className="items-center gap-1 rounded-xl bg-card px-4 py-10">
            <Text className="text-sm font-semibold text-app">Доска пуста</Text>
            <Text className="text-center text-xs text-muted">
              Добавьте направление: в настройках стратегического уровня, затем создайте инициативу. Проекты и задачи
              можно создавать и без родителя.
            </Text>
          </View>
        ) : null}

        {LEVELS.map((level) => {
          const lanes = swimlanesOf(cards, directions, level);
          // Карточки уровня в порядке сервера: по нему строятся ленты закрытых
          // колонок, чтобы догруженные карточки оставались в конце списка.
          const levelCards = cards.filter((card) => card.kind === LEVEL_CARD_KIND[level.id]);
          // Кнопку блокирует только стратегический уровень: инициативе родитель
          // (направление) обязателен, а проекту и задаче родитель не нужен —
          // они создаются и «без инициативы» / «без проекта». Ограничение прав
          // кнопка не учитывает: доска одинакова для всех, а отказ записи
          // приходит от сервера внутри формы.
          const canCreate = level.id !== 'strategic' || directions.length > 0;

          return (
            <BoardLevel
              key={level.id}
              level={level}
              lanes={lanes}
              closedTotals={closedTotals}
              closedPages={closedPages}
              levelCards={levelCards}
              onLoadMoreClosed={handleLoadMoreClosed}
              collapsed={collapsedLevels[level.id] === true}
              onToggle={() => toggleLevel(level.id)}
              compact={compact}
              width={width}
              collapsedColumns={collapsedColumns[level.id] ?? {}}
              onToggleCollapsed={(columnId) => toggleColumn(level.id, columnId)}
              expandedColumns={expandedColumns}
              onToggleColumn={toggleLaneColumn}
              cardAction={cardAction}
              cardBreadcrumbs={breadcrumbsOf}
              onCardAction={handleCardAction}
              onOpenCard={(card) => setEditedCardId(card.id)}
              onAddCard={(parentId, columnId) => openLaneForm(level, parentId, columnId)}
              onCreateInLevel={() => openLevelForm(level)}
              createLabel={LEVEL_CREATE_LABEL[level.id]}
              canCreate={canCreate}
              createHint={canCreate ? null : createHintOf(level.id, () => setDirectionsOpen(true))}
              onOpenSettings={() => setSettingsLevel(level.id)}
            />
          );
        })}

        <Text className="px-1 pb-2 text-center text-[11px] text-subtle">
          Данные доски хранятся на сервере и общие для всех устройств
        </Text>
      </ScrollView>

      {/* Главное меню: действия уровня аккаунта. Открывается гамбургером в шапке,
          поэтому данных пользователя на самой доске больше нет. */}
      <MainMenu
        visible={mainMenuOpen}
        email={session?.user?.email ?? '—'}
        items={[
          { label: 'Выйти', confirm: true, confirmQuestion: 'Выйти из аккаунта?', onPress: () => void handleSignOut() },
        ]}
        onClose={() => setMainMenuOpen(false)}
      />

      {/* Меню настроек уровня: иконка есть на всех уровнях, но пункты свои.
          Сейчас «Направления» есть только у стратегического — там они и живут. */}
      <LevelSettingsMenu
        visible={settingsLevel !== null}
        levelLabel={LEVELS.find((item) => item.id === settingsLevel)?.label ?? ''}
        items={
          settingsLevel === 'strategic'
            ? [
                {
                  label: 'Направления',
                  onPress: () => {
                    setSettingsLevel(null);
                    setDirectionsOpen(true);
                  },
                },
              ]
            : []
        }
        onClose={() => setSettingsLevel(null)}
      />

      <DirectionsModal
        visible={directionsOpen}
        directions={directions}
        limit={board.directionLimit}
        onAdd={board.addDirection}
        onUpdate={board.editDirection}
        onDelete={board.removeDirection}
        deleteReason={(direction) => checkDirectionDelete(cards, direction).reason}
        onClose={() => setDirectionsOpen(false)}
      />

      <CardFormModal draft={draft} onCreate={createCard} onClose={() => setForm(null)} />

      <CardEditModal
        card={editedCard}
        lockedReason={editedCardLockedReason}
        onSave={saveCard}
        onDelete={removeCard}
        onClose={() => setEditedCardId(null)}
      />
    </View>
  );
}
