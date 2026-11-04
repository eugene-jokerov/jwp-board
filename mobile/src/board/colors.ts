/**
 * Визуальные токены доски.
 *
 * Цвета направлений/инициатив/эпиков живут в данных и применяются inline-стилем
 * (динамические классы Tailwind не собираются), поэтому общие оттенки собраны
 * здесь, чтобы литералы не расползались по компонентам.
 */

import type { LevelId } from './types';

/** Фон пустых зон и карточек без цвета. */
export const EMPTY_COLOR = '#eef0f3';

/** Полоса-заглушка у карточки без цвета. */
export const NEUTRAL_COLOR = '#cbd5e1';

/** Метки уровней. */
export const LEVEL_COLORS: Record<LevelId, string> = {
  strategic: '#6366f1',
  tactical: '#0ea5e9',
  operational: '#f59e0b',
};

/**
 * Полупрозрачный фон карточки: цвета родителей глухие, а карточка должна быть мягкой.
 * Восьмизначный hex с альфой поддерживают и native, и web.
 */
export function cardTint(color: string | null): string {
  return color === null ? EMPTY_COLOR : `${color}1f`;
}
