/**
 * Главный экран: трёхуровневая канбан-доска.
 *
 * Данные приходят из backend API через `useBoard()` (src/board/store.ts):
 * карточки, направления и переходы между колонками сервер отдаёт готовыми.
 * Уровни сворачиваются в строку, колонки фиксированные. На телефоне колонки
 * внутри свимлайна раскладываются вертикально как аккордеон.
 */

import { useState } from 'react';
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
import { BoardLevel, type SwimlaneModel } from '../src/board/components/BoardLevel';
import { CardEditModal } from '../src/board/components/CardEditModal';
import { CardFormModal, type CardDraft } from '../src/board/components/CardFormModal';
import { DirectionsModal } from '../src/board/components/DirectionsModal';
import { LevelSettingsMenu } from '../src/board/components/LevelSettingsMenu';
import { UNPARENTED_CARD_COLOR, directionColorHex } from '../src/board/directionColors';
import { cardAction, checkDirectionDelete, parentCandidates } from '../src/board/derive';
import { parentOf } from '../src/board/mappers';
import { useBoard } from '../src/board/store';
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
      id: `direction-${direction.id}`,
      parentId: direction.id,
      title: direction.title,
      // У направления хранится ключ палитры, свимлайн рисуется оттенком.
      color: directionColorHex(direction.color),
      cards: cards.filter((card) => card.kind === 'initiative' && card.directionId === direction.id),
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
    title: parent.title,
    // У карточки-родителя хранится ключ палитры, свимлайн рисуется оттенком.
    color: directionColorHex(parent.color),
    // Дети свимлайна — карточки, у которых родитель именно эта карточка.
    cards: children.filter((card) => card.kind !== parent.kind && parentOf(card)?.id === parent.id),
  }));

  // Карточки без родителя и карточки, чей родитель вышел из работы,
  // на этом уровне показывать негде — их видно в свимлайне «без родителя».
  const inLane = new Set(lanes.flatMap((lane) => lane.cards.map((card) => card.id)));
  const orphaned = children.filter((card) => !inLane.has(card.id));

  if (orphaned.length > 0) {
    lanes.push({
      id: `no_parent-${level.id}`,
      parentId: null,
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

  // Доска: карточки, направления и все изменения живут в API (src/board/store.ts).
  const board = useBoard();
  const { cards, directions, isLoading, loadError, reload } = board;

  const [collapsedLevels, setCollapsedLevels] = useState<Record<string, boolean>>({});
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
          setNotice(error);
        }
      });

      return;
    }

    setNotice(action.blockReason);

    // На web Alert не работает, там текст показывается баннером над доской.
    if (Platform.OS !== 'web') {
      Alert.alert('Перенос запрещён', action.blockReason);
    }
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
   * Создание карточки: родителя выбирает форма, остальное (цвет, ссылки иерархии)
   * считает сервер, а модалке возвращается текст ошибки для показа внутри формы.
   */
  const createCard = async (cardDraft: CardDraft, title: string, parentId: number | null) => {
    const error = await board.createCard(cardDraft, title, parentId);

    if (error === null) {
      setForm(null);
      setNotice(null);
    }

    return error;
  };

  /**
   * Изменение карточки: название и статус. Ошибку возвращаем модалке — она
   * показывается внутри попапа, а не над доской.
   */
  const saveCard = async (card: Card, title: string, column: ColumnId) => {
    const error = await board.saveCard(card, title, column);

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

        {loadError !== null ? (
          <View className="gap-2 rounded-xl border border-border bg-card p-3">
            <FormAlert message={loadError} />

            <Pressable
              accessibilityRole="button"
              accessibilityState={{ disabled: isRefreshing }}
              disabled={isRefreshing}
              onPress={() => void reloadBoard()}
              className={[
                'h-10 items-center justify-center rounded-xl bg-app active:opacity-90',
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
              Добавьте направление: в настройках стратегического уровня, затем создайте инициативу.
            </Text>
          </View>
        ) : null}

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
              cardAction={cardAction}
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

        <Text className="px-1 pb-2 text-center text-[11px] text-subtle">
          Данные доски хранятся на сервере и общие для всех устройств
        </Text>
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
