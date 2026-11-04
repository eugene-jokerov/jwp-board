/**
 * Карточка доски: цветная полоса, название и стрелки перевода между колонками.
 *
 * Стрелки блокируются на краях доски и тогда, когда у карточки есть зависимости
 * в работе (правило проверяет board/derive.ts и передаёт готовый результат).
 * По нажатию заблокированной стрелки вызываем onBlocked с текстом причины —
 * на web Alert не работает, поэтому решение «где показать» принимает экран.
 */

import { Pressable, Text, View } from 'react-native';

import { cardTint, NEUTRAL_COLOR } from '../colors';
import type { MoveDirection } from '../types';

interface BoardCardProps {
  title: string;
  color: string | null;
  canMovePrev: boolean;
  canMoveNext: boolean;
  onMove: (direction: MoveDirection) => void;
  onBlocked: (direction: MoveDirection) => void;
}

export function BoardCard({ title, color, canMovePrev, canMoveNext, onMove, onBlocked }: BoardCardProps) {
  const handlePress = (direction: MoveDirection, allowed: boolean) => {
    if (allowed) {
      onMove(direction);
      return;
    }

    onBlocked(direction);
  };

  return (
    <View
      className="overflow-hidden rounded-xl border border-border bg-card"
      style={{ backgroundColor: cardTint(color) }}
    >
      <View className="flex-row">
        <View className="w-1.5" style={{ backgroundColor: color ?? NEUTRAL_COLOR }} />

        <View className="flex-1 gap-1 px-3 py-2.5">
          <Text className="text-sm font-medium text-app" numberOfLines={3}>
            {title}
          </Text>

          {color === null ? <Text className="text-[11px] text-subtle">Без эпика</Text> : null}
        </View>
      </View>

      <View className="flex-row items-center justify-between border-t border-border px-1.5 py-1">
        <ArrowButton
          label="←"
          accessibilityLabel={`Переместить «${title}» в предыдущую колонку`}
          enabled={canMovePrev}
          onPress={() => handlePress('prev', canMovePrev)}
        />

        <ArrowButton
          label="→"
          accessibilityLabel={`Переместить «${title}» в следующую колонку`}
          enabled={canMoveNext}
          onPress={() => handlePress('next', canMoveNext)}
        />
      </View>
    </View>
  );
}

interface ArrowButtonProps {
  label: string;
  accessibilityLabel: string;
  enabled: boolean;
  onPress: () => void;
}

function ArrowButton({ label, accessibilityLabel, enabled, onPress }: ArrowButtonProps) {
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={accessibilityLabel}
      accessibilityState={{ disabled: !enabled }}
      onPress={onPress}
      hitSlop={6}
      className={[
        'h-7 w-9 items-center justify-center rounded-lg',
        enabled ? 'bg-card active:bg-surface' : 'bg-transparent',
      ].join(' ')}
    >
      <Text className={enabled ? 'text-base font-semibold text-app' : 'text-base font-semibold text-subtle opacity-60'}>
        {label}
      </Text>
    </Pressable>
  );
}
