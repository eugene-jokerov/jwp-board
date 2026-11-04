/**
 * Карточка доски: белая плитка в рамке, полоса цвета родителя в левом боку,
 * название и стрелки перевода между колонками.
 *
 * Цвет наследуется по иерархии (направление → инициатива → эпик) и живёт только
 * в полосе слева: заливки у карточки нет, рамка отделяет её от колонки.
 *
 * Стрелки блокируются на краях доски и тогда, когда у карточки есть зависимости
 * в работе (правило проверяет board/derive.ts и передаёт готовый результат).
 * По нажатию заблокированной стрелки вызываем onBlocked с текстом причины —
 * на web Alert не работает, поэтому решение «где показать» принимает экран.
 *
 * Клик по самой карточке (кроме стрелок) открывает форму редактирования.
 */

import { Pressable, Text, View } from 'react-native';

import { NEUTRAL_COLOR } from '../colors';
import type { MoveDirection } from '../types';

interface BoardCardProps {
  title: string;
  color: string | null;
  canMovePrev: boolean;
  canMoveNext: boolean;
  onMove: (direction: MoveDirection) => void;
  onBlocked: (direction: MoveDirection) => void;
  /** Открыть карточку на редактирование. */
  onPress: () => void;
}

export function BoardCard({ title, color, canMovePrev, canMoveNext, onMove, onBlocked, onPress }: BoardCardProps) {
  const handlePress = (direction: MoveDirection, allowed: boolean) => {
    if (allowed) {
      onMove(direction);
      return;
    }

    onBlocked(direction);
  };

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`Открыть карточку «${title}»`}
      onPress={onPress}
      className="flex-row overflow-hidden rounded border border-card-line bg-card active:bg-lane"
    >
      {/* Полоса цвета родителя — единственный цветной акцент карточки. */}
      <View className="w-1" style={{ backgroundColor: color ?? NEUTRAL_COLOR }} />

      <View className="flex-1 gap-1.5 px-2.5 py-2">
        <Text className="text-sm font-medium leading-5 text-ink" numberOfLines={3}>
          {title}
        </Text>

        <View className="flex-row items-center justify-between gap-2">
          <Text className="flex-1 text-[11px] text-muted" numberOfLines={1}>
            {color === null ? 'Без эпика' : ''}
          </Text>

          <View className="flex-row items-center gap-0.5">
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
      </View>
    </Pressable>
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
      className={['h-6 w-6 items-center justify-center rounded', enabled ? 'active:bg-lane' : 'bg-transparent'].join(
        ' ',
      )}
    >
      <Text className={enabled ? 'text-sm font-semibold text-muted' : 'text-sm font-semibold text-subtle opacity-40'}>
        {label}
      </Text>
    </Pressable>
  );
}
