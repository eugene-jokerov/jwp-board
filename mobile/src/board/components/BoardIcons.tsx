/**
 * Иконки доски.
 *
 * Библиотек иконок до этого не было и значки рисовались гранями `View`
 * (шевроны, меню). Иконки действий взяты из Lucide (`lucide-react-native`,
 * SVG через `react-native-svg`): они векторные, линейные и одинаково
 * работают на native и на web.
 */

import { View } from 'react-native';

import { CircleCheck, Play, Settings } from 'lucide-react-native';

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

/** Цвет штриха иконок действия: иконки стоят на тёмной кнопке. */
const ACTION_ICON_COLOR = '#ffffff';

/** Иконка «Взять в работу»: play в круге. */
export function PlayIcon() {
  return <Play size={14} color={ACTION_ICON_COLOR} />;
}

/** Иконка «Завершить задачу»: галочка в круге. */
export function CheckIcon() {
  return <CircleCheck size={14} color={ACTION_ICON_COLOR} />;
}

/** Цвет служебных иконок, которые стоят на светлом фоне. */
const CONTROL_ICON_COLOR = '#6b7280';

/** Иконка «Настройки уровня»: шестерёнка в строке с кнопкой создания. */
export function SettingsIcon() {
  return <Settings size={14} color={CONTROL_ICON_COLOR} />;
}
