/**
 * Поле дедлайна с календарём.
 *
 * Своя сетка дней на NativeWind, а не `@react-native-community/datetimepicker`:
 * у системного пикера нет web-версии, а фронт один (native + web). Внешне поле
 * повторяет `TextField`: подпись, рамка, плейсхолдер и текст ошибки.
 *
 * Наружу значение отдаётся в формате API — `YYYY-MM-DD` или null, — поэтому
 * формы не знают про объекты Date и часовые пояса. Выбранный день не
 * применяется сразу: сначала его можно поменять в календаре, а «Готово»
 * записывает результат в поле.
 */

import { useState } from 'react';
import { Modal, Pressable, Text, View } from 'react-native';

import { CalendarIcon } from './BoardIcons';
import { dateToDeadlineDate, deadlineDateToDate, formatDeadlineDate, todayDeadlineDate } from '../deadline';

interface DatePickerFieldProps {
  label: string;
  /** Текущее значение в формате API (`YYYY-MM-DD`); null — дата не задана. */
  value: string | null;
  onChange: (value: string | null) => void;
  error?: string | null;
  placeholder?: string;
}

/** Подписи месяцев в именительном падеже — их использует шапка календаря. */
const MONTHS: readonly string[] = [
  'Январь',
  'Февраль',
  'Март',
  'Апрель',
  'Май',
  'Июнь',
  'Июль',
  'Август',
  'Сентябрь',
  'Октябрь',
  'Ноябрь',
  'Декабрь',
];

/** Подписи дней недели, начиная с понедельника. */
const WEEKDAYS: readonly string[] = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

/**
 * Ячейки месяца: 6 недель по 7 дней, чтобы высота календаря не прыгала.
 * null — место до первого числа и после последнего.
 */
function monthCells(year: number, month: number): (number | null)[] {
  const firstWeekday = (new Date(year, month, 1, 12).getDay() + 6) % 7;
  const daysInMonth = new Date(year, month + 1, 0, 12).getDate();
  const cells: (number | null)[] = [...Array<null>(firstWeekday).fill(null)];

  for (let day = 1; day <= daysInMonth; day += 1) {
    cells.push(day);
  }

  while (cells.length < 42) {
    cells.push(null);
  }

  return cells;
}

interface CalendarProps {
  /** Выбранный день в формате API; null — ничего не выбрано. */
  selected: string | null;
  onSelect: (value: string) => void;
}

export function DeadlineCalendar({ selected, onSelect }: CalendarProps) {
  // Месяц на экране: начинаем с выбранного дня, иначе с сегодняшнего.
  const [view, setView] = useState(() => deadlineDateToDate(selected ?? todayDeadlineDate()));
  const today = todayDeadlineDate();
  const year = view.getFullYear();
  const month = view.getMonth();

  const shiftMonth = (delta: number) => {
    setView(new Date(year, month + delta, 1, 12));
  };

  return (
    <View className="gap-2">
      <View className="flex-row items-center justify-between">
        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Предыдущий месяц"
          onPress={() => shiftMonth(-1)}
          hitSlop={8}
          className="h-9 w-9 items-center justify-center rounded-lg active:bg-surface"
        >
          <Text className="text-base text-app">‹</Text>
        </Pressable>

        <Text className="text-sm font-semibold text-app">
          {MONTHS[month]} {year}
        </Text>

        <Pressable
          accessibilityRole="button"
          accessibilityLabel="Следующий месяц"
          onPress={() => shiftMonth(1)}
          hitSlop={8}
          className="h-9 w-9 items-center justify-center rounded-lg active:bg-surface"
        >
          <Text className="text-base text-app">›</Text>
        </Pressable>
      </View>

      <View className="flex-row">
        {WEEKDAYS.map((weekday) => (
          <View key={weekday} className="flex-1 items-center py-1">
            <Text className="text-[11px] font-medium text-subtle">{weekday}</Text>
          </View>
        ))}
      </View>

      {[0, 1, 2, 3, 4, 5].map((week) => (
        <View key={week} className="flex-row">
          {monthCells(year, month)
            .slice(week * 7, week * 7 + 7)
            .map((day, index) => {
              const value = day === null ? null : dateToDeadlineDate(new Date(year, month, day, 12));
              const isSelected = value !== null && value === selected;
              const isToday = value !== null && value === today;

              return (
                <View key={`${week}-${index}`} className="flex-1 items-center p-0.5">
                  {value === null || day === null ? (
                    <View className="h-9 w-full" />
                  ) : (
                    <Pressable
                      accessibilityRole="button"
                      accessibilityLabel={`${day} ${MONTHS[month]} ${year}`}
                      accessibilityState={{ selected: isSelected }}
                      onPress={() => onSelect(value)}
                      className={[
                        'h-9 w-full items-center justify-center rounded-lg',
                        isSelected ? 'bg-app' : isToday ? 'bg-surface' : 'active:bg-surface',
                      ].join(' ')}
                    >
                      <Text
                        className={[
                          'text-sm',
                          isSelected ? 'font-semibold text-white' : isToday ? 'font-semibold text-app' : 'text-app',
                        ].join(' ')}
                      >
                        {day}
                      </Text>
                    </Pressable>
                  )}
                </View>
              );
            })}
        </View>
      ))}
    </View>
  );
}

export function DatePickerField({ label, value, onChange, error, placeholder }: DatePickerFieldProps) {
  /** Черновик выбора: null — в календаре ничего не выбрано (как и в поле). */
  const [draft, setDraft] = useState<string | null>(value);
  const [open, setOpen] = useState(false);

  const openPicker = () => {
    setDraft(value);
    setOpen(true);
  };

  const confirm = () => {
    onChange(draft);
    setOpen(false);
  };

  return (
    <View className="gap-1.5">
      <Text className="text-sm font-medium text-app">{label}</Text>

      <Pressable
        accessibilityRole="button"
        accessibilityLabel={`${label}: открыть календарь`}
        onPress={openPicker}
        className={[
          'h-12 flex-row items-center justify-between gap-2 rounded-xl border bg-card px-4 active:opacity-80',
          error ? 'border-err-fg' : 'border-black/10',
        ].join(' ')}
      >
        <Text className={value === null ? 'text-base text-subtle' : 'text-base text-app'}>
          {formatDeadlineDate(value) ?? placeholder ?? 'Выберите дату'}
        </Text>

        <CalendarIcon />
      </Pressable>

      {error ? <Text className="text-xs text-err-fg">{error}</Text> : null}

      <Modal visible={open} transparent animationType="fade" onRequestClose={() => setOpen(false)}>
        <View className="flex-1 items-center justify-center bg-black/40 px-4">
          <View className="w-full max-w-md gap-3 rounded-2xl bg-card p-5">
            <Text className="text-lg font-bold text-app">Дедлайн</Text>

            <DeadlineCalendar selected={draft} onSelect={setDraft} />

            <View className="flex-row gap-2">
              <Pressable
                accessibilityRole="button"
                onPress={() => setDraft(null)}
                className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
              >
                <Text className="text-sm font-semibold text-app">Очистить</Text>
              </Pressable>

              <Pressable
                accessibilityRole="button"
                onPress={() => setOpen(false)}
                className="h-11 flex-1 items-center justify-center rounded-xl bg-surface active:opacity-80"
              >
                <Text className="text-sm font-semibold text-app">Отмена</Text>
              </Pressable>

              <Pressable
                accessibilityRole="button"
                onPress={confirm}
                className="h-11 flex-1 items-center justify-center rounded-xl bg-app active:opacity-90"
              >
                <Text className="text-sm font-semibold text-white">Готово</Text>
              </Pressable>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}
