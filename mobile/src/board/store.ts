/**
 * Состояние доски и все обращения к API.
 *
 * Экран не ходит в `client.ts` напрямую: он вызывает действия этого хука,
 * а тот хранит карточки и направления и подставляет токен сессии.
 *
 * Источник правды — сервер: после любого изменения в состояние кладётся ответ
 * API, а не локальная копия. Поэтому `color`, `allowed_transitions` и
 * унаследованные ссылки иерархии всегда актуальны, и клиенту не нужно
 * повторять доменные правила.
 *
 * Счётчики закрытых колонок («Готово», «Отмена») берутся из `meta.closed_totals`:
 * в этих колонках список приходит порцией, и по длине массива полное число
 * карточек не восстановить. Ответы одиночных мутаций `meta` не содержат, поэтому
 * счётчик пары правится локально на ту же единицу (см. `shiftClosedTotals`),
 * а первый следующий `reload()` снова берёт числа с сервера.
 */

import { useCallback, useEffect, useMemo, useState } from 'react';

import {
  ApiError,
  createDirection as createDirectionRequest,
  createTask as createTaskRequest,
  deleteDirection as deleteDirectionRequest,
  deleteTask as deleteTaskRequest,
  fetchDirections,
  fetchTasks,
  updateDirection as updateDirectionRequest,
  updateTask as updateTaskRequest,
} from '../api/client';
import type { TaskCreateInput, TaskUpdateInput } from '../api/types';
import { getToken } from '../auth/session';
import { shiftClosedTotals } from './derive';
import { parentFieldOf, toCard, toClosedTotals, toDirection } from './mappers';
import type { CardDraft } from './components/CardFormModal';
import type { Card, CardKind, ClosedTotals, ColumnId, Direction } from './types';

/** Текст ошибки для интерфейса: сообщение сервера, иначе — запасное. */
export function errorMessage(error: unknown, fallback: string): string {
  if (error instanceof ApiError) {
    const fieldError = error.payload.errors ? Object.values(error.payload.errors)[0]?.[0] : undefined;

    return fieldError ?? error.payload.message ?? fallback;
  }

  return fallback;
}

/** Действия над карточками; каждая возвращает текст ошибки сервера или null. */
interface CardActions {
  createCard: (draft: CardDraft, title: string, parentId: number | null) => Promise<string | null>;
  saveCard: (card: Card, title: string, column: ColumnId) => Promise<string | null>;
  moveCard: (card: Card, column: ColumnId) => Promise<string | null>;
  removeCard: (card: Card) => Promise<string | null>;
}

/** Действия над направлениями; каждая возвращает текст ошибки сервера или null. */
interface DirectionActions {
  addDirection: (title: string, color: string) => Promise<string | null>;
  editDirection: (id: number, title: string, color: string) => Promise<string | null>;
  removeDirection: (id: number) => Promise<string | null>;
}

export interface BoardState extends CardActions, DirectionActions {
  cards: Card[];
  directions: Direction[];
  /**
   * Полное число карточек закрытых колонок по парам «уровень + колонка» из
   * `meta.closed_totals`: в «Готово» и «Отмене» приходит лишь порция карточек,
   * поэтому счётчик берётся отсюда, а не из длины списка.
   */
  closedTotals: ClosedTotals;
  /** true — идёт первая загрузка доски. */
  isLoading: boolean;
  /** Ошибка загрузки (сеть, 401, 500); null — доска загружена. */
  loadError: string | null;
  /** Перечитать карточки и направления. */
  reload: () => Promise<void>;
}

/**
 * Ссылка на родителя при создании карточки.
 *
 * Уровень определяет поле: инициативе родитель — направление, проекту —
 * инициатива, задаче — проект. Карточка без родителя не отправляет ссылки вовсе.
 */
function parentPayload(kind: CardKind, parentId: number | null): Partial<TaskCreateInput> {
  switch (parentFieldOf(kind)) {
    case 'direction_id':
      return parentId === null ? {} : { direction_id: parentId };
    case 'initiative_id':
      return parentId === null ? {} : { initiative_id: parentId };
    case 'project_id':
      return parentId === null ? {} : { project_id: parentId };
  }
}

