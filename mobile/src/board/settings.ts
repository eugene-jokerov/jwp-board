/**
 * Настройки доски, которые переживают перезагрузку страницы.
 *
 * Свёрнутые уровни и свёрнутые колонки — это состояние интерфейса, но
 * пользователь ждёт, что оно сохранится: при следующем визите доска должна
 * открыться в том же виде. Источник правды — сервер (UserMeta, пары
 * «ключ — значение»), локальное состояние нужно только для мгновенной реакции
 * на клик.
 *
 * Схема работы:
 *  - при монтировании читаем `GET /user/settings` и раскладываем значения по ключам;
 *  - переключатель обновляет состояние сразу (оптимистично) и ставит ключ в очередь;
 *  - очередь уходит одним `PUT` с дебаунсом: серия кликов не превращается в серию запросов;
 *  - отказ сервера (в режиме «только чтение» — 403) показывается тостом, а локальное
 *    состояние остаётся переключённым: раскладка живёт до конца сессии, а
 *    пользователь знает, что она не сохранилась.
 */

import { useCallback, useEffect, useRef, useState } from 'react';

import { fetchUserSettings, updateUserSettings } from '../api/client';
import type { UserSettingsInput } from '../api/types';
import { getToken } from '../auth/session';
import { useNotifications } from '../notifications/NotificationContext';
import { errorMessage } from './store';
import type { ColumnId, LevelId } from './types';

/** Ключ настройки «свёрнутые уровни»: `{ strategic: true }`. */
export const COLLAPSED_LEVELS_KEY = 'board.collapsed_levels';

/** Ключ настройки «свёрнутые колонки»: `{ strategic: { backlog: true } }`. */
export const COLLAPSED_COLUMNS_KEY = 'board.collapsed_columns';

/** Задержка перед сохранением: серия переключений уходит одним запросом. */
const SAVE_DEBOUNCE_MS = 500;

/** Свёрнутые уровни: ключ — id уровня, true — свёрнут. */
export type CollapsedLevels = Record<string, boolean>;

/** Свёрнутые колонки: уровень → (колонка → свёрнута). */
export type CollapsedColumnsByLevel = Record<string, Record<string, boolean>>;

export interface BoardSettings {
  collapsedLevels: CollapsedLevels;
  collapsedColumns: CollapsedColumnsByLevel;
}

export interface BoardSettingsState extends BoardSettings {
  /** true — настройки ещё не загружены; до этого момента переключатели не сохраняются. */
  isLoading: boolean;
  /** Свернуть или развернуть уровень. */
  toggleLevel: (levelId: LevelId) => void;
  /** Свернуть или развернуть колонку внутри уровня. */
  toggleColumn: (levelId: LevelId, columnId: ColumnId) => void;
}

/**
 * Проверка формы значения «id → флаг».
 *
 * Сервер хранит то, что положил клиент, поэтому ответ читается защитно:
 * мусор в настройке не должен ломать раскладку доски.
 */
function toFlags(raw: unknown): Record<string, boolean> {
  if (typeof raw !== 'object' || raw === null || Array.isArray(raw)) {
    return {};
  }

  const flags: Record<string, boolean> = {};

  for (const [key, value] of Object.entries(raw)) {
    if (value === true) {
      flags[key] = true;
    }
  }

  return flags;
}

/** Значение «уровень → свёрнутые колонки» из ответа сервера. */
function toColumnsByLevel(raw: unknown): CollapsedColumnsByLevel {
  if (typeof raw !== 'object' || raw === null || Array.isArray(raw)) {
    return {};
  }

  const byLevel: CollapsedColumnsByLevel = {};

  for (const [levelId, value] of Object.entries(raw)) {
    const columns = toFlags(value);

    if (Object.keys(columns).length > 0) {
      byLevel[levelId] = columns;
    }
  }

  return byLevel;
}

/** Раскладка настроек из пар «ключ — значение». */
function parseSettings(entries: readonly { key: string; value: unknown }[]): BoardSettings {
  let collapsedLevels: CollapsedLevels = {};
  let collapsedColumns: CollapsedColumnsByLevel = {};

  for (const entry of entries) {
    if (entry.key === COLLAPSED_LEVELS_KEY) {
      collapsedLevels = toFlags(entry.value);
    }

    if (entry.key === COLLAPSED_COLUMNS_KEY) {
      collapsedColumns = toColumnsByLevel(entry.value);
    }
  }

  return { collapsedLevels, collapsedColumns };
}

