<?php

namespace Tests\Unit\Modules\User\Support;

use App\Modules\User\Domain\Contracts\UserRepository;
use App\Modules\User\Domain\User;
use App\Modules\User\Domain\ValueObjects\Email;
use App\Modules\User\Domain\ValueObjects\Password;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Тестовый двойник порта UserRepository: хранит пользователей в памяти.
 *
 * Приложение в Unit-тестах не поднято, поэтому Eloquent-модель наполняется
 * напрямую через setRawAttributes(): каст `hashed` недоступен.
 */
final class InMemoryUserRepository implements UserRepository
{
    /**
     * Созданные email — в порядке вызовов create().
     *
     * @var list<string>
     */
    public array $created = [];

    /**
     * Пользователи по id: id => email.
     *
     * @var array<int, string>
     */
    private array $byId = [];

    private int $nextId = 1;

    public function seed(string $email, int $id = 1): void
    {
        $this->byId[$id] = $email;
        $this->nextId = max($this->nextId, $id + 1);
    }

    public function findByEmail(Email $email): ?User
    {
        $id = array_search($email->value(), $this->byId, true);

        return $id === false ? null : $this->makeUser($email->value(), (int) $id);
    }

    public function findById(int $id): User
    {
        if (! isset($this->byId[$id])) {
            throw (new ModelNotFoundException)->setModel(User::class, [$id]);
        }

        return $this->makeUser($this->byId[$id], $id);
    }

    public function emailExists(Email $email): bool
    {
        return in_array($email->value(), $this->byId, true);
    }

    public function create(Email $email, Password $password, bool $verified = false): User
    {
        $id = $this->nextId++;

        $this->created[] = $email->value();
        $this->byId[$id] = $email->value();

        return $this->makeUser($email->value(), $id);
    }

    /**
     * Собрать Eloquent-модель без обращения к БД.
     */
    private function makeUser(string $email, int $id): User
    {
        $user = new User;
        $user->setRawAttributes(['id' => $id, 'email' => $email], true);

        return $user;
    }
}
