@props([
    // Заголовок документа: он же <h1>, он же <title>.
    'title',
    // Описание для поисковых систем; null — общий текст сайта.
    'description' => null,
    // Вариант разметки: legal — документ в рамке (юридические страницы),
    // page — свободная страница (лендинг).
    'variant' => 'legal',
])

@php
    $metaDescription = $description ?? 'JWP Board — персональный канбан для личных целей.';

    // Дата редакции: ISO из конфига превращается в «11 ноября 2026».
    // Некорректная дата не ломает страницу — выводится пометкой, чтобы
    // документ не получил неверную дату редакции.
    try {
        $effectiveDateLabel = \Illuminate\Support\Carbon::parse(config('legal.effective_date'))->translatedFormat('j F Y');
    } catch (\Throwable) {
        $effectiveDateLabel = 'не указана';
    }
    $version = config('legal.version');
    $appUrl = rtrim((string) config('legal.app_url'), '/');
@endphp
<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title }} — JWP Board</title>
    <meta name="description" content="{{ $metaDescription }}" />
    <meta name="robots" content="index, follow" />
    @if ($variant === 'legal')
      <link rel="stylesheet" href="/css/legal.css" />
    @endif
    {{ $head ?? '' }}
  </head>
  <body class="{{ $variant === 'legal' ? 'layout-legal' : 'layout-page' }}">
    @if ($variant === 'legal')
      <header class="site-header">
        <div class="site-header__inner">
          <a class="brand" href="/">JWP Board<small>Персональный канбан</small></a>
          <a class="site-header__back" href="/">← На главную</a>
        </div>
      </header>

      <main class="wrap">
        <h1>{{ $title }}</h1>
        <p class="meta">Редакция {{ $version }} от {{ $effectiveDateLabel }} г. Действует для сервиса JWP Board — {{ $appUrl }}.</p>

        <article class="document">
          {{ $slot }}
        </article>
      </main>

      <x-legal.footer :current="$title" />
    @else
      {{ $slot }}
    @endif
  </body>
</html>
