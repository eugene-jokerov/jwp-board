<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JWP Board — персональный канбан для личных целей</title>
    <meta
      name="description"
      content="JWP Board — персональный канбан: направления, инициативы, проекты и задачи на одном экране. Работа видна целиком, статусы меняются по правилам, доска одинаково открыта на всех устройствах."
    />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="JWP Board — персональный канбан" />
    <meta
      property="og:description"
      content="Три уровня вместо одного списка: направления, инициативы, проекты и задачи. Доска с правилами перехода и одной точкой правды для всех устройств."
    />
    <meta property="og:image" content="/images/board.png" />
    {{--
      Лендинг — Blade-шаблон (маршрут «/» в routes/web.php, Route::view).
      Сборки нет: стили инлайновые, палитра совпадает с mobile/tailwind.config.js
      (app/surface/card/muted/border/lane/ink). Год в футере берётся из now(),
      адрес демо — маршрут приложения /board/.
    --}}
    <style>
      :root {
        --color-app: #111827;
        --color-surface: #f5f6f8;
        --color-card: #ffffff;
        --color-muted: #6b7280;
        --color-subtle: #9ca3af;
        --color-border: #e5e7eb;
        --color-lane: #f1f2f4;
        --color-ink: #172b4d;
        --color-accent: #1f6feb;
        --color-accent-soft: #e8f0fe;
        --radius: 16px;
        --shadow: 0 1px 2px rgba(17, 24, 39, 0.06), 0 8px 24px rgba(17, 24, 39, 0.06);
      }

      * {
        box-sizing: border-box;
      }

      html {
        scroll-behavior: smooth;
      }

      body {
        margin: 0;
        background: var(--color-surface);
        color: var(--color-app);
        font-family:
          -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        font-size: 16px;
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
      }

      a {
        color: inherit;
      }

      a:focus-visible,
      button:focus-visible {
        outline: 2px solid var(--color-accent);
        outline-offset: 3px;
        border-radius: 6px;
      }

      .container {
        width: 100%;
        max-width: 1080px;
        margin: 0 auto;
        padding: 0 24px;
      }

      /* Шапка */
      .topbar {
        position: sticky;
        top: 0;
        z-index: 10;
        background: rgba(255, 255, 255, 0.92);
        backdrop-filter: saturate(180%) blur(8px);
        border-bottom: 1px solid var(--color-border);
      }

      .topbar__inner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        min-height: 64px;
      }

      .brand {
        display: block;
        font-size: 16px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        text-decoration: none;
      }

      .brand small {
        display: block;
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.02em;
        text-transform: none;
        color: var(--color-muted);
      }

      .topbar__nav {
        display: flex;
        align-items: center;
        gap: 20px;
      }

      .topbar__nav a:not(.button) {
        font-size: 14px;
        font-weight: 500;
        color: var(--color-muted);
        text-decoration: none;
      }

      .topbar__nav a:not(.button):hover {
        color: var(--color-app);
      }

      /* Кнопки */
      .button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 20px;
        border-radius: 10px;
        border: 1px solid var(--color-app);
        background: var(--color-app);
        color: #ffffff;
        font-size: 15px;
        font-weight: 600;
        text-decoration: none;
        transition:
          background-color 0.15s ease,
          transform 0.15s ease;
      }

      .button:hover {
        background: #1f2937;
      }

      .button:active {
        transform: translateY(1px);
      }

      .button--secondary {
        background: transparent;
        border-color: var(--color-border);
        color: var(--color-app);
      }

      .button--secondary:hover {
        background: var(--color-card);
      }

      .button--small {
        padding: 9px 16px;
        font-size: 14px;
      }

      /* Hero */
      .hero {
        padding: 64px 0 40px;
      }

      .eyebrow {
        display: inline-block;
        margin-bottom: 16px;
        padding: 5px 12px;
        border-radius: 999px;
        background: var(--color-accent-soft);
        color: #1a4fb4;
        font-size: 13px;
        font-weight: 600;
      }

      h1 {
        max-width: 18ch;
        margin: 0 0 16px;
        font-size: clamp(32px, 5vw, 52px);
        line-height: 1.1;
        letter-spacing: -0.025em;
      }

      .hero__lead {
        max-width: 62ch;
        margin: 0 0 28px;
        font-size: clamp(16px, 2vw, 19px);
        color: var(--color-muted);
      }

      .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        margin-bottom: 12px;
      }

      .actions__note {
        margin: 0 0 28px;
        font-size: 13px;
        color: var(--color-subtle);
      }

      /* Скриншот */
      .shot {
        margin: 0;
        border-radius: var(--radius);
        border: 1px solid var(--color-border);
        background: var(--color-lane);
        box-shadow: var(--shadow);
        overflow: hidden;
      }

      .shot img {
        display: block;
        width: 100%;
        height: auto;
        /* До появления файла блок не выглядит пустым провалом: фон слоя lane. */
        min-height: 220px;
        background: var(--color-lane);
      }

      .shot figcaption {
        padding: 12px 16px;
        border-top: 1px solid var(--color-border);
        background: var(--color-card);
        font-size: 13px;
        color: var(--color-muted);
      }

      /* Секции */
      section {
        padding: 56px 0;
      }

      .section-head {
        max-width: 720px;
        margin-bottom: 28px;
      }

      h2 {
        margin: 0 0 10px;
        font-size: clamp(24px, 3vw, 32px);
        line-height: 1.2;
        letter-spacing: -0.015em;
      }

      .section-head p {
        margin: 0;
        font-size: 17px;
        color: var(--color-muted);
      }

      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      }

      .card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        padding: 22px 24px;
      }

      .card h3 {
        margin: 0 0 8px;
        font-size: 17px;
      }

      .card p {
        margin: 0;
        font-size: 15px;
        color: var(--color-muted);
      }

      .card__marker {
        display: inline-block;
        margin-bottom: 10px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--color-accent);
      }

      /* Уровни доски */
      .levels {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        counter-reset: level;
      }

      .level {
        display: flex;
        flex-direction: column;
        gap: 10px;
        padding: 20px 22px;
        border-radius: var(--radius);
        border: 1px solid var(--color-border);
        background: var(--color-card);
      }

      .level__step {
        counter-increment: level;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-muted);
      }

      .level__step::before {
        content: 'Уровень ' counter(level) ' · ';
      }

      .level h3 {
        margin: 0;
        font-size: 18px;
      }

      .level p {
        margin: 0;
        font-size: 15px;
        color: var(--color-muted);
      }

      .chips {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: auto;
        padding-top: 6px;
      }

      .chip {
        padding: 3px 10px;
        border-radius: 999px;
        border: 1px solid var(--color-border);
        background: var(--color-lane);
        font-size: 12px;
        font-weight: 600;
        color: var(--color-ink);
      }

      .chip--done {
        background: #dcfce7;
        border-color: #bbf7d0;
        color: #166534;
      }

      .rule {
        margin-top: 16px;
        padding: 14px 18px;
        border-left: 3px solid var(--color-accent);
        border-radius: 0 12px 12px 0;
        background: var(--color-accent-soft);
        font-size: 15px;
        color: #1e3a8a;
      }

      .rule strong {
        display: block;
        margin-bottom: 4px;
      }

      /* CTA */
      .cta {
        margin-bottom: 56px;
        padding: 40px;
        border-radius: 20px;
        background: var(--color-app);
        color: #ffffff;
        text-align: center;
      }

      .cta h2 {
        margin-bottom: 10px;
      }

      .cta p {
        margin: 0 auto 24px;
        max-width: 56ch;
        color: #d1d5db;
      }

      .cta .button {
        background: #ffffff;
        border-color: #ffffff;
        color: var(--color-app);
      }

      .cta .button:hover {
        background: #f3f4f6;
      }

      /* Футер */
      footer {
        padding: 32px 0 48px;
        border-top: 1px solid var(--color-border);
        font-size: 14px;
        color: var(--color-muted);
      }

      .footer__top {
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        justify-content: space-between;
        margin-bottom: 24px;
      }

      .footer__brand p {
        margin: 6px 0 0;
        font-size: 13px;
        color: var(--color-subtle);
      }

      .footer__links {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 20px;
      }

      .footer__links a {
        text-decoration: none;
        color: var(--color-muted);
      }

      .footer__links a:hover {
        color: var(--color-app);
        text-decoration: underline;
      }

      .footer__legal {
        margin: 0 0 8px;
        font-size: 13px;
      }

      .footer__legal a {
        color: var(--color-app);
      }

      .footer__copyright {
        font-size: 13px;
        color: var(--color-subtle);
      }

      /* Адаптив */
      @media (max-width: 900px) {
        .hero {
          padding-top: 40px;
        }

        .topbar__nav a:not(.button) {
          display: none;
        }
      }

      @media (max-width: 640px) {
        .container {
          padding: 0 16px;
        }

        section {
          padding: 40px 0;
        }

        .cta {
          padding: 28px 20px;
          margin-bottom: 40px;
        }

        .card,
        .level {
          padding: 18px;
        }
      }

      @media (prefers-reduced-motion: reduce) {
        html {
          scroll-behavior: auto;
        }

        .button {
          transition: none;
        }
      }
    </style>
  </head>
  <body>
    <header class="topbar">
      <div class="container topbar__inner">
        <a class="brand" href="/">JWP Board<small>Персональный канбан</small></a>
        <nav class="topbar__nav" aria-label="Основная навигация">
          <a href="#features">Возможности</a>
          <a href="#how">Как работает</a>
          <a class="button button--small" href="/board/">Посмотреть демо</a>
        </nav>
      </div>
    </header>

    <main>
      <div class="container hero">
        <span class="eyebrow">Личный планировщик по методу канбан</span>

        <h1>Персональный канбан для целей, а не для совещаний</h1>

        <p class="hero__lead">
          JWP Board связывает большую цель с конкретным действием: направления, инициативы,
          проекты и задачи живут на одной доске с разными уровнями детализации. Вы всегда видите,
          где находится работа, — без переключения между таблицами, заметками и приложениями.
        </p>

        <div class="actions">
          <a class="button" href="/board/">Посмотреть демо</a>
          <a class="button button--secondary" href="#how">Как это устроено</a>
        </div>
        <p class="actions__note">
          Демо открывает веб-версию приложения. Для входа нужен аккаунт — он создаётся при
          регистрации; мобильное приложение работает с тем же аккаунтом и той же доской.
        </p>

        <figure class="shot">
          <img
            src="/images/board.png"
            alt="Доска JWP Board: три уровня — стратегический с инициативами по направлениям, тактический с проектами и операционный с задачами; карточки разложены по колонкам «Беклог», «В работе» и «Готово»."
            width="1600"
            height="1000"
            loading="lazy"
            decoding="async"
          />
          <figcaption>
            Доска JWP Board: направления задают цвет карточек, уровни раскладывают работу от
            стратегии к задачам, колонки показывают статус.
          </figcaption>
        </figure>
      </div>

      <section id="features">
        <div class="container">
          <div class="section-head">
            <h2>Зачем канбан, если задачи ведёт один человек</h2>
            <p>
              Канбан помогает не только командам. Его главная идея — сделать поток работы видимым
              и ограничить число одновременных дел. Для личного планирования это значит меньше
              брошенных проектов и меньше дел «в работе», которые на самом деле стоят.
            </p>
          </div>

          <div class="grid">
            <article class="card">
              <span class="card__marker">Видимость</span>
              <h3>Вся работа на одном экране</h3>
              <p>
                Список дел скрывает реальность: сколько проектов начато и ни один не доведён.
                Канбан-доска показывает это сразу — по колонкам видно, где скопилась работа.
              </p>
            </article>

            <article class="card">
              <span class="card__marker">Поток</span>
              <h3>Ограничение незавершённой работы</h3>
              <p>
                Главный принцип канбана — меньше дел одновременно. Здесь он встроен в карточки:
                родитель остаётся в работе, пока не завершены его подзадачи. Закрыть проект «на
                бумаге» не получится.
              </p>
            </article>

            <article class="card">
              <span class="card__marker">Контекст</span>
              <h3>Цель не теряется в задачах</h3>
              <p>
                Направление и инициатива — не ярлыки, а реальные родители. Цвет карточки
                наследуется от направления, поэтому на любом уровне видно, к какой области жизни
                относится работа.
              </p>
            </article>

            <article class="card">
              <span class="card__marker">Одно место</span>
              <h3>Одна доска — все устройства</h3>
              <p>
                Доска хранится на сервере и открывается в браузере и в мобильном приложении.
                Задача, перенесённая на телефоне, уже в нужной колонке на компьютере.
              </p>
            </article>

            <article class="card">
              <span class="card__marker">Фокус</span>
              <h3>Меньше переключений</h3>
              <p>
                Уровни и колонки сворачиваются в узкие полосы: на экране остаётся только то, что
                важно сейчас. Раскладка сохраняется на сервере и переживает перезагрузку.
              </p>
            </article>

            <article class="card">
              <span class="card__marker">Порядок</span>
              <h3>История изменений</h3>
              <p>
                Создание, правка и удаление карточек фиксируются в журнале. Возвращаясь к проекту
                через месяц, вы видите, что и когда менялось, а не гадаете.
              </p>
            </article>
          </div>
        </div>
      </section>

      <section id="how">
        <div class="container">
          <div class="section-head">
            <h2>Три уровня вместо одного списка</h2>
            <p>
              Обычный список задач теряет связь с целью: через неделю непонятно, зачем эта задача
              нужна. В JWP Board у каждой карточки есть родитель — связь не теряется никогда.
            </p>
          </div>

          <div class="levels">
            <article class="level">
              <span class="level__step">Стратегия</span>
              <h3>Инициативы по направлениям</h3>
              <p>
                Направление — это область жизни: работа, здоровье, обучение, дом. Инициатива — то,
                что вы собираетесь в этой области изменить.
              </p>
              <div class="chips">
                <span class="chip">Работа</span>
                <span class="chip">Здоровье</span>
                <span class="chip">Обучение</span>
              </div>
            </article>

            <article class="level">
              <span class="level__step">Тактика</span>
              <h3>Проекты по инициативам в работе</h3>
              <p>
                Разложите инициативу на проекты, которые реально можно закончить. Проекты
                показываются внутри своей инициативы, пока она в работе.
              </p>
              <div class="chips">
                <span class="chip">Беклог</span>
                <span class="chip">В работе</span>
                <span class="chip chip--done">Готово</span>
              </div>
            </article>

            <article class="level">
              <span class="level__step">Операции</span>
              <h3>Задачи по проектам в работе</h3>
              <p>
                Самый нижний уровень — конкретные дела. Они видны внутри своего проекта, а не
                свалены в общий список на сотню пунктов.
              </p>
              <div class="chips">
                <span class="chip">Сегодня</span>
                <span class="chip">На этой неделе</span>
                <span class="chip chip--done">Сделано</span>
              </div>
            </article>
          </div>

          <div class="rule">
            <strong>Правило потока</strong>
            Пока у карточки есть дочерние карточки в работе, её нельзя перевести вперёд: проект не
            уйдёт из «В работе», пока в нём живут незавершённые задачи, а инициатива — пока в работе
            её проекты. Интерфейс предлагает только разрешённые переходы: их рассчитывает сервер
            (поле <code>allowedTransitions</code>), поэтому правило нельзя обойти случайно.
          </div>
        </div>
      </section>

      <section>
        <div class="container">
          <div class="cta">
            <h2>Попробуйте доску на своих целях</h2>
            <p>
              Откройте демо и разложите одну реальную цель по уровням: направление, инициатива,
              проект, задачи. Обычно уже на этом шаге видно, что стоит «в работе» слишком долго.
            </p>
            <a class="button" href="/board/">Посмотреть демо</a>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <div class="container">
        <div class="footer__top">
          <div class="footer__brand">
            <a class="brand" href="/">JWP Board<small>Персональный канбан</small></a>
            <p>Личный инструмент планирования: направления, инициативы, проекты и задачи.</p>
          </div>

          <nav class="footer__links" aria-label="Разделы сайта">
            <a href="/">Главная</a>
            <a href="/privacy">Политика конфиденциальности</a>
            <a href="/terms">Пользовательское соглашение</a>
            <a href="/cookies">Cookie</a>
          </nav>
        </div>

        <p class="footer__copyright">© {{ now()->format('Y') }} JWP Board — персональный канбан.</p>
      </div>
    </footer>
  </body>
</html>
