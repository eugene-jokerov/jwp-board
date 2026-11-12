/**
 * Контекст всплывающих уведомлений (тостов).
 *
 * Показывает ответы сервера: ошибку переноса или догрузки ленты, отказ сервера
 * в форме карточки и направлений. Визуальный слой — `NotificationHost`
 * (подключён в `app/_layout.tsx` рядом с этим провайдером), поэтому любой экран
 * вызывает `useNotifications()` и не думает о том, где на экране и на какой
 * платформе это будет нарисовано.
 *
 * Сообщения, которые считаются на клиенте и не зависят от ответа сервера
 * (режим «только чтение», запрет переноса по иерархии, причины недоступных
 * кнопок), тостами не показываются: это постоянные подписи — баннер или
 * строка рядом с элементом (`FormAlert`, `ReadOnlyBanner`). Исключение —
 * экран входа и регистрации (`app/auth.tsx`): там ошибки полей остаются в
 * самой форме.
 *
 * Правила стека:
 *  - одновременно не больше MAX_VISIBLE уведомлений: новые вытесняют старые;
 *  - уведомление с тем же ключом (по умолчанию — тот же текст) не дублируется:
 *    плашка обновляет текст и получает новый срок жизни;
 *  - каждое уведомление живёт `duration` мс и закрывается само; крестик в тосте
 *    закрывает его раньше;
 *  - `duration: 'sticky'` — не закрывается по таймеру: так показываются ошибки
 *    открытой формы, они должны висеть, пока пользователь правит данные.
 */

import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';

/** Вид уведомления: задаёт цвет и иконку в `NotificationHost`. */
export type NotificationKind = 'error' | 'warning' | 'info' | 'success';

/** Уведомление в стеке. */
export interface AppNotification {
  /** Уникальный id: нужен как React-ключ и для снятия с экрана. */
  id: number;
  /**
   * Логический ключ, под которым уведомление показывается.
   *
   * По нему уведомление и снимается (`dismissKey`): форма карточки просит
   * показать ошибку под своим ключом и закрывает её, когда ошибка исправлена —
   * без хранения id на стороне формы.
   */
  key: string;
  kind: NotificationKind;
  /** Заголовок одной строкой; без него тост — просто текст сообщения. */
  title?: string;
  message: string;
  /** Время жизни в миллисекундах; null — висит, пока не закроют. */
  duration: number | null;
}

/** Что можно передать вместе с текстом: заголовок, время жизни и ключ. */
export interface NotificationOptions {
  title?: string;
  /**
   * `'sticky'` — не скрывать по таймеру. Так показываются ошибки открытой
   * формы: они должны висеть, пока пользователь правит данные, а не исчезать
   * через несколько секунд.
   */
  duration?: number | 'sticky';
  /** Ключ для показа вместо предыдущего уведомления с тем же ключом. */
  key?: string;
}

/** Ключ по умолчанию: текст самого сообщения (одинаковые тексты не дублируются). */
const DEFAULT_KEY_PREFIX = 'message:';

interface NotificationContextValue {
  /** Уведомления в порядке появления (старые выше). */
  notifications: readonly AppNotification[];
  /** Показать уведомление произвольного вида. */
  notify: (kind: NotificationKind, message: string, options?: NotificationOptions) => void;
  /** Ошибка: сбой действия или запрет от сервера. */
  notifyError: (message: string, options?: NotificationOptions) => void;
  /** Предупреждение: например, запрет на действие, которое клиент отклонил сам. */
  notifyWarning: (message: string, options?: NotificationOptions) => void;
  /** Снять уведомление с экрана досрочно (крестик, клик по тосту). */
  dismiss: (id: number) => void;
  /** Снять уведомление по его ключу: форма убирает свою ошибку после исправления. */
  dismissKey: (key: string) => void;
}

/**
 * Сколько уведомлений держать на экране одновременно.
 *
 * Больше трёх плашек на телефоне уже закрывают доску, а очередь из сообщений
 * одного действия пользователю всё равно не прочитать.
 */
const MAX_VISIBLE = 3;

/** Время показа: у ошибки текст бывает длинным, ей даём больше времени. */
const DEFAULT_DURATION: Record<NotificationKind, number> = {
  error: 7000,
  warning: 6000,
  info: 5000,
  success: 4000,
};

const NotificationContext = createContext<NotificationContextValue | null>(null);

