<?php

namespace App\Modules\User\Infrastructure\Http\Requests;

use App\Http\Requests\Api\V1\StoreUserRequest;

/**
 * Регистрация мобильного клиента (модуль User).
 *
 * Правила общие с веб-регистрацией и живут в API-контракте StoreUserRequest:
 * два входных адаптера переиспользуют одну валидацию. Класс объявлен здесь,
 * чтобы маршрут модуля отправлял запрос своему собственному адаптеру.
 */
final class RegisterRequest extends StoreUserRequest {}
