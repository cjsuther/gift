<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Auth\AuthenticatedUser;
use App\Auth\UserProvider;

final class InMemoryUserProvider implements UserProvider
{
    /** @var array<int, AuthenticatedUser> */
    private array $users = [];

    public function add(AuthenticatedUser $user): void
    {
        $this->users[$user->id] = $user;
    }

    public function findActiveById(int $id): ?AuthenticatedUser
    {
        return $this->users[$id] ?? null;
    }
}