export function useBoard(): BoardState {
  const [cards, setCards] = useState<Card[]>([]);
  const [directions, setDirections] = useState<Direction[]>([]);
  const [closedTotals, setClosedTotals] = useState<ClosedTotals>({});
  const [isLoading, setIsLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);

  const reload = useCallback(async () => {
    const token = getToken();

    try {
      const [directionsPayload, tasksPayload] = await Promise.all([fetchDirections(token), fetchTasks(token)]);

      setDirections(directionsPayload.map(toDirection));
      setCards(tasksPayload.data.map(toCard));
      // Счётчики закрытых колонок — из `meta`: карточки «Готово» и «Отмены»
      // приходят порцией, и по длине списка полное число не восстановить.
      setClosedTotals(toClosedTotals(tasksPayload.meta));
      setLoadError(null);
    } catch (error) {
      setLoadError(errorMessage(error, 'Не удалось загрузить доску. Проверьте соединение с сервером.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    // Загрузка — асинхронный внешний вызов: состояние обновится после ответа API,
    // а не синхронным setState внутри эффекта.
    const request = Promise.resolve().then(() => reload());

    void request;
  }, [reload]);

  /** Заменить карточку ответом сервера; созданной карточки в списке ещё нет — добавляем. */
  const applyCard = useCallback((updated: Card) => {
    setCards((prev) => {
      const exists = prev.some((card) => card.id === updated.id);

      return exists ? prev.map((card) => (card.id === updated.id ? updated : card)) : [...prev, updated];
    });
  }, []);

  const createCard = useCallback<CardActions['createCard']>(
    async (draft, title, parentId) => {
      const input: TaskCreateInput = {
        type: draft.kind,
        column: draft.columnId,
        title,
        ...parentPayload(draft.kind, draft.laneId ?? parentId),
      };

      try {
        applyCard(toCard(await createTaskRequest(getToken(), input)));

        // Созданная карточка могла попасть в закрытую колонку (форма это
        // разрешает) — тогда её пара прибавляется и в счётчике.
        setClosedTotals((prev) => shiftClosedTotals(prev, draft.kind, draft.columnId, draft.columnId));

        return null;
      } catch (error) {
        // 422 — некорректная иерархия (например, родитель перестал быть в работе).
        return errorMessage(error, 'Не удалось создать карточку.');
      }
    },
    [applyCard],
  );

  const saveCard = useCallback<CardActions['saveCard']>(
    async (card, title, column) => {
      const input: TaskUpdateInput = { title };

      // Колонку отправляем только при смене: PATCH с текущей колонкой
      // всё равно проходит через правило перехода.
      if (column !== card.column) {
        input.column = column;
      }

      try {
        applyCard(toCard(await updateTaskRequest(getToken(), card.id, input)));

        // Карточка сменила колонку — переносим её между парами счётчика.
        setClosedTotals((prev) => shiftClosedTotals(prev, card.kind, card.column, input.column ?? card.column));

        return null;
      } catch (error) {
        // 409 — смену статуса блокируют дочерние карточки в работе.
        return errorMessage(error, 'Не удалось сохранить карточку.');
      }
    },
    [applyCard],
  );

  const moveCard = useCallback<CardActions['moveCard']>(
    async (card, column) => {
      try {
        applyCard(toCard(await updateTaskRequest(getToken(), card.id, { column })));
        setClosedTotals((prev) => shiftClosedTotals(prev, card.kind, card.column, column));

        return null;
      } catch (error) {
        // 409 — карточку нельзя перевести, пока у неё есть дети в работе.
        return errorMessage(error, 'Не удалось переместить карточку.');
      }
    },
    [applyCard],
  );

  const removeCard = useCallback<CardActions['removeCard']>(async (card) => {
    try {
      await deleteTaskRequest(getToken(), card.id);
      setCards((prev) => prev.filter((item) => item.id !== card.id));
      // Удалённая карточка могла быть в закрытой колонке — счётчик пары убывает.
      setClosedTotals((prev) => shiftClosedTotals(prev, card.kind, card.column, null));

      return null;
    } catch (error) {
      // 409 — у карточки есть дочерние карточки.
      return errorMessage(error, 'Не удалось удалить карточку.');
    }
  }, []);

  const addDirection = useCallback<DirectionActions['addDirection']>(async (title, color) => {
    try {
      const created = toDirection(await createDirectionRequest(getToken(), { title, color }));
      setDirections((prev) => [...prev, created]);

      return null;
    } catch (error) {
      // 422 — занятый цвет или недопустимое название.
      return errorMessage(error, 'Не удалось добавить направление.');
    }
  }, []);

  const editDirection = useCallback<DirectionActions['editDirection']>(
    async (id, title, color) => {
      try {
        const updated = toDirection(await updateDirectionRequest(getToken(), id, { title, color }));

        setDirections((prev) => prev.map((item) => (item.id === id ? updated : item)));

        // Новый цвет перекрашивает карточки каскадом на сервере: перечитываем доску.
        await reload();

        return null;
      } catch (error) {
        return errorMessage(error, 'Не удалось сохранить направление.');
      }
    },
    [reload],
  );

  const removeDirection = useCallback<DirectionActions['removeDirection']>(async (id) => {
    try {
      await deleteDirectionRequest(getToken(), id);
      setDirections((prev) => prev.filter((item) => item.id !== id));

      return null;
    } catch (error) {
      // 409 — в направлении есть карточки.
      return errorMessage(error, 'Не удалось удалить направление.');
    }
  }, []);

  return useMemo(
    () => ({
      cards,
      directions,
      closedTotals,
      isLoading,
      loadError,
      reload,
      createCard,
      saveCard,
      moveCard,
      removeCard,
      addDirection,
      editDirection,
      removeDirection,
    }),
    [
      cards,
      directions,
      closedTotals,
      isLoading,
      loadError,
      reload,
      createCard,
      saveCard,
      moveCard,
      removeCard,
      addDirection,
      editDirection,
      removeDirection,
    ],
  );
}
