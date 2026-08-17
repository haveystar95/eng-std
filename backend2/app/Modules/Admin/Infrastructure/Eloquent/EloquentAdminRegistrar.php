<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Port\AdminRegistrar;

final class EloquentAdminRegistrar implements AdminRegistrar
{
    public function create(string $email, string $name, string $password): ?string
    {
        if (Admin::query()->where('email', $email)->exists()) {
            return null;
        }

        // The model's `hashed` cast bcrypts the password on save.
        $admin = Admin::query()->create([
            'email' => $email,
            'name' => $name,
            'password' => $password,
        ]);

        return $admin->id;
    }
}
