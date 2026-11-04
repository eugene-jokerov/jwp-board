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
import { CardFormModal, type CardDraft } from '../src/board/components/CardFormModal';
import { DIRECTIONS, INITIAL_CARDS } from '../src/board/data';
import { checkMove, moveCard, parentCandidates } from '../src/board/derive';
import {
  COLUMNS,
  LEVELS,
  PARENT_KIND,
  type BoardLevel as BoardLevelModel,
  type Card,
  type CardKind,
  type ColumnId,
  type LevelId,
  type MoveDirection,
} from '../src/board/types';
import { FormAlert } from '../src/components/FormAlert';

/** Ниже этой ширины доска переключается в одноколоночный режим (телефон). */
const COMPACT_MAX_WIDTH = 900;

/** Колонка, в которую попадает карточка, созданная из шапки уровня. */
const LEVEL_CREATE_COLUMN: Record<LevelId, ColumnId> = {
  strategic: 'in_progress',
  tactical: 'backlog',
  operational: 'backlog',
};

const LEVEL_CREATE_LABEL: Record<LevelId, string> = {
  strategic: '+ Инициатива',
  tactical: '+ Эпик',
  operational: '+ Задача',
};

/** Текст под кнопкой уровня, если создать карточку нельзя. */
const LEVEL_CREATE_HINT: Record<LevelId, string> = {
  strategic: '',
  tactical: 'Нужна хотя бы одна инициатива в «В работе»',
  operational: 'Нужен хотя бы один эпик в «В работе»',
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
function swimlanesOf(cards: readonly Card[], level: BoardLevelModel): SwimlaneModel[] {
  if (level.id === 'strategic') {
    return DIRECTIONS.map((direction) => ({
      id: direction.id,
      parentId: direction.id,
      title: direction.title,
      color: direction.color,
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
    color: parent.color,
    cards: children.filter((card) => card.parentId === parent.id),
  }));

  const orphaned = children.filter((card) => card.parentId === null || !parentIds.has(card.parentId));

  if (orphaned.length > 0) {
    lanes.push({
      id: '__no_parent__',
      parentId: null,
      title: childKind === 'epic' ? 'Без инициативы' : 'Без эпика',
      color: null,
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
function buildDraft(form: FormContext | null, cards: readonly Card[]): CardDraft | null {
  if (form === null) {
    return null;
  }

  const level = LEVELS.find((item) => item.id === form.level) ?? LEVELS[0];
  const parentKind = PARENT_KIND[form.level];
  const parent = cards.find((card) => card.id === form.parentId) ?? null;
  const direction = DIRECTIONS.find((item) => item.id === form.parentId) ?? null;
  // Родитель из свимлайна: цвет известен сразу, выбирать ничего не нужно.
  const fromLane = parent !== null || direction !== null;

  return {
    kind: form.kind,
    laneId: fromLane ? form.parentId : null,
    laneColor: parent?.color ?? direction?.color ?? null,
    laneTitle: parent?.title ?? direction?.title ?? null,
    parentOptions: fromLane || parentKind === null ? null : parentCandidates(cards, parentKind),
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
  const [collapsedLevels, setCollapsedLevels] = useState<Record<string, boolean>>({});
  const [expandedColumns, setExpandedColumns] = useState<Record<string, boolean>>({});
  const [form, setForm] = useState<FormContext | null>(null);
  const [notice, setNotice] = useState<string | null>(null);

  const toggleLevel = (levelId: LevelId) => {
    setCollapsedLevels((prev) => ({ ...prev, [levelId]: !prev[levelId] }));
  };

  const toggleColumn = (key: string) => {
    setExpandedColumns((prev) => ({ ...prev, [key]: !prev[key] }));
  };

  /** Перенос карточки: либо переносим, либо показываем причину запрета. */
  const handleMove = (card: Card, direction: MoveDirection) => {
    const check = checkMove(cards, card, direction);

    if (check.allowed) {
      setCards((prev) => moveCard(prev, card.id, direction));
      setNotice(null);
      return;
    }

    if (!check.reason) {
      // Край доски — стрелка недоступна, объяснять нечего.
      return;
    }

    setNotice(check.reason);

    // На web Alert не работает, там текст показывается баннером над доской.
    if (Platform.OS !== 'web') {
      Alert.alert('Перенос запрещён', check.reason);
    }
  };

  const isCardMoveAllowed = (card: Card, direction: MoveDirection) => checkMove(cards, card, direction).allowed;

  /** Форма из свимлайна: родитель предопределён. */
  const openLaneForm = (level: BoardLevelModel, parentId: string | null, column: ColumnId) => {
    setForm({ level: level.id, kind: LEVEL_CARD_KIND[level.id], parentId, column });
  };

  /** Форма из шапки уровня: родителя выбирают вручную. */
  const openLevelForm = (level: BoardLevelModel) => {
    setForm({
      level: level.id,
      kind: LEVEL_CARD_KIND[level.id],
      parentId: null,
      column: LEVEL_CREATE_COLUMN[level.id],
    });
  };

  /** Создание карточки: цвет и родитель берутся из выбранной в форме связи. */
  const createCard = (cardDraft: CardDraft, title: string, parentId: string | null) => {
    const parent = parentId === null ? null : cards.find((card) => card.id === parentId);

    setCards((prev) => [
      ...prev,
      {
        id: nextCardId(prev),
        kind: cardDraft.kind,
        title,
        column: cardDraft.columnId,
        color: parent ? parent.color : null,
        parentId: parent ? parent.id : null,
      },
    ]);
    setForm(null);
  };

  const draft = buildDraft(form, cards);

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
              {session?.user.email ?? '—'}
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
          const lanes = swimlanesOf(cards, level);
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
              expandedColumns={expandedColumns}
              onToggleColumn={toggleColumn}
              isCardMoveAllowed={isCardMoveAllowed}
              onMoveCard={handleMove}
              onAddCard={(parentId, columnId) => openLaneForm(level, parentId, columnId)}
              onCreateInLevel={() => openLevelForm(level)}
              createLabel={LEVEL_CREATE_LABEL[level.id]}
              canCreate={canCreate}
              createHint={canCreate ? null : LEVEL_CREATE_HINT[level.id]}
            />
          );
        })}

        <Text className="px-1 pb-2 text-center text-[11px] text-subtle">Демо-данные: доска ещё не связана с API</Text>
      </ScrollView>

      <CardFormModal draft={draft} onCreate={createCard} onClose={() => setForm(null)} />
    </View>
  );
}
