/**
 * Сообщение об ошибке над формой (общее для всех полей).
 *
 * Сейчас используется только экраном входа и регистрации (`app/auth.tsx`):
 * на доске все ошибки показываются всплывающими уведомлениями
 * (`src/notifications`), включая ошибки попапов карточки и направлений.
 */

import { Text, View } from 'react-native';

export function FormAlert({ message }: { message: string }) {
  return (
    <View className="rounded-xl bg-err-bg px-4 py-3">
      <Text className="text-sm text-err-fg">{message}</Text>
    </View>
  );
}
