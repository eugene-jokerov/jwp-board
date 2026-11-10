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
 * Клик по названию карточки открывает форму редактирования. Кликабельность
 * включена явно (`pointer-events-auto`): карточки закрытых колонок лежат в слое
 * с `pointer-events-none`, см. комментарий у корневого `View`.
 * Признак «без проекта» — отсутствие родителя, а не отсутствие цвета:
 * у такой карточки цвет служебный (нейтральный c0).
 *
 * В нижней строке слева стоит дедлайн без времени в виде «до ДД.ММ.ГГГГ», если
 * он задан: срок виден прямо на доске, не открывая карточку. За ним — подпись
 * «Без проекта» / «Без инициативы»; справа остаётся кнопка действия.
 * Если срок прошёл, карточка получает лёгкую красную подложку, а сама дата
 * красится в цвет ошибки — просрочка видна и без чтения даты.
 *
 * В закрытых колонках («Готово», «Отмена») срок не выводится и просрочка не
 * подсвечивается: дедлайн там потерял смысл — работа уже завершена или
 * отменена, и красная плитка читалась бы как «просрочено» без причины.
 * Признак колонки передаёт вызывающий компонент (`closedColumn`).
 */

import { Pressable, Text, View } from 'react-native';

import { CalendarIcon, CheckIcon, PlayIcon } from './BoardIcons';
import { formatDeadlineDate, isDeadlineOverdue } from '../deadline';
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
  /** Срок карточки строкой ISO-8601; null — срока нет. */
  deadline: string | null;
  /**
   * Карточка лежит в закрытой колонке («Готово», «Отмена»): срок не выводим
   * и просрочку не подсвечиваем — см. комментарий в шапке компонента.
   */
  closedColumn: boolean;
  /** Действие над колонками; null — в этой колонке действий нет. */
  action: CardAction | null;
  /** Выполнить действие (перевод в следующую колонку). */
  onAction: () => void;
  /** Открыть карточку на редактирование. */
  onPress: () => void;
}

export function BoardCard({
  title,
  color,
  hasParent,
  kind,
  deadline,
  closedColumn,
  action,
  onAction,
  onPress,
}: BoardCardProps) {
  // У карточки без родителя подпись говорит, чего именно ей не хватает.
  const withoutParentLabel = hasParent ? '' : kind === 'project' ? 'Без инициативы' : 'Без проекта';
  // Срок рисуется без времени: для дедлайна важен сам день. В закрытых
  // колонках срок не показываем — он теряет смысл после завершения работы.
  const deadlineLabel = closedColumn ? null : formatDeadlineDate(deadline);
  // Просроченная карточка подсвечивается: срок прошёл, а работа ещё не закрыта.
  // В закрытых колонках подсветки нет — иначе завершённая задача выглядела бы
  // проблемной из-за давно прошедшего срока.
  const overdue = !closedColumn && isDeadlineOverdue(deadline);

  return (
    // `pointer-events-auto` возвращает клики самой карточке. Карточки закрытых
    // колонок («Готово», «Отмена») лежат в позиционном слое, помеченном
    // `pointer-events-none` (см. CLOSED_COLUMN_SLOT в BoardLevel.tsx), а это
    // свойство наследуемое: без явного `auto` плитка была видна, но не ловила
    // наведение и клик на web. Пустые места слота при этом остаются прозрачными
    // для кликов. На native свойство не используется — там поведение прежнее.
    <View
      className={[
        'pointer-events-auto flex-row overflow-hidden rounded border',
        overdue ? 'border-err-fg/30 bg-card-overdue' : 'border-card-line bg-card',
      ].join(' ')}
    >
      {/* Полоса цвета родителя — единственный цветной акцент карточки.
          У карточки без родителя резолвер отдаёт служебный c0. */}
      <View className="w-1 shrink-0" style={{ backgroundColor: directionColorHex(color) }} />

      {/* `min-w-0` — длинное название не расширяет карточку: у флекс-элемента
          минимальная ширина по умолчанию равна содержимому, из-за чего карточка
          в узкой колонке вылезала за её отступы. Название обрезается по
          `numberOfLines`. */}
      <View className="min-w-0 flex-1 gap-1.5 px-2.5 py-2">
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
          {/* Левая часть нижней строки: срок карточки на левом краю, подпись
              «Без проекта» — следом, чтобы срок не уезжал к кнопке действия. */}
          <View className="min-w-0 flex-1 flex-row items-center gap-1.5">
            {deadlineLabel === null ? null : (
              /* «до» + дата: сразу понятно, что дата — это срок, а не отметка
                 создания. У просроченной карточки сама дата краснее подложки,
                 чтобы причина подсветки читалась без открытия карточки. */
              <View className="flex-row items-center gap-1">
                <CalendarIcon size={11} />

                <Text className="text-[11px] text-muted">до</Text>

                <Text
                  className={['text-[11px]', overdue ? 'font-semibold text-err-fg' : 'font-medium text-muted'].join(
                    ' ',
                  )}
                >
                  {deadlineLabel}
                </Text>
              </View>
            )}

            {withoutParentLabel === '' ? null : (
              <Text className="min-w-0 flex-1 text-[11px] text-muted" numberOfLines={1}>
                {withoutParentLabel}
              </Text>
            )}
          </View>

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
