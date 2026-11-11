/**
 * Показать причину, по которой действие сейчас недоступно.
 *
 * Так оформлены блокировки, у которых причина зависит от данных (направление с
 * карточками удалить нельзя, у карточки есть зависимости в работе): текст
 * целиком приходит от `derive.ts`, поэтому его не вписать в подпись иначе, как
 * отдельным сообщением. Мелкий серый текст под кнопкой легко пропустить, тост
 * же читается как предупреждение и снимается сам, когда блокировка уходит.
 */

import { useEffect } from 'react';

import { useNotifications } from './NotificationContext';

export function useUnavailableReasonToast(reason: string | null, key: string, title: string): void {
  const { notifyWarning, dismissKey } = useNotifications();

  useEffect(() => {
    if (reason === null) {
      dismissKey(key);

      return;
    }

    notifyWarning(reason, { title, duration: 'sticky', key });

    // `reason` — единственное, что должно перезапускать показ; функции из
    // контекста стабильны (`useCallback` в провайдере).
    const activeKey = key;

    return () => {
      dismissKey(activeKey);
    };
  }, [reason, key, title, notifyWarning, dismissKey]);
}