export function NotificationProvider({ children }: { children: ReactNode }) {
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  /** Таймеры автоскрытия по id: они не влияют на рендер, поэтому живут в ref. */
  const timers = useRef(new Map<number, ReturnType<typeof setTimeout>>());
  /** Счётчик id; `useRef`, а не состояние — новый id не должен вызывать рендер. */
  const nextId = useRef(1);

  /** Снять уведомление с экрана и погасить его таймер. */
  const dismiss = useCallback((id: number) => {
    const timer = timers.current.get(id);

    if (timer !== undefined) {
      clearTimeout(timer);
      timers.current.delete(id);
    }

    setNotifications((prev) => prev.filter((item) => item.id !== id));
  }, []);

  /** Подписать уведомление на автоскрытие; прежний таймер (если был) заменяется. */
  const scheduleDismiss = useCallback(
    (id: number, duration: number | null) => {
      const timer = timers.current.get(id);

      if (timer !== undefined) {
        clearTimeout(timer);
        timers.current.delete(id);
      }

      // `sticky`-уведомление живёт до явного закрытия.
      if (duration === null) {
        return;
      }

      timers.current.set(
        id,
        setTimeout(() => dismiss(id), duration),
      );
    },
    [dismiss],
  );

  /** Снять уведомления по ключу (форме нужно убрать свою ошибку после правки). */
  const dismissKey = useCallback((key: string) => {
    setNotifications((prev) => {
      const removed = prev.filter((item) => item.key === key);

      removed.forEach((item) => {
        const timer = timers.current.get(item.id);

        if (timer !== undefined) {
          clearTimeout(timer);
          timers.current.delete(item.id);
        }
      });

      return removed.length === 0 ? prev : prev.filter((item) => item.key !== key);
    });
  }, []);

  /**
   * Показать уведомление.
   *
   * Уведомление с тем же ключом (по умолчанию — тот же текст) не дублируется:
   * существующая плашка просто обновляет текст и получает новый срок жизни.
   * Иначе двойной клик по заблокированной кнопке давал бы две копии одного
   * сообщения, а форма — десяток одинаковых ошибок при каждой правке поля.
   */
  const notify = useCallback(
    (kind: NotificationKind, message: string, options?: NotificationOptions) => {
      const duration = options?.duration === 'sticky' ? null : (options?.duration ?? DEFAULT_DURATION[kind]);
      const key = options?.key ?? `${DEFAULT_KEY_PREFIX}${message}`;
      let shownId: number | null = null;

      setNotifications((prev) => {
        const existing = prev.find((item) => item.key === key);

        if (existing !== undefined) {
          shownId = existing.id;

          // Тот же ключ — обновляем содержимое на месте, не меняя порядок стека.
          return prev.map((item) =>
            item.key === key ? { ...item, kind, message, title: options?.title, duration } : item,
          );
        }

        const created: AppNotification = { id: nextId.current, key, kind, title: options?.title, message, duration };

        nextId.current += 1;
        shownId = created.id;

        // Стек ограничен: самые старые уходят, новое встаёт в низ стека.
        return [...prev, created].slice(-MAX_VISIBLE);
      });

      // Обновление очереди выполняется синхронно, поэтому `shownId` здесь уже
      // известен: уведомление на экране (обновлённое или новое).
      if (shownId !== null) {
        scheduleDismiss(shownId, duration);
      }
    },
    [scheduleDismiss],
  );

  const notifyError = useCallback(
    (message: string, options?: NotificationOptions) => notify('error', message, options),
    [notify],
  );

  const notifyWarning = useCallback(
    (message: string, options?: NotificationOptions) => notify('warning', message, options),
    [notify],
  );

  // Уход провайдера с экрана — гасим все таймеры, чтобы они не сработали по
  // уже размонтированному состоянию.
  useEffect(
    () => () => {
      timers.current.forEach((timer) => clearTimeout(timer));
      timers.current.clear();
    },
    [],
  );

  const value = useMemo<NotificationContextValue>(
    () => ({ notifications, notify, notifyError, notifyWarning, dismiss, dismissKey }),
    [notifications, notify, notifyError, notifyWarning, dismiss, dismissKey],
  );

  return <NotificationContext.Provider value={value}>{children}</NotificationContext.Provider>;
}

/** Доступ к уведомлениям; вызывается только внутри `NotificationProvider`. */
export function useNotifications(): NotificationContextValue {
  const value = useContext(NotificationContext);

  if (value === null) {
    throw new Error('useNotifications используется вне NotificationProvider');
  }

  return value;
}
