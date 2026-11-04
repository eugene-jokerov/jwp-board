/** @type {import('tailwindcss').Config} */
// Цветовые токены повторяют палитру из src/styles/home.ts (заглушка экрана).
module.exports = {
  content: ['./app/**/*.{js,jsx,ts,tsx}', './src/**/*.{js,jsx,ts,tsx}'],
  presets: [require('nativewind/preset')],
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
      },
    },
  },
  plugins: [],
};
