{{-- Футер юридических страниц: те же ссылки на документы, что и на лендинге. --}}
@props([
    // Заголовок текущей страницы — он же подпись активной ссылки.
    'current' => null,
])

<footer>
  <nav aria-label="Правовая информация">
    <a href="/">Главная</a>
    <a href="/privacy" @if ($current === 'Политика обработки персональных данных') aria-current="page" @endif>Политика конфиденциальности</a>
    <a href="/terms" @if ($current === 'Пользовательское соглашение') aria-current="page" @endif>Пользовательское соглашение</a>
    <a href="/cookies" @if ($current === 'Политика использования файлов cookie') aria-current="page" @endif>Cookie</a>
  </nav>
  <div>© {{ now()->format('Y') }} JWP Board — персональный канбан.</div>
</footer>