export function useBoardSettings(): BoardSettingsState {
  const [collapsedLevels, setCollapsedLevels] = useState<CollapsedLevels>({});
  const [collapsedColumns, setCollapsedColumns] = useState<CollapsedColumnsByLevel>({});
  const [isLoading, setIsLoading] = useState(true);
  /** Отказ сервера на сохранение настроек показывается тостом: это ответ сервера. */
  const { notifyError } = useNotifications();

  /**
   * Очередь изменённых настроек: ключ → значение.
   *
   * Хранится в ref, а не в состоянии: сохранение не должно вызывать
   * перерисовку, а отправка не зависит от рендера.
   */
  const pending = useRef<UserSettingsInput>({});

  /** До окончания загрузки изменения не отправляются — иначе затёрли бы серверные. */
  const isLoaded = useRef(false);

  /** Таймер отложенного сохранения. Живёт вне состояния: перезапуск дешевле. */
  const saveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

  /** Отправить накопленную очередь одним запросом. */
  const flush = useCallback(async () => {
    const payload = pending.current;
    pending.current = {};

    if (Object.keys(payload).length === 0) {
      return;
    }

    try {
      await updateUserSettings(getToken(), payload);
    } catch (error) {
      // Отказ сервера (в режиме «только чтение» — 403) показываем тостом: иначе
      // пользователь думает, что раскладка сохранилась, а после перезагрузки
      // видит прежнюю. Ключ один, поэтому серия переключений не плодит плашки.
      // Локальное состояние при этом остаётся прежним: доска не «разворачивается»
      // на глазах у пользователя.
      notifyError(errorMessage(error, 'Раскладка доски не сохранилась.').message, {
        title: 'Не удалось сохранить раскладку',
        key: 'board-settings',
      });
    }
  }, [notifyError]);

  /** Поставить сохранение с дебаунсом: серия кликов уйдёт одним запросом. */
  const scheduleSave = useCallback(() => {
    if (!isLoaded.current) {
      return;
    }

    if (saveTimer.current !== null) {
      clearTimeout(saveTimer.current);
    }

    saveTimer.current = setTimeout(() => {
      saveTimer.current = null;
      void flush();
    }, SAVE_DEBOUNCE_MS);
  }, [flush]);

  /**
   * Сохранить очередь при размонтировании экрана.
   *
   * Пользователь может свернуть уровень и сразу закрыть доску: дебаунс ещё
   * не истёк, таймер снимается — и последние переключения потерялись бы.
   * Поэтому на размонтировании отправляем накопленное сразу.
   */
  useEffect(
    () => () => {
      if (saveTimer.current !== null) {
        clearTimeout(saveTimer.current);
        saveTimer.current = null;
      }

      void flush();
    },
    [flush],
  );

  useEffect(() => {
    let cancelled = false;

    const load = async () => {
      try {
        const entries = await fetchUserSettings(getToken());

        if (!cancelled) {
          const parsed = parseSettings(entries);

          setCollapsedLevels(parsed.collapsedLevels);
          setCollapsedColumns(parsed.collapsedColumns);
        }
      } catch (error) {
        // Без настроек доска откроется в виде по умолчанию.
        if (__DEV__) {
          console.warn('Не удалось загрузить настройки доски', error);
        }
      } finally {
        if (!cancelled) {
          isLoaded.current = true;
          setIsLoading(false);
        }
      }
    };

    void load();

    return () => {
      cancelled = true;
    };
  }, []);

  const toggleLevel = useCallback<BoardSettingsState['toggleLevel']>(
    (levelId) => {
      const next = { ...collapsedLevels };

      // Отсутствие ключа — «развёрнут»: храним только свёрнутые состояния.
      if (next[levelId] === true) {
        delete next[levelId];
      } else {
        next[levelId] = true;
      }

      setCollapsedLevels(next);
      pending.current = { ...pending.current, [COLLAPSED_LEVELS_KEY]: next };
      scheduleSave();
    },
    [collapsedLevels, scheduleSave],
  );

  const toggleColumn = useCallback<BoardSettingsState['toggleColumn']>(
    (levelId, columnId) => {
      const levelColumns = { ...(collapsedColumns[levelId] ?? {}) };

      if (levelColumns[columnId] === true) {
        delete levelColumns[columnId];
      } else {
        levelColumns[columnId] = true;
      }

      const next = { ...collapsedColumns };

      // Уровень без свёрнутых колонок не храним: «нет записи» и «ничего не свёрнуто» — одно.
      if (Object.keys(levelColumns).length === 0) {
        delete next[levelId];
      } else {
        next[levelId] = levelColumns;
      }

      setCollapsedColumns(next);
      pending.current = { ...pending.current, [COLLAPSED_COLUMNS_KEY]: next };
      scheduleSave();
    },
    [collapsedColumns, scheduleSave],
  );

  return { collapsedLevels, collapsedColumns, isLoading, toggleLevel, toggleColumn };
}
