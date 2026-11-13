<?php

namespace App\Pages;

use Illuminate\Contracts\View\View;
use InvalidArgumentException;

/**
 * Отдача юридических страниц публичного сайта.
 *
 * Политика обработки персональных данных, пользовательское соглашение и
 * политика cookie — Blade-шаблоны в resources/views/legal. Реквизиты оператора,
 * даты редакций и контакты не дублируются в трёх шаблонах: значения приходят из
 * config('legal'), общий каркас страницы — component x-legal.layout, блок
 * реквизитов — x-legal.operators.
 *
 * Сервис отвечает только за выбор шаблона: подстановка значений живёт
 * в шаблонах, а не в PHP-коде.
 */
final class LegalPageService
{
    /**
     * Имена документов: ключ — имя Blade-шаблона, значение — заголовок страницы
     * (он же <title> и подпись активной ссылки в футере).
     */
    public const PAGES = [
        'privacy' => 'Политика обработки персональных данных',
        'terms' => 'Пользовательское соглашение',
        'cookies' => 'Политика использования файлов cookie',
    ];

    /**
     * Собрать страницу документа.
     *
     * Ответ возвращается как View (а не JsonResponse): это HTML публичного
     * сайта, а не контракт API. Проверка имени шаблона защищает от подстановки
     * произвольного имени из URL.
     */
    public function render(string $page): View
    {
        if (! array_key_exists($page, self::PAGES)) {
            throw new InvalidArgumentException("Неизвестная юридическая страница: {$page}");
        }

        return view("legal.{$page}", ['title' => self::PAGES[$page]]);
    }
}
