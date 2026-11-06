<?php

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Веб-маршруты: только лендинг
|--------------------------------------------------------------------------
| Бэкенд остаётся headless API: весь контракт приложения живёт в /api/v1.
| Исключение — публичный сайт (лендинг). Его разметка лежит в resources/views:
| вне веб-корня, поэтому страница доступна только по `/` и не дублируется
| адресами вида /landing/ или /landing/index.html. Blade здесь не используется —
| маршрут отдаёт готовый файл; HTML-ответов из контроллеров по-прежнему нет.
|
| Веб-версия самого приложения — статическая сборка mobile по адресу /board/.
| Её отдаёт веб-сервер (nginx локально, .htaccess на хостинге), до Laravel
| такие запросы не доходят: это SPA с клиентской маршрутизацией.
*/

// Лендинг — статическая страница из resources/views/landing.html.
// `->file()` транслирует поток ответа, поэтому в тесте содержимое читается из
// файла напрямую (см. tests/Feature/LandingTest.php).
Route::get('/', function () {
    return response()->file(resource_path('views/landing.html'));
})->name('landing');

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
*/
Route::fallback(function (Request $request) {
    if ($request->is('api', 'api/*', 'up')) {
        abort(404);
    }

    if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
        abort(Response::HTTP_NOT_FOUND);
    }

    return new RedirectResponse('/', Response::HTTP_FOUND);
});
