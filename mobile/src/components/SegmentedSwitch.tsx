/**
 * Сегментированный переключатель режима формы (вход / регистрация).
 */

import { Pressable, Text, View } from 'react-native';

interface SegmentedSwitchProps<T extends string> {
  options: ReadonlyArray<{ value: T; label: string }>;
  value: T;
  onChange: (value: T) => void;
}

export function SegmentedSwitch<T extends string>({ options, value, onChange }: SegmentedSwitchProps<T>) {
  return (
    <View className="flex-row rounded-xl bg-surface p-1">
      {options.map((option) => {
        const isActive = option.value === value;

        return (
          <Pressable
            key={option.value}
            accessibilityRole="tab"
            accessibilityState={{ selected: isActive }}
            onPress={() => onChange(option.value)}
            className={[
              'flex-1 items-center justify-center rounded-lg py-2.5',
              isActive ? 'bg-card' : '',
            ].join(' ')}
          >
            <Text className={isActive ? 'text-sm font-semibold text-app' : 'text-sm text-muted'}>{option.label}</Text>
          </Pressable>
        );
      })}
    </View>
  );
}
