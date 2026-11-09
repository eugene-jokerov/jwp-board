/**
 * Кнопка «Показать ещё» под списком закрытой колонки.
 *
 * Закрытые колонки («Готово», «Отмена») грузятся порциями: первичная загрузка
 * отдаёт несколько карточек пары, остальные приходят по этой кнопке шагами
 * CLOSED_PAGE_SIZE. Пока запрос в полёте, кнопка заблокирована и показывает
 * индикатор — повторный клик не должен дублировать страницу.
 *
 * `pointer-events-auto` обязателен: в широкой раскладке кнопка живёт внутри
 * слоя закрытых колонок, а он прозрачен для кликов (`pointer-events-none`,
 * см. CLOSED_COLUMN_SLOT в BoardLevel.tsx). На native свойство не используется.
 */

import { ActivityIndicator, Pressable, Text, View } from 'react-native';

interface ShowMoreButtonProps {
  onPress: () => void;
  /** true — страница уже грузится. */
  loading: boolean;
  /** Текст ошибки последней догрузки; null — ошибки нет. */
  error: string | null;
}

export function ShowMoreButton({ onPress, loading, error }: ShowMoreButtonProps) {
  return (
    <View className="gap-1.5">
      <Pressable
        accessibilityRole="button"
        accessibilityLabel="Показать ещё карточки"
        accessibilityState={{ disabled: loading, busy: loading }}
        disabled={loading}
        onPress={onPress}
        className="pointer-events-auto flex-row items-center justify-center gap-2 rounded-xl border border-dashed border-subtle px-3 py-2 active:bg-card"
      >
        {loading ? <ActivityIndicator size="small" color="#6b7280" /> : null}

        <Text className="text-xs font-medium text-muted">{loading ? 'Загружаем…' : 'Показать ещё'}</Text>
      </Pressable>

      {/* Ошибку показываем у самой кнопки: повторный клик по ней и есть повтор
          запроса, искать другой способ перезагрузить ленту не нужно. */}
      {error === null ? null : <Text className="text-[11px] text-err-fg">{error}</Text>}
    </View>
  );
}
