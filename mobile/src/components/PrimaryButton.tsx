/**
 * Основная кнопка действия формы.
 */

import { ActivityIndicator, Pressable, Text } from 'react-native';

interface PrimaryButtonProps {
  label: string;
  onPress: () => void;
  loading?: boolean;
  disabled?: boolean;
}

export function PrimaryButton({ label, onPress, loading = false, disabled = false }: PrimaryButtonProps) {
  const isDisabled = disabled || loading;

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ disabled: isDisabled, busy: loading }}
      disabled={isDisabled}
      onPress={onPress}
      className={[
        'h-12 flex-row items-center justify-center rounded-xl bg-app active:opacity-90',
        isDisabled ? 'opacity-60' : '',
      ].join(' ')}
    >
      {loading ? <ActivityIndicator color="#ffffff" /> : <Text className="text-base font-semibold text-white">{label}</Text>}
    </Pressable>
  );
}
