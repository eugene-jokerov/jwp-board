/**
 * Главный экран: трёхуровневая канбан-доска.
 *
 * Доска пока только визуальная — данные лежат в состоянии экрана (board/data.ts).
 * Уровни сворачиваются в строку, колонки фиксированные. На телефоне колонки
 * внутри свимлайна раскладываются вертикально как аккордеон.
 */

import { useState } from 'react';
import { Alert, Platform, Pressable, ScrollView, Text, View, useWindowDimensions } from 'react-native';

import { useAuth } from '../src/auth/AuthContext';
import { BoardLevel, type SwimlaneModel } from '../src/board/components/BoardLevel';
import { CardEditModal } from '../src/board/components/CardEditModal';
import { CardFormModal, type CardDraft } from '../src/board/components/CardFormModal';
import { DirectionsModal } from '../src/board/components/DirectionsModal';
import { LevelSettingsMenu } from '../src/board/components/LevelSettingsMenu';
import { DIRECTIONS, INITIAL_CARDS } from '../src/board/data';
import { UNPARENTED_CARD_COLOR, directionColorHex } from '../src/board/directionColors';
import {
  cardAction,
  checkColumnChange,
  checkDirectionDelete,
  parentCandidates,
  recolorDirection,
  updateCard,
} from '../src/board/derive';
import {
  COLUMNS,
  LEVELS,
  PARENT_KIND,
  type BoardLevel as BoardLevelModel,
  type Card,
  type CardKind,
  type ColumnId,
  type Direction,
  type LevelId,
} from '../src/board/types';
import { FormAlert } from '../src/components/FormAlert';

/** Ниже этой ширины доска переключается в одноколоночный режим (телефон). */
const COMPACT_MAX_WIDTH = 900;

/** Колонка, в которую попадает карточка, созданная из шапки уровня: всегда «Беклог». */
const LEVEL_CREATE_COLUMN: ColumnId = COLUMNS[0].id;

const LEVEL_CREATE_LABEL: Record<LevelId, string> = {
  strategic: '+ Инициатива',
  tactical: '+ Проект',
  operational: '+ Задача',
};

/** Текст под кнопкой уровня, если создать карточку нельзя. */
const LEVEL_CREATE_HINT: Record<LevelId, string> = {
  strategic: '',
  tactical: 'Нужна хотя бы одна инициатива в «В работе»',
  operational: 'Нужен хотя бы один проект в «В работе»',
};

/** Тип карточки, который создают на уровне. */
const LEVEL_CARD_KIND: Record<LevelId, CardKind> = {
  strategic: 'initiative',
  tactical: 'epic',
  operational: 'task',
};

/** Черновик формы: откуда и какую карточку создают. */
interface FormContext {
  level: LevelId;
  kind: CardKind;
  /** Родитель из свимлайна; null — родителя выбирают вручную или он не нужен. */
  parentId: string | null;
  column: ColumnId;
}

function columnLabel(column: ColumnId): string {
  return COLUMNS.find((item) => item.id === column)?.label ?? column;
}

/**
 * Свимлайны уровня:
 * - стратегический — направления деятельности;
 * - тактический/операционный — родительские карточки из «В работе»; карточки
 *   родителей вне работы и карточки без родителя собираются в свимлайн «без родителя».
 */
function swimlanesOf(
  cards: readonly Card[],
  directions: readonly Direction[],
  level: BoardLevelModel,
): SwimlaneModel[] {
  if (level.id === 'strategic') {
    return directions.map((direction) => ({
      id: direction.id,
      parentId: direction.id,
      title: direction.title,
      // У направления хранится ключ палитры, свимлайн рисуется оттенком.
      color: directionColorHex(direction.color),
      cards: cards.filter((card) => card.kind === 'initiative' && card.parentId === direction.id),
    }));
  }

  const parentKind = PARENT_KIND[level.id];
  const childKind = LEVEL_CARD_KIND[level.id];

  if (parentKind === null) {
    return [];
  }

  const parents = parentCandidates(cards, parentKind);
  const children = cards.filter((card) => card.kind === childKind);
  const parentIds = new Set(parents.map((card) => card.id));

  const lanes: SwimlaneModel[] = parents.map((parent) => ({
    id: parent.id,
    parentId: parent.id,
    title: parent.title,
    // У карточки-родителя хранится ключ палитры, свимлайн рисуется оттенком.
    color: directionColorHex(parent.color),
    cards: children.filter((card) => card.parentId === parent.id),
  }));

  const orphaned = children.filter((card) => card.parentId === null || !parentIds.has(card.parentId));

  if (orphaned.length > 0) {
    lanes.push({
      id: '__no_parent__',
      parentId: null,
      title: childKind === 'epic' ? 'Без инициативы' : 'Без проекта',
      // Свимлайн «без родителя» — это и есть служебный цвет c0.
      color: directionColorHex(UNPARENTED_CARD_COLOR),
      cards: orphaned,
    });
  }

  return lanes;
}

