<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Provider;

use App\Modules\Identity\Application\Port\GoogleSignIn;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;
use App\Modules\Identity\Application\Port\ProfileUpdater;
use App\Modules\Identity\Application\Port\SignOut;
use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Infrastructure\Adapter\GoogleAuthTokenVerifier;
use App\Modules\Identity\Infrastructure\Auth\SanctumGoogleSignIn;
use App\Modules\Identity\Infrastructure\Auth\SanctumSignOut;
use App\Modules\Identity\Infrastructure\Eloquent\EloquentProfileUpdater;
use App\Modules\Identity\Infrastructure\Eloquent\EloquentUserReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleTokenVerifier::class, function (): GoogleAuthTokenVerifier {
            /** @var list<string> $clientIds */
            $clientIds = array_values(array_map('strval', (array) config('services.google.client_ids', [])));

            return new GoogleAuthTokenVerifier($clientIds);
        });

        $this->app->bind(GoogleSignIn::class, SanctumGoogleSignIn::class);
        $this->app->bind(UserReader::class, EloquentUserReader::class);
        $this->app->bind(ProfileUpdater::class, EloquentProfileUpdater::class);
        $this->app->bind(SignOut::class, SanctumSignOut::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');

        $routes = __DIR__ . '/../../Presentation/Http/routes.php';
        if (is_file($routes)) {
            Route::middleware('api')->prefix('api/v1')->group($routes);
        }
    }
}
