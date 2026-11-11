<?php

use App\Http\Controllers\Web\LegalPagesController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Веб-маршруты: лендинг и юридические страницы
|--------------------------------------------------------------------------
| Бэкенд остаётся headless API: весь контракт приложения живёт в /api/v1.
| Исключение — публичный сайт: лендинг и юридические документы (политика
| персональных данных, пользовательское соглашение, политика cookie), на
| которые ссылается футер лендинга. Оба варианта — Blade-шаблоны
| (resources/views, провайдер Illuminate\View\ViewServiceProvider), поэтому
| реквизиты оператора и год в футере подставляются из config/legal.php, а не
| правятся в разметке. Шаблоны лежат вне веб-корня: `/` — единственный адрес
| лендинга, /landing/ и /landing/index.html уводятся фолбэком.
|
| Веб-версия самого приложения — статическая сборка mobile по адресу /board/.
| Её отдаёт веб-сервер (nginx локально, .htaccess на хостинге), до Laravel
| такие запросы не доходят: это SPA с клиентской маршрутизацией.
*/

// Лендинг — Blade-шаблон resources/views/landing.blade.php.
Route::view('/', 'landing')->name('landing');

/*
| Юридические страницы публичного сайта.
|
| Политика обработки персональных данных (152-ФЗ, ст. 18.1), пользовательское
| соглашение и политика cookie. На них ссылается футер лендинга. Маршруты
| объявлены до Route::fallback: иначе неизвестный путь уводил бы эти адреса
| редиректом на «/». Шаблоны — resources/views/legal, реквизиты оператора
| выводятся из config/legal.php.
*/
Route::get('/privacy', [LegalPagesController::class, 'privacy'])->name('legal.privacy');
Route::get('/terms', [LegalPagesController::class, 'terms'])->name('legal.terms');
Route::get('/cookies', [LegalPagesController::class, 'cookies'])->name('legal.cookies');

/*
| Редиректы заданы относительным Location: абсолютный адрес Laravel строит из
| APP_URL/заголовка Host, а за docker-прокси (и на хостинге за реверс-прокси)
| host подставляется неверно — браузер уехал бы на чужой адрес. Поэтому
| создаём RedirectResponse напрямую: Location остаётся относительным.
*/

// /board без завершающего слэша: относительные пути сборки (бандл, ассеты)
// строятся от каталога /board/, поэтому ведём на канонический адрес.
Route::get('/board', function () {
    return new RedirectResponse('/board/', Response::HTTP_FOUND);
});

/*
| Всё остальное уводим на лендинг: сохранённые ссылки вида /auth или старые
| адреса приложения не должны отдавать JSON-ошибку Laravel.
|
| API исключён явно: неизвестный /api/* должен остаться JSON 404 (это контракт
| клиента), а не редиректом на лендинг. Fallback перехватывает все методы,
| поэтому не-API пути проверяем внутри и возвращаем 404 для остальных методов.
|
| Админка Filament (/admin) и её служебные маршруты исключены: панель
| регистрируется своим провайдером, но Livewire 4 отдаёт свои эндпоинты по
| пути с хешем от APP_KEY (/livewire-<hash>/…), в том числе POST-запросами —
| редирект на лендинг сломал бы работу панели. Заодно исключены префиксы
| системных маршрутов пакета (/filament), чтобы новые страницы админки не
| уезжали на «/».
*/
Route::fallback(function (Request $request) {
    if ($request->is(
        'api', 'api/*', 'up',
        'admin', 'admin/*',
        'livewire-*', 'filament', 'filament/*',
    )) {
        abort(404);
    }

    if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
        abort(Response::HTTP_NOT_FOUND);
    }

    return new RedirectResponse('/', Response::HTTP_FOUND);
});
