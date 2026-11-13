<?php

namespace App\Http\Requests\Api\V1;

use App\Modules\User\Domain\ValueObjects\Password;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса на регистрацию пользователя (общий контракт для мобильного и веб-клиента).
 *
 * Запрос нужен двум входным адаптерам — регистрации мобильного клиента
 * (модуль User) и веб-регистрации (app/Http/Controllers/Api/V1/Web), поэтому
 * правила лежат здесь; контроллер модуля работает с тонким наследником из
 * своей Infrastructure.
 *
 * Идентификация пользователя — только email и пароль, поэтому поля `name` нет.
 * Подтверждение пароля обязательно: правило `confirmed` требует `password_confirmation`.
 *
 * Имени устройства здесь нет: оно относится к выдаче Bearer-токена и
 * передаётся при входе (LoginRequest), а регистрация веб-клиента обходится
 * вовсе без токена.
 */
class StoreUserRequest extends FormRequest
{
    /**
     * Регистрация доступна неаутентифицированным пользователям.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:'.Password::MIN_LENGTH,
                'max:'.Password::MAX_LENGTH,
                'same:password_confirmation',
            ],
            'password_confirmation' => ['required', 'string', 'min:'.Password::MIN_LENGTH],
            // Имя устройства не идёт в команду: контроллер использует его
            // только как метку Bearer-токена, но валидировать всё равно нужно.
            'device_name' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Человекочитаемые имена полей для сообщений об ошибках.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => 'email',
            'password' => 'пароль',
            'password_confirmation' => 'подтверждение пароля',
            'device_name' => 'имя устройства',
        ];
    }

    /**
     * Сообщения об ошибках на русском.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Укажите email.',
            'email.email' => 'Укажите корректный email.',
            'email.max' => 'Email не может быть длиннее 255 символов.',
            'password.required' => 'Укажите пароль.',
            'password.min' => 'Пароль должен содержать минимум :min символов.',
            'password.max' => 'Пароль не может быть длиннее :max символов.',
            'password.same' => 'Пароли не совпадают.',
            'password_confirmation.required' => 'Повторите пароль.',
            'password_confirmation.min' => 'Подтверждение пароля должно содержать минимум :min символов.',
        ];
    }

    /**
     * Провалидированные данные для команды регистрации.
     *
     * @return array{email: string, password: string}
     */
    public function toCommandPayload(): array
    {
        return [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
        ];
    }
}
