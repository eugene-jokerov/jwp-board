<?php

use App\Modules\User\Infrastructure\Persistence\EloquentUserModel;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Консольные команды
|--------------------------------------------------------------------------
| Вне HTTP пайп шины DenyReadOnlyCommands видит пользователя только через
| Auth::user(). Чтобы правило «только чтение» действовало и в консоли,
| исполнитель входит в гвард `web` перед командами шины:
|   php artisan tinker-as 42
|   php artisan tinker-as user@example.com
| (в Makefile — make artisan-as who=42).
*/

Artisan::command('tinker-as {user}', function (string $user): void {
    $model = EloquentUserModel::query()
        ->when(
            ctype_digit($user),
            fn ($query) => $query->whereKey((int) $user),
            fn ($query) => $query->where('email', $user),
        )
        ->first();

    if ($model === null) {
        $this->error('Пользователь не найден: '.$user);

        return;
    }

    // Auth::login оставляет исполнителя в гварде `web`: DenyReadOnlyCommands
    // прочитает его флаг и откажет в записи, если аккаунт read-only.
    Auth::login($model);

    $this->info(sprintf(
        'Вошёл как %s (readonly: %s). Команды шины выполняются от его имени.',
        $model->email,
        $model->isReadonly() ? 'да' : 'нет',
    ));

    $this->call('tinker');
})->purpose('Tinker от имени пользователя (учитывает флаг readonly)');

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
