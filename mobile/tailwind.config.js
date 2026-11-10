/** @type {import('tailwindcss').Config} */
// Цветовые токены фирменной палитры: используются в компонентах через className
// (bg-surface, text-app, bg-err-bg, …).
module.exports = {
  content: ['./app/**/*.{js,jsx,ts,tsx}', './src/**/*.{js,jsx,ts,tsx}'],
  presets: [require('nativewind/preset')],
  // Тёмную тему переключаем классом: при darkMode: 'media' (значение по умолчанию)
  // react-native-css-interop на web падает с «Cannot manually set color scheme».
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        app: '#111827',
        surface: '#f5f6f8',
        card: '#ffffff',
        muted: '#6b7280',
        subtle: '#9ca3af',
        ok: { bg: '#dcfce7', fg: '#166534' },
        err: { bg: '#fee2e2', fg: '#991b1b' },
        // Лёгкая красная подложка просроченной карточки («дедлайн прошёл»):
        // различима на белой карточке, но мягче, чем err.bg у кнопок удаления —
        // тревога не должна глушить название задачи. Текст срока при этом
        // красится в err.fg: дата должна читаться как предупреждение.
        'card-overdue': '#fff1f2',

        // Токены доски: hairline-границы карточек и зон.
        // Цвета карточек (направление → инициатива → проект) живут в данных и
        // применяются inline-стилем — динамические классы Tailwind не собираются.
        border: '#e5e7eb',
        // Палитра доски в духе Jira (Atlassian Design System):
        // lane — фон колонки-«лунки», card-line — рамка карточки, ink — основной текст.
        lane: '#f1f2f4',
        'card-line': '#dfe1e6',
        ink: '#172b4d',

        // Палитра направлений: ключи c0…c19 — это универсальные значения,
        // которые хранятся в базе данных (c0 — служебный цвет карточек без родителя). Оттенки заданы CSS-переменными
        // в global.css (тем же файлом пользуется резолвер в src/board/directionColors.ts),
        // поэтому bg-palette-cN не дублирует HEX в разметке.
        palette: {
          c0: 'var(--color-direction-c0)',
          c1: 'var(--color-direction-c1)',
          c2: 'var(--color-direction-c2)',
          c3: 'var(--color-direction-c3)',
          c4: 'var(--color-direction-c4)',
          c5: 'var(--color-direction-c5)',
          c6: 'var(--color-direction-c6)',
          c7: 'var(--color-direction-c7)',
          c8: 'var(--color-direction-c8)',
          c9: 'var(--color-direction-c9)',
          c10: 'var(--color-direction-c10)',
          c11: 'var(--color-direction-c11)',
          c12: 'var(--color-direction-c12)',
          c13: 'var(--color-direction-c13)',
          c14: 'var(--color-direction-c14)',
          c15: 'var(--color-direction-c15)',
          c16: 'var(--color-direction-c16)',
          c17: 'var(--color-direction-c17)',
          c18: 'var(--color-direction-c18)',
          c19: 'var(--color-direction-c19)',
        },
      },
    },
  },
  plugins: [],
};
