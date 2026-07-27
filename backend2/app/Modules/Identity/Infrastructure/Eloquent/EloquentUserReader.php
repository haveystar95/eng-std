<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Eloquent;

use App\Modules\Identity\Application\Dto\UserView;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class EloquentUserReader implements UserReader
{
    public function __construct(private readonly UserViewMapper $mapper) {}

    public function byId(UserId $id): ?UserView
    {
        $user = User::query()->find($id->value);

        return $user !== null ? $this->mapper->toView($user) : null;
    }
}
