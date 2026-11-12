/**
 * Сообщение-подсказка над доской (общее для всех полей).
 *
 * Сейчас используется в двух местах: экраном входа и регистрации
 * (`app/auth.tsx`) для ошибки формы, а также главным экраном доски — для
 * сообщений, которые не зависят от ответа сервера (отказ в режиме «только
 * чтение», запрет переноса по иерархии). Ошибки сервера на доске показываются
 * всплывающими уведомлениями (`src/notifications`).
 */

import { Text, View } from 'react-native';

export function FormAlert({ message }: { message: string }) {
  return (
    <View className="rounded-xl bg-err-bg px-4 py-3">
      <Text className="text-sm text-err-fg">{message}</Text>
    </View>
  );
}
