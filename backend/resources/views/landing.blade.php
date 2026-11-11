<!doctype html>
<html lang="ru">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>JWP Board — персональный канбан для личных целей</title>
    <meta
      name="description"
      content="JWP Board — персональный канбан: направления, инициативы, проекты и задачи на одной доске. Один аккаунт для веба и мобильного приложения."
    />
    <meta property="og:type" content="website" />
    <meta property="og:title" content="JWP Board — персональный канбан" />
    <meta
      property="og:description"
      content="Три уровня вместо одного списка: направления, инициативы, проекты и задачи. Одна доска для веба и мобильного приложения."
    />
    <meta property="og:image" content="/images/board.png" />
    {{--
      Лендинг — Blade-шаблон (маршрут «/» в routes/web.php, Route::view).
      Сборки нет: стили инлайновые, палитра совпадает с mobile/tailwind.config.js
      (app/surface/card/muted/border/lane/ink). Год в футере берётся из now(),
      адрес демо — маршрут приложения /board/.

      Страница намеренно короткая: тексты ещё не проработаны, поэтому блоков
      ровно столько, сколько нужно, чтобы объяснить идею — hero, три уровня
      доски, три факта и призыв открыть демо. Скриншот /images/board.png
      кладётся в public/images отдельно; до появления файла блок держит размер
      (aspect-ratio и фон lane), а подпись объясняет, что на нём будет.
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
        max-width: 1000px;
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
        padding: 56px 0 40px;
        text-align: center;
      }

      /*
        Заголовок держим в одну строку: 23 символа при 48px — это ~640px даже
        широким DejaVu Sans (в system-ui/Arial заметно меньше), а полная ширина
        контейнера на десктопе — 952px, так что запас есть. Прежний max-width
        22ch (~528px) был уже самой строки и заставлял её переноситься; вместо
        него запрет переноса, а на очень узких экранах (телефон) размер
        подбирается так, чтобы строка влезла по ширине, — там допустимо
        уменьшение ниже обычного масштаба.
      */
      h1 {
        margin: 0 auto 16px;
        font-size: clamp(19px, 6.4vw, 48px);
        line-height: 1.1;
        letter-spacing: -0.025em;
        text-wrap: nowrap;
        white-space: nowrap;
      }

      .hero__lead {
        max-width: 54ch;
        margin: 0 auto 28px;
        font-size: clamp(16px, 2vw, 18px);
        color: var(--color-muted);
      }

      .actions {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 12px;
        margin-bottom: 12px;
      }

      .actions__note {
        margin: 0;
        font-size: 13px;
        color: var(--color-subtle);
      }

      /*
        Скриншот доски: 1600×686 (пропорция 2.33:1).

        Ширины 100% мало: внутри ограниченного по высоте окна картинка вместе со
        всем hero съезжала вниз и упиралась в сгиб. Поэтому блок ограничен по
        высоте (max-height на .shot img), а не по пропорции: по умолчанию
        картинка открывается целиком, а при низком окне (ноутбук, mobile
        landscape) ужимается до 52vh — ширину тогда задаёт само соотношение
        сторон, и правый край с колонкой «Отменено» виден без обрезки.

        На телефоне ограничение по высоте снимается (см. @media ниже): при
        ширине экрана 375px пропорция 2.33:1 давала полоску ~145px, в которой
        не читался ни один текст карточки. Там картинка идёт в естественную
        высоту и её можно приблизить (pinch-zoom), а тап открывает файл целиком.
      */
      .shot {
        margin: 40px 0 0;
        border-radius: var(--radius);
        border: 1px solid var(--color-border);
        background: var(--color-lane);
        box-shadow: var(--shadow);
        overflow: hidden;
        text-align: left;
      }

      .shot img {
        display: block;
        width: 100%;
        max-width: 100%;
        /* 1600 / 686 — точная пропорция файла, поэтому без кропа. */
        aspect-ratio: 1600 / 686;
        max-height: 52vh;
        /* Если высоту урезал max-height, картинка центрируется, а не липнет влево. */
        margin-inline: auto;
        object-fit: contain;
        background: var(--color-lane);
      }

      /* Ссылка-обёртка нужна только на тач-экранах: тап открывает скриншот целиком. */
      .shot__link {
        display: block;
      }

      .shot__hint {
        display: none;
        margin: 10px 0 0;
        font-size: 13px;
        color: var(--color-subtle);
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
        padding: 48px 0;
      }

      .section-head {
        max-width: 640px;
        margin: 0 auto 28px;
        text-align: center;
      }

      h2 {
        margin: 0 0 10px;
        font-size: clamp(22px, 3vw, 30px);
        line-height: 1.2;
        letter-spacing: -0.015em;
      }

      .section-head p {
        margin: 0;
        font-size: 16px;
        color: var(--color-muted);
      }

      .grid {
        display: grid;
        gap: 16px;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      }

      .card {
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        padding: 22px 24px;
      }

      .card h3 {
        margin: 0 0 6px;
        font-size: 17px;
      }

      .card p {
        margin: 0;
        font-size: 15px;
        color: var(--color-muted);
      }

      /* Уровни доски */
      .level {
        display: flex;
        flex-direction: column;
        gap: 8px;
        background: var(--color-card);
        border: 1px solid var(--color-border);
        border-radius: var(--radius);
        padding: 22px 24px;
      }

      .level__step {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-muted);
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
        padding-top: 8px;
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
        margin: 16px 0 0;
        padding: 14px 18px;
        border-left: 3px solid var(--color-accent);
        border-radius: 0 12px 12px 0;
        background: #e8f0fe;
        font-size: 15px;
        color: #1e3a8a;
      }

      /* CTA */
      .cta {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 24px 28px;
        border-radius: var(--radius);
        border: 1px solid var(--color-border);
        background: var(--color-card);
      }

      .cta h2 {
        margin: 0 0 4px;
        font-size: 20px;
      }

      .cta p {
        margin: 0;
        font-size: 15px;
        color: var(--color-muted);
      }

      /* Футер */
      footer {
        padding: 32px 0 48px;
        border-top: 1px solid var(--color-border);
        font-size: 14px;
        color: var(--color-muted);
      }

      .footer__inner {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        justify-content: space-between;
        gap: 12px 24px;
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

      .footer__copyright {
        margin: 16px 0 0;
        font-size: 13px;
        color: var(--color-subtle);
      }

      /* Адаптив */
      @media (max-width: 640px) {
        .container {
          padding: 0 16px;
        }

        .hero {
          padding-top: 48px;
        }

        section {
          padding: 36px 0;
        }

        .card,
        .level {
          padding: 18px;
        }

        .cta {
          padding: 20px;
        }

        /*
          Скриншот на телефоне. Вся доска в ширину 343px — это масштаб ~0.21:
          подписи карточек выходят ~3px, то есть при любой высоте блока блок
          остаётся «пёстрой полоской». Поэтому здесь показывается не вся доска,
          а её кадр: левый край в натуральном масштабе (уровни направлений и
          первая колонка с карточками читаемого размера), а целиком скриншот
          открывается тапом по ссылке.
        */
        .shot {
          margin-top: 28px;
        }

        .shot__link {
          aspect-ratio: 4 / 3;
          overflow: hidden;
        }

        .shot img {
          width: 300%;
          max-width: none;
          aspect-ratio: auto;
          max-height: none;
          margin-inline: 0;
          object-fit: cover;
          object-position: left top;
        }

        .shot__hint {
          display: block;
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
        <a class="button button--small" href="/board/">Открыть доску</a>
      </div>
    </header>

    <main>
      <div class="container hero">
        <h1>Канбан для личных целей</h1>

        <p class="hero__lead">
          Направления, инициативы, проекты и задачи — на одной доске. Видно, что в работе, а что
          стоит без движения.
        </p>

        <div class="actions">
          <a class="button" href="/board/">Посмотреть демо</a>
          <a class="button button--secondary" href="#how">Как это устроено</a>
        </div>

        <figure class="shot">
          {{--
            Обёртка-ссылка: на телефоне тап открывает скриншот в натуральном
            размере (браузер даёт зум). На десктопе это обычная ссылка на файл,
            вреда нет; показывать её как действие подсказывает .shot__hint,
            который виден только на узких экранах.
          --}}
          <a class="shot__link" href="/images/board.png">
            <img
              src="/images/board.png"
              alt="Доска JWP Board: три уровня — инициативы по направлениям, проекты и задачи; карточки разложены по колонкам «Беклог», «В работе» и «Готово»."
              width="1600"
              height="686"
              loading="lazy"
              decoding="async"
            />
          </a>
          <figcaption>
            Доска JWP Board: направления задают цвет карточек, уровни раскладывают работу от
            стратегии к задачам, колонки показывают статус.
          </figcaption>
          <p class="shot__hint">Нажмите на скриншот, чтобы рассмотреть доску целиком.</p>
        </figure>
      </div>

      <section id="how">
        <div class="container">
          <div class="section-head">
            <h2>Три уровня вместо одного списка</h2>
            <p>У каждой карточки есть родитель, поэтому задача не теряет связи с целью.</p>
          </div>

          <div class="grid">
            <article class="level">
              <span class="level__step">Стратегический уровень</span>
              <h3>Инициативы по направлениям</h3>
              <p>Направление — область жизни, например: работа, здоровье, обучение, дом.</p>
              <div class="chips">
                <span class="chip">Работа</span>
                <span class="chip">Здоровье</span>
                <span class="chip">Обучение</span>
              </div>
            </article>

            <article class="level">
              <span class="level__step">Тактический уровень</span>
              <h3>Проекты по инициативам</h3>
              <p>Инициатива раскладывается на проекты, которые реально закончить.</p>
              <div class="chips">
                <span class="chip">Беклог</span>
                <span class="chip">В работе</span>
                <span class="chip chip--done">Готово</span>
              </div>
            </article>

            <article class="level">
              <span class="level__step">Операционный уровень</span>
              <h3>Задачи по проектам</h3>
              <p>Самый нижний уровень — конкретные дела внутри своего проекта.</p>
              <div class="chips">
                <span class="chip">Сегодня</span>
                <span class="chip">На неделе</span>
                <span class="chip chip--done">Сделано</span>
              </div>
            </article>
          </div>

          <p class="rule">
            Карточка остаётся в работе, пока не завершены вложенные: проект не закроется с живыми
            задачами, инициатива — с незакрытыми проектами.
          </p>
        </div>
      </section>

      <section>
        <div class="container">
          <div class="grid">
            <article class="card">
              <h3>Вся работа на одном экране</h3>
              <p>По колонкам видно, где скопилась работа, а где всё стоит.</p>
            </article>

            <article class="card">
              <h3>Один аккаунт — все устройства</h3>
              <p>Доска хранится на сервере: телефон и компьютер показывают одно и то же.</p>
            </article>

            <article class="card">
              <h3>История изменений</h3>
              <p>Создание, правка и удаление карточек фиксируются в журнале.</p>
            </article>
          </div>
        </div>
      </section>

      <section>
        <div class="container">
          <div class="cta">
            <div>
              <h2>Попробуйте на своих целях</h2>
              <p>Разложите одну реальную цель по уровням — начните с демо.</p>
            </div>
            <a class="button" href="/board/">Посмотреть демо</a>
          </div>
        </div>
      </section>
    </main>

    <footer>
      <div class="container">
        <div class="footer__inner">
          <a class="brand" href="/">JWP Board<small>Персональный канбан</small></a>

          <nav class="footer__links" aria-label="Разделы сайта">
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
