/**
 * Иконки доски правого края заголовка колонки.
 *
 * Библиотек иконок в проекте нет (`@expo/vector-icons`, `react-native-svg`
 * не установлены), поэтому иконки рисуются примитивами `View`: они одинаково
 * работают на native и на web и красятся цветовыми токенами Tailwind.
 */

import { View } from 'react-native';

/** Три точки в столбик — «меню колонки». */
export function MenuIcon() {
  return (
    <View className="items-center justify-center gap-[3px]">
      <View className="h-[3px] w-[3px] rounded-full bg-muted" />
      <View className="h-[3px] w-[3px] rounded-full bg-muted" />
      <View className="h-[3px] w-[3px] rounded-full bg-muted" />
    </View>
  );
}

/** Шеврон влево — «свернуть колонку» (квадрат с гранями снизу и слева). */
export function ChevronLeftIcon() {
  return <View className="h-2.5 w-2.5 rotate-45 border-b border-l border-muted" />;
}

/** Шеврон вправо — «развернуть колонку» (квадрат с гранями сверху и справа). */
export function ChevronRightIcon() {
  return <View className="h-2.5 w-2.5 rotate-45 border-r border-t border-muted" />;
}