/** Следующий свободный id карточки: демо-состояние живёт только в памяти. */
function nextCardId(cards: readonly Card[]): string {
  const max = cards.reduce((acc, card) => {
    const numeric = Number.parseInt(card.id.replace(/\D/g, ''), 10);

    return Number.isNaN(numeric) ? acc : Math.max(acc, numeric);
  }, 0);

  return `card-${max + 1}`;
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
    // У стратегического уровня родитель формы — направление, его выбирают из списка.
    parentOptions: fromLane ? null : parentKind === null ? [...directions] : parentCandidates(cards, parentKind),
    allowNoParent: !fromLane && form.level === 'operational',
    columnLabel: columnLabel(form.column),
    columnId: form.column,
    levelLabel: level.label,
  };
}

export default function HomeScreen() {
  const { session, signOut } = useAuth();
  const { width } = useWindowDimensions();
  const compact = width < COMPACT_MAX_WIDTH;

  const [cards, setCards] = useState<Card[]>(() => [...INITIAL_CARDS]);
  // Направления — состояние экрана: доска пока не связана с API.
  const [directions, setDirections] = useState<Direction[]>(() => [...DIRECTIONS]);
  const [collapsedLevels, setCollapsedLevels] = useState<Record<string, boolean>>({});
  const [expandedColumns, setExpandedColumns] = useState<Record<string, boolean>>({});
  const [form, setForm] = useState<FormContext | null>(null);
  /** Карточка, открытая на редактирование; null — попап закрыт. */
  const [editedCardId, setEditedCardId] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  /** Уровень, у которого открыто меню «Настройки уровня»; null — меню закрыто. */
  const [settingsLevel, setSettingsLevel] = useState<LevelId | null>(null);
  /** true — открыт попап настроек направлений. */
  const [directionsOpen, setDirectionsOpen] = useState(false);

  const toggleLevel = (levelId: LevelId) => {
    setCollapsedLevels((prev) => ({ ...prev, [levelId]: !prev[levelId] }));
  };

  const toggleColumn = (key: string) => {
    setExpandedColumns((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  /** Следующий свободный id направления: демо-состояние живёт только в памяти. */
  const nextDirectionId = (items: readonly Direction[]): string => {
    const max = items.reduce((acc, item) => {
      const numeric = Number.parseInt(item.id.replace(/\D/g, ''), 10);

      return Number.isNaN(numeric) ? acc : Math.max(acc, numeric);
    }, 0);

    return `dir-${max + 1}`;
  };

  /** Название направления не должно повторяться: свимлайны на доске были бы неразличимы. */
  const directionTitleError = (items: readonly Direction[], title: string, exceptId: string | null): string | null => {
    const normalized = title.trim().toLowerCase();
    const duplicate = items.some((item) => item.id !== exceptId && item.title.trim().toLowerCase() === normalized);

    return duplicate ? 'Направление с таким названием уже есть.' : null;
  };

  /**
   * Цвет направления уникален: одинаковые цвета слили бы свимлайны и карточки.
   * В палитре занятые цвета уже недоступны, здесь — проверка на всякий случай.
   */
  const directionColorError = (items: readonly Direction[], color: string, exceptId: string | null): string | null =>
    items.some((item) => item.id !== exceptId && item.color === color)
      ? 'Этот цвет уже занят другим направлением.'
      : null;

  /** Добавить направление; возвращает текст ошибки для попапа или null. */
  const addDirection = (title: string, color: string): string | null => {
    const error = directionTitleError(directions, title, null) ?? directionColorError(directions, color, null);

    if (error !== null) {
      return error;
    }

    setDirections((prev) => [...prev, { id: nextDirectionId(prev), title, color }]);

    return null;
  };

  /**
   * Изменить направление.
   *
   * Смена цвета перекрашивает и карточки направления: цвет карточки — это цвет
   * её направления, иначе полоски карточек разъедутся с точкой свимлайна.
   */
  const updateDirection = (id: string, title: string, color: string): string | null => {
    const error = directionTitleError(directions, title, id) ?? directionColorError(directions, color, id);

    if (error !== null) {
      return error;
    }

    const current = directions.find((item) => item.id === id) ?? null;

    setDirections((prev) => prev.map((item) => (item.id === id ? { ...item, title, color } : item)));

    if (current !== null && current.color !== color) {
      setCards((prev) => recolorDirection(prev, id, color));
    }

    return null;
  };

  /** Удалить направление: правило проверяется в попапе, здесь — последний рубеж. */
  const removeDirection = (id: string) => {
    const direction = directions.find((item) => item.id === id) ?? null;

    if (direction === null || !checkDirectionDelete(cards, direction).allowed) {
      return;
    }

    setDirections((prev) => prev.filter((item) => item.id !== id));
  };

  /** Действие над колонками: выполняем, если правило разрешает, иначе показываем причину. */
  const handleCardAction = (card: Card) => {
    const action = cardAction(cards, card);

    if (action === null) {
      return;
    }

    if (action.blockReason === null) {
      setCards((prev) => updateCard(prev, card.id, { title: card.title, column: action.target }));
      setNotice(null);
      return;
    }

    setNotice(action.blockReason);

    // На web Alert не работает, там текст показывается баннером над доской.
    if (Platform.OS !== 'web') {
      Alert.alert('Перенос запрещён', action.blockReason);
    }
  };

  /** Форма из свимлайна: родитель предопределён. */
  const openLaneForm = (level: BoardLevelModel, parentId: string | null, column: ColumnId) => {
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

  /** Создание карточки: цвет и родитель берутся из выбранной в форме связи. */
  const createCard = (cardDraft: CardDraft, title: string, parentId: string | null) => {
    // Родитель инициативы — направление, у остальных уровней — карточка.
    const parent =
      directions.find((direction) => direction.id === parentId) ?? cards.find((card) => card.id === parentId) ?? null;

    setCards((prev) => [
      ...prev,
      {
        id: nextCardId(prev),
        kind: cardDraft.kind,
        title,
        column: cardDraft.columnId,
        // Карточка без родителя получает служебный нейтральный цвет c0.
        color: parent ? parent.color : UNPARENTED_CARD_COLOR,
        parentId: parent ? parent.id : null,
      },
    ]);
    setForm(null);
  };

  /**
   * Изменение карточки: название и статус, если правило смены статуса выполнено.
   * Ошибку возвращаем модалке — она показывается внутри попапа, а не над доской.
   */
  const saveCard = (card: Card, title: string, column: ColumnId): string | null => {
    const check = checkColumnChange(cards, card, column);

    if (!check.allowed) {
      // Форма блокирует статусы сама, сюда попадаем только при гонке данных.
      return check.reason;
    }

    setCards((prev) => updateCard(prev, card.id, { title, column }));
    setEditedCardId(null);
    setNotice(null);

    return null;
  };

  const draft = buildDraft(form, cards, directions);

  const editedCard = editedCardId === null ? null : (cards.find((card) => card.id === editedCardId) ?? null);
  const editedCardLockedReason =
    editedCard === null ? null : (checkColumnChange(cards, editedCard, editedCard.column).reason ?? null);

  const handleSignOut = async () => {
    await signOut();
  };

  return (
    <View className="flex-1 bg-surface">
      <ScrollView className="flex-1" contentContainerClassName="gap-3 px-3 py-3">
        <View className="flex-row items-center gap-3 rounded-2xl bg-card px-4 py-3">
          <View className="flex-1 gap-0.5">
            <Text className="text-base font-bold text-app">Канбан-доска</Text>
            <Text className="text-xs text-muted" numberOfLines={1}>
              {session?.user?.email ?? '—'}
            </Text>
          </View>

          <Pressable
            accessibilityRole="button"
            onPress={() => void handleSignOut()}
            className="rounded-xl bg-app px-3 py-2 active:opacity-90"
          >
            <Text className="text-xs font-semibold text-white">Выйти</Text>
          </Pressable>
        </View>

        {notice ? <FormAlert message={notice} /> : null}

        {LEVELS.map((level) => {
          const lanes = swimlanesOf(cards, directions, level);
          const parentKind = PARENT_KIND[level.id];
          // На стратегическом родитель (направление) всегда есть; ниже нужен хотя бы
          // один родитель в работе, иначе выбрать его в форме не из чего.
          const canCreate = parentKind === null || parentCandidates(cards, parentKind).length > 0;

          return (
            <BoardLevel
              key={level.id}
              level={level}
              lanes={lanes}
              collapsed={collapsedLevels[level.id] === true}
              onToggle={() => toggleLevel(level.id)}
              compact={compact}
              width={width}
              expandedColumns={expandedColumns}
              onToggleColumn={toggleColumn}
              cardAction={(card) => cardAction(cards, card)}
              onCardAction={handleCardAction}
              onOpenCard={(card) => setEditedCardId(card.id)}
              onAddCard={(parentId, columnId) => openLaneForm(level, parentId, columnId)}
              onCreateInLevel={() => openLevelForm(level)}
              createLabel={LEVEL_CREATE_LABEL[level.id]}
              canCreate={canCreate}
              createHint={canCreate ? null : LEVEL_CREATE_HINT[level.id]}
              onOpenSettings={() => setSettingsLevel(level.id)}
            />
          );
        })}

        <Text className="px-1 pb-2 text-center text-[11px] text-subtle">Демо-данные: доска ещё не связана с API</Text>
      </ScrollView>

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
        onAdd={addDirection}
        onUpdate={updateDirection}
        onDelete={removeDirection}
        deleteReason={(direction) => checkDirectionDelete(cards, direction).reason}
        onClose={() => setDirectionsOpen(false)}
      />

      <CardFormModal draft={draft} onCreate={createCard} onClose={() => setForm(null)} />

      <CardEditModal
        card={editedCard}
        lockedReason={editedCardLockedReason}
        onSave={saveCard}
        onClose={() => setEditedCardId(null)}
      />
    </View>
  );
}
