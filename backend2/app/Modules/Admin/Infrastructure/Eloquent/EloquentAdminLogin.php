<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\AdminSession;
use App\Modules\Admin\Application\Port\AdminLogin;
use Illuminate\Support\Facades\Hash;

final class EloquentAdminLogin implements AdminLogin
{
    public function attempt(string $email, string $password, string $deviceName): ?AdminSession
    {
        $admin = Admin::query()->where('email', $email)->first();
        if ($admin === null || ! Hash::check($password, $admin->password)) {
            return null;
        }

        $token = $admin->createToken($deviceName)->plainTextToken;

        return new AdminSession($token, $admin->id, $admin->email, $admin->name);
    }
}
