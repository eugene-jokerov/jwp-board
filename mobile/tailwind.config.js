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
        // Токены доски: hairline-границы карточек и зон.
        // Цвета направлений/инициатив/эпиков живут в данных (src/board/data.ts)
        // и применяются inline-стилем — динамические классы Tailwind не собираются.
        border: '#e5e7eb',
        // Палитра доски в духе Jira (Atlassian Design System):
        // lane — фон колонки-«лунки», card-line — рамка карточки, ink — основной текст.
        lane: '#f1f2f4',
        'card-line': '#dfe1e6',
        ink: '#172b4d',
      },
    },
  },
  plugins: [],
};
