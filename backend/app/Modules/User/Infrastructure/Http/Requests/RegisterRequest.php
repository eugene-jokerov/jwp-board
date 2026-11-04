<?php

namespace App\Modules\User\Infrastructure\Http\Requests;

use App\Modules\User\Domain\ValueObjects\Password;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Валидация запроса на регистрацию пользователя.
 *
 * Идентификация пользователя — только email и пароль, поэтому поля `name` нет.
 * Подтверждение пароля обязательно: правило `confirmed` требует `password_confirmation`.
 */
final class RegisterRequest extends FormRequest
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
     * @return array{email: string, password: string, device_name?: string|null}
     */
    public function toCommandPayload(): array
    {
        return [
            'email' => $this->string('email')->toString(),
            'password' => $this->string('password')->toString(),
            'device_name' => $this->input('device_name'),
        ];
    }
}
