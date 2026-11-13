<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Pages\LegalPageService;
use Illuminate\Contracts\View\View;

/**
 * Юридические страницы публичного сайта: политика обработки персональных
 * данных, пользовательское соглашение и политика cookie.
 *
 * Отдают HTML (Blade), поэтому лежат вне Api/V1 — там только JSON-контракт.
 * Шаблоны — resources/views/legal, общий каркас — component x-legal.layout.
 */
final class LegalPagesController extends Controller
{
    public function __construct(
        private readonly LegalPageService $pages,
    ) {}

    /**
     * Политика обработки персональных данных (152-ФЗ, ст. 18.1).
     */
    public function privacy(): View
    {
        return $this->pages->render('privacy');
    }

    /**
     * Пользовательское соглашение (публичная оферта, ГК РФ ст. 435–438).
     */
    public function terms(): View
    {
        return $this->pages->render('terms');
    }

    /**
     * Политика использования файлов cookie.
     */
    public function cookies(): View
    {
        return $this->pages->render('cookies');
    }
}
