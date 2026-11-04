/**
 * Текстовое поле формы на NativeWind.
 *
 * Оборачивает TextInput, чтобы `className` работал и на web, и на нативной
 * платформе, и показывает сообщение об ошибке под полем.
 */

import { Text, TextInput, View, type TextInputProps } from 'react-native';

interface TextFieldProps extends TextInputProps {
  label: string;
  error?: string | null;
}

export function TextField({ label, error, ...inputProps }: TextFieldProps) {
  return (
    <View className="gap-1.5">
      <Text className="text-sm font-medium text-app">{label}</Text>

      <TextInput
        className={[
          'h-12 rounded-xl border bg-card px-4 text-base text-app',
          error ? 'border-err-fg' : 'border-black/10',
        ].join(' ')}
        placeholderTextColor="#9ca3af"
        {...inputProps}
      />

      {error ? <Text className="text-xs text-err-fg">{error}</Text> : null}
    </View>
  );
}
