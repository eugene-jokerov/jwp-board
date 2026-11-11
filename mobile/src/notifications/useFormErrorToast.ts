/**
 * Ошибка формы, показываемая тостом.
 *
 * Формы доски (карточка, новое направление, редактирование направления) выводят
 * ошибки сервера одинаково:
 *  - ошибка, которая «принадлежит» полю, остаётся в `TextField` (`error` у поля):
 *    она должна стоять рядом с полем, а не улетать в угол экрана;
 *  - общая ошибка (валидация иерархии, лимит, запрет, сеть, 409 при удалении)
 *    показывается тостом;
 *  - тост ошибки формы «липкий»: он висит, пока пользователь правит данные, и
 *    снимается, как только данные изменили или форму закрыли. Иначе ошибка
 *    исчезла бы через несколько секунд, а пользователь так и не понял, почему
 *    сохранение не прошло.
 *
 * Ключ тоста уникален для формы (`formKey` + `slot`), поэтому ошибки разных
 * попапов не затирают друг друга, а повторное сохранение с той же ошибкой не
 * плодит копии.
 */

import { useCallback, useEffect, useRef } from 'react';

import { useNotifications } from '../notifications/NotificationContext';

interface FormErrorToastOptions {
  /** Стабильный ключ формы: например, `card-edit-42` или `direction-create`. */
  formKey: string;
  /** Заголовок тоста; по умолчанию — «Не удалось сохранить». */
  title?: string;
}

/** Слот ошибки в тосте: у формы их несколько (сохранение, удаление). */
type ErrorSlot = 'main' | 'delete';

interface FormErrorToast {
  /** Показать ошибку формы тостом. */
  show: (message: string, slot?: ErrorSlot) => void;
  /** Снять ошибку формы с экрана (правка поля, закрытие формы). */
  clear: (slot?: ErrorSlot) => void;
}

export function useFormErrorToast({ formKey, title = 'Не удалось сохранить' }: FormErrorToastOptions): FormErrorToast {
  const { notifyError, dismissKey } = useNotifications();
  /** Ключи текущих тостов — чтобы снять именно их, а не чужие. */
  const keys = useRef(new Map<ErrorSlot, string>());

  const keyOf = useCallback((slot: ErrorSlot) => `form:${formKey}:${slot}`, [formKey]);

  const show = useCallback(
    (message: string, slot: ErrorSlot = 'main') => {
      const key = keyOf(slot);

      keys.current.set(slot, key);
      notifyError(message, { title, duration: 'sticky', key });
    },
    [keyOf, notifyError, title],
  );

  const clear = useCallback(
    (slot: ErrorSlot = 'main') => {
      if (!keys.current.has(slot)) {
        return;
      }

      keys.current.delete(slot);
      dismissKey(keyOf(slot));
    },
    [dismissKey, keyOf],
  );

  // Форма ушла с экрана — её ошибки не должны висеть над доской.
  useEffect(() => {
    const activeKeys = keys.current;

    return () => {
      activeKeys.forEach((key) => dismissKey(key));
      activeKeys.clear();
    };
  }, [dismissKey]);

  return { show, clear };
}
