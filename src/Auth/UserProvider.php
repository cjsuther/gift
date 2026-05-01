<?php

declare(strict_types=1);

namespace App\Auth;

interface UserProvider
{
    public function findActiveById(int $id): ?AuthenticatedUser;
}
