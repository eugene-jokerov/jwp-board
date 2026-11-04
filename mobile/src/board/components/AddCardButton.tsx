/**
 * Пунктирная кнопка «+ …» под колонкой свимлайна.
 */

import { Pressable, Text } from 'react-native';

interface AddCardButtonProps {
  label: string;
  onPress: () => void;
  disabled?: boolean;
}

export function AddCardButton({ label, onPress, disabled = false }: AddCardButtonProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled }}
      disabled={disabled}
      onPress={onPress}
      className={[
        'items-center rounded-xl border border-dashed px-3 py-2',
        disabled ? 'border-border opacity-50' : 'border-subtle active:bg-card',
      ].join(' ')}
    >
      <Text className={['text-xs font-medium', disabled ? 'text-subtle' : 'text-muted'].join(' ')}>{label}</Text>
    </Pressable>
  );
}
