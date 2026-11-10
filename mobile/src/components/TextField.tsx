/**
 * Текстовое поле формы на NativeWind.
 *
 * Оборачивает TextInput, чтобы `className` работал и на web, и на нативной
 * платформе, и показывает сообщение об ошибке под полем. С `multiline`
 * однострочная высота не подставляется: высоту и выравнивание текста задаёт
 * вызывающий экран (описание карточки — несколько строк).
 */

import { Text, TextInput, View, type TextInputProps } from 'react-native';

interface TextFieldProps extends TextInputProps {
  label: string;
  error?: string | null;
}

export function TextField({ label, error, multiline, style, ...inputProps }: TextFieldProps) {
  return (
    <View className="gap-1.5">
      <Text className="text-sm font-medium text-app">{label}</Text>

      <TextInput
        className={[
          'rounded-xl border bg-card px-4 text-base text-app',
          multiline ? 'min-h-24 py-3' : 'h-12',
          error ? 'border-err-fg' : 'border-black/10',
        ].join(' ')}
        // Android по умолчанию выравнивает текст многострочного поля по центру.
        style={multiline ? [{ textAlignVertical: 'top' as const }, style] : style}
        multiline={multiline}
        placeholderTextColor="#9ca3af"
        {...inputProps}
      />

      {error ? <Text className="text-xs text-err-fg">{error}</Text> : null}
    </View>
  );
}
