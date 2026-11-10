/**
 * Дедлайн карточки: преобразование между датой интерфейса и значением API.
 *
 * Модуль без React — как `derive.ts`: функции вызываются и в формах, и в плитке
 * карточки. Формат обмена с сервером — `YYYY-MM-DD` (время не выбирается,
 * сервер хранит начало дня); в интерфейсе показываем `DD.MM.YYYY`.
 *
 * Строка ISO-8601 разбирается посимвольно, без `new Date`: сервер отдаёт дату
 * с часовым поясом, и `new Date(...)` в западном поясе сдвинул бы её на
 * предыдущий день. Для дедлайна день важен сам по себе, время у него —
 * служебное (00:00:00).
 */

/** Часть ISO-строки с датой: `YYYY-MM-DD`. */
const ISO_DATE = /^(\d{4})-(\d{2})-(\d{2})/;

/** Дата в формате API (`YYYY-MM-DD`) из ISO-строки; null — строки нет или она не распознана. */
export function toDeadlineDate(iso: string | null): string | null {
  if (iso === null) {
    return null;
  }

  const match = ISO_DATE.exec(iso.trim());

  return match === null ? null : `${match[1]}-${match[2]}-${match[3]}`;
}

/**
 * Дата для плитки карточки: `DD.MM.YYYY` без времени.
 * Ключ дня из ISO-строки — строковая операция, поэтому сдвига по поясу нет.
 */
export function formatDeadlineDate(iso: string | null): string | null {
  const date = toDeadlineDate(iso);

  return date === null ? null : `${date.slice(8, 10)}.${date.slice(5, 7)}.${date.slice(0, 4)}`;
}

/**
 * Текущая дата приложения в формате API (`YYYY-MM-DD`).
 * Берётся локальная дата устройства — её же пользователь видит в календаре.
 */
export function todayDeadlineDate(): string {
  return dateToDeadlineDate(new Date());
}

/** Дата JavaScript в формат API (`YYYY-MM-DD`) по локальным компонентам. */
export function dateToDeadlineDate(date: Date): string {
  const year = String(date.getFullYear()).padStart(4, '0');
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
}

/**
 * Дедлайн уже прошёл: день срока раньше сегодняшнего.
 *
 * Сравниваются строки формата `YYYY-MM-DD` — они лексикографически совпадают
 * с хронологическим порядком, поэтому часовой пояс и время в расчёт не входят.
 * Карточка со сроком «сегодня» не просрочена: до конца дня задача ещё в работе.
 */
export function isDeadlineOverdue(iso: string | null): boolean {
  const deadline = toDeadlineDate(iso);

  return deadline !== null && deadline < todayDeadlineDate();
}

/**
 * Дата JavaScript из значения формы (`YYYY-MM-DD`).
 * Время — полдень локального дня: полночь при пересчёте форматов даёт
 * соседний день в некоторых часовых поясах.
 */
export function deadlineDateToDate(value: string): Date {
  const [year, month, day] = value.split('-').map(Number);

  return new Date(year, month - 1, day, 12);
}
