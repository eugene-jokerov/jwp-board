/**
 * Карточка доски: белая плитка в рамке, полоса цвета родителя в левом боку,
 * название и иконка действия над колонками.
 *
 * Цвет наследуется по иерархии (направление → инициатива → проект) и живёт только
 * в полосе слева: заливки у карточки нет, рамка отделяет её от колонки.
 *
 * Действие зависит от колонки: в «Беклоге» — «Взять в работу», в «В работе» —
 * «Завершить задачу», в «Готово» и «Отмене» иконки нет. Действие блокируется,
 * если у карточки есть зависимости в работе (правило проверяет board/derive.ts
 * и передаёт готовый результат). По нажатию заблокированной иконки вызываем
 * onBlocked с текстом причины — на web Alert не работает, поэтому решение
 * «где показать» принимает экран.
 *
 * Клик по названию карточки открывает форму редактирования.
 * Признак «без проекта» — отсутствие родителя, а не отсутствие цвета:
 * у такой карточки цвет служебный (нейтральный c0).
 */

import { Pressable, Text, View } from 'react-native';

import { CheckIcon, PlayIcon } from './BoardIcons';
import { NEUTRAL_COLOR } from '../colors';
import { directionColorHex } from '../directionColors';
import type { CardAction } from '../derive';
import type { CardKind } from '../types';

/** Иконка действия по целевой колонке: «В работе» — play, «Готово» — галочка. */
function ActionIcon({ target }: { target: CardAction['target'] }) {
  return target === 'complete' ? <CheckIcon /> : <PlayIcon />;
}

interface BoardCardProps {
  title: string;
  /** Цвет родителя ключом палитры; у карточки без родителя — служебный c0. */
  color: string | null;
  /** Есть ли у карточки родитель: если нет — под названием подпись «Без проекта». */
  hasParent: boolean;
  /** Тип карточки: у проекта без родителя подпись «Без инициативы». */
  kind: CardKind;
  /** Действие над колонками; null — в этой колонке действий нет. */
  action: CardAction | null;
  /** Выполнить действие (перевод в следующую колонку). */
  onAction: () => void;
  /** Открыть карточку на редактирование. */
  onPress: () => void;
}

export function BoardCard({ title, color, hasParent, kind, action, onAction, onPress }: BoardCardProps) {
  // У карточки без родителя подпись говорит, чего именно ей не хватает.
  const withoutParentLabel = hasParent ? '' : kind === 'project' ? 'Без инициативы' : 'Без проекта';

  return (
    <View className="flex-row overflow-hidden rounded border border-card-line bg-card">
      {/* Полоса цвета родителя — единственный цветной акцент карточки. */}
      <View className="w-1" style={{ backgroundColor: directionColorHex(color) ?? NEUTRAL_COLOR }} />

      <View className="flex-1 gap-1.5 px-2.5 py-2">
        {/* Открытие карточки — отдельная область над названием: кнопка-иконка
            действия стоит рядом, а не внутри, иначе на web получится
            <button> внутри <button>. */}
        <Pressable
          accessibilityRole="button"
          accessibilityLabel={`Открыть карточку «${title}»`}
          onPress={onPress}
          className="active:opacity-70"
        >
          <Text className="text-sm font-medium leading-5 text-ink" numberOfLines={3}>
            {title}
          </Text>
        </Pressable>

        <View className="flex-row items-center justify-between gap-2">
          <Text className="flex-1 text-[11px] text-muted" numberOfLines={1}>
            {withoutParentLabel}
          </Text>

          {action ? (
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={`${action.label}: «${title}»`}
              accessibilityState={{ disabled: action.blockReason !== null }}
              onPress={onAction}
              hitSlop={6}
              className={[
                'h-6 w-6 items-center justify-center rounded',
                action.blockReason === null ? 'bg-app active:opacity-90' : 'bg-border opacity-70',
              ].join(' ')}
            >
              <ActionIcon target={action.target} />
            </Pressable>
          ) : null}
        </View>
      </View>
    </View>
  );
}
