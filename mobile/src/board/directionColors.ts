/**
 * Палитра цветов направлений.
 *
 * Цвета направлений — доменное ограничение, а не оформление: выбор ограничен
 * фиксированным списком, чтобы карточки одного направления читались одинаково
 * на всех уровнях доски. Красного в списке нет: он зарезервирован под
 * блокировки, нарушение VIP-лимитов и ошибки.
 *
 * Порядок не случаен: сначала идут пять базовых цветов — они лучше всего
 * различимы друг от друга и при этом не спорят с красным; далее второстепенные,
 * выстроенные так, чтобы соседние в списке тоже контрастировали.
 * Все оттенки одной светлоты и насыщенности (ступень 500), поэтому цветная
 * точка свимлайна и полоски карточек выглядят ровно на белом фоне.
 *
 * В данных хранится не оттенок, а ключ палитры (`c1`, `c2`, …): это универсальные
 * значения для базы — перекрасить палитру или подправить оттенок можно, не
 * мигрируя сохранённые направления. HEX — слой отображения, его отдаёт
 * `directionColorHex`. Те же ключи и оттенки объявлены в Tailwind-теме как
 * `palette.c0…c19` (см. `tailwind.config.js` и `global.css`).
 *
 * Отдельно есть служебный ключ `c0` (нейтральный): его нельзя выбрать
 * направлению — он закреплён за карточками без родителя («Проект без
 * инициативы» и «Задача без проекта»). В палитру выбора `DIRECTION_COLORS` он
 * поэтому не входит: резолвер оттенка берёт его из `SHOWABLE_COLORS`.
 */

export interface DirectionColor {
  /** Универсальный ключ палитры: это значение хранится в базе данных. */
  key: string;
  /** CSS-переменная с оттенком: источник HEX для интерфейса. */
  hexVariable: string;
  /** Название цвета — для подсказки и доступности. */
  label: string;
}

export const DIRECTION_COLORS: readonly DirectionColor[] = [
  // Базовые: максимально различимые между собой.
  { key: 'c1', hexVariable: '--color-direction-c1', label: 'Синий' },
  { key: 'c2', hexVariable: '--color-direction-c2', label: 'Янтарный' },
  { key: 'c3', hexVariable: '--color-direction-c3', label: 'Зелёный' },
  { key: 'c4', hexVariable: '--color-direction-c4', label: 'Фиолетовый' },
  { key: 'c5', hexVariable: '--color-direction-c5', label: 'Бирюзовый' },
  // Второстепенные: ближе к соседним оттенкам, но всё ещё различимы.
  { key: 'c6', hexVariable: '--color-direction-c6', label: 'Розовый' },
  { key: 'c7', hexVariable: '--color-direction-c7', label: 'Лаймовый' },
  { key: 'c8', hexVariable: '--color-direction-c8', label: 'Индиго' },
  { key: 'c9', hexVariable: '--color-direction-c9', label: 'Оранжевый' },
  { key: 'c10', hexVariable: '--color-direction-c10', label: 'Изумрудный' },
  { key: 'c11', hexVariable: '--color-direction-c11', label: 'Фуксия' },
  { key: 'c12', hexVariable: '--color-direction-c12', label: 'Небесный' },
  { key: 'c13', hexVariable: '--color-direction-c13', label: 'Жёлтый' },
  { key: 'c14', hexVariable: '--color-direction-c14', label: 'Сине-серый' },
  { key: 'c15', hexVariable: '--color-direction-c15', label: 'Тёмно-лиловый' },
  { key: 'c16', hexVariable: '--color-direction-c16', label: 'Морской волны' },
  { key: 'c17', hexVariable: '--color-direction-c17', label: 'Медный' },
  { key: 'c18', hexVariable: '--color-direction-c18', label: 'Глубокий циан' },
  { key: 'c19', hexVariable: '--color-direction-c19', label: 'Малиновый' },
];

/**
 * Служебный нейтральный цвет карточек без родителя.
 *
 * Направлению его выбрать нельзя: `c0` закрепляется автоматически за
 * «Проектом без инициативы» и «Задачей без проекта», когда у карточки
 * нет родителя и, значит, нет направления.
 */
export const UNPARENTED_CARD_COLOR = 'c0';

/**
 * Все ключи, у которых есть оттенок: служебный c0 и палитра направлений.
 *
 * Палитра выбора направления — только `DIRECTION_COLORS`: служебный цвет
 * направлению не предлагается, но показать его (точка свимлайна, полоса
 * карточки) нужно, поэтому резолвер смотрит в этот расширенный список.
 */
const SHOWABLE_COLORS: readonly DirectionColor[] = [
  { key: UNPARENTED_CARD_COLOR, hexVariable: '--color-direction-c0', label: 'Нейтральный' },
  ...DIRECTION_COLORS,
];

/** Ключ цвета нового направления, пока пользователь не выбрал другой. */
export const DEFAULT_DIRECTION_COLOR: string = DIRECTION_COLORS[0].key;

/**
 * Первый свободный цвет палитры — для подстановки в форму создания.
 *
 * Цвет направления уникален, поэтому при открытии формы нельзя предлагать
 * уже занятый: берём самый первый свободный по порядку палитры. Если заняты
 * все двадцать, возвращаем цвет по умолчанию — форма покажет ошибку при выборе.
 */
export function firstFreeDirectionColor(usedColors: readonly string[]): string {
  const free = DIRECTION_COLORS.find((color) => !usedColors.includes(color.key));

  return free ? free.key : DEFAULT_DIRECTION_COLOR;
}

/**
 * Оттенок для интерфейса по ключу палитры.
 *
 * В данных живёт только ключ, поэтому любой показ цвета идёт через эту функцию.
 * Неизвестный ключ (например, из будущего API) красится служебным нейтральным
 * цветом, а не первым цветом палитры: иначе любая «дырка» в палитре даёт синие
 * свимлайны и карточки.
 */
export function directionColorHex(key: string | null): string | null {
  if (key === null) {
    return null;
  }

  const color = SHOWABLE_COLORS.find((item) => item.key === key);

  return HEX_BY_COLOR_KEY[color ? color.key : UNPARENTED_CARD_COLOR] ?? null;
}

/** Название цвета по ключу — для подсказок и доступности. */
export function directionColorLabel(key: string): string {
  return (SHOWABLE_COLORS.find((item) => item.key === key) ?? SHOWABLE_COLORS[0]).label;
}

/**
 * Оттенки палитры по ключам.
 *
 * Оттенки нужны inline-стилями (цветная точка свимлайна, полоска карточки,
 * палитра в настройках), поэтому живут здесь. Те же значения объявлены
 * переменными `--color-direction-cN` в `global.css` для Tailwind-классов
 * (`bg-palette-cN`): при изменении палитры правьте оба места.
 */
const HEX_BY_COLOR_KEY: Record<string, string> = {
  // Служебный нейтральный цвет карточек без родителя.
  [UNPARENTED_CARD_COLOR]: '#a8a29e',
  c1: '#3b82f6',
  c2: '#f59e0b',
  c3: '#22c55e',
  c4: '#8b5cf6',
  c5: '#06b6d4',
  c6: '#ec4899',
  c7: '#84cc16',
  c8: '#6366f1',
  c9: '#f97316',
  c10: '#10b981',
  c11: '#d946ef',
  c12: '#0ea5e9',
  c13: '#ca8a04',
  c14: '#64748b',
  c15: '#7c3aed',
  c16: '#14b8a6',
  c17: '#b45309',
  c18: '#0891b2',
  c19: '#db2777',
};
