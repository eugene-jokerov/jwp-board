/**
 * Плашка «Режим только чтение».
 *
 * Показывается первым элементом над доской, когда учётная запись помечена
 * флагом `readonly`. Это единственное отличие доски от обычной: интерфейс не
 * гасится, а изменения, отправленные на сервер, он отклоняет (403), и отказ
 * приходит тостом или внутри формы.
 *
 * Место выбрано так, чтобы о режиме нельзя было не узнать ни на телефоне, ни в
 * веб-версии: плашка идёт выше содержимого доски и не сворачивается вместе с
 * уровнями. Текст объясняет не только ограничение, но и причину — так отказ
 * сервера не выглядит поломкой приложения.
 */

import { Lock } from 'lucide-react-native';
import { Text, View } from 'react-native';

interface ReadOnlyBannerProps {
  /** Дополнительное пояснение, например «данные общие для всех устройств». */
  hint?: string;
}

export function ReadOnlyBanner({ hint }: ReadOnlyBannerProps) {
  return (
    <View
      accessibilityRole="alert"
      className="flex-row items-start gap-2.5 rounded-xl border border-amber-300 bg-amber-50 px-3.5 py-3"
    >
      <Lock size={18} color="#b45309" />

      <View className="flex-1 gap-0.5">
        <Text className="text-sm font-semibold text-amber-900">Режим только для чтения</Text>
        <Text className="text-xs leading-relaxed text-amber-800">
          {hint ?? 'Создание, изменение и удаление недоступны. Данные доски можно только просматривать.'}
        </Text>
      </View>
    </View>
  );
}
