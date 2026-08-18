<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Provider;

use App\Modules\Observability\Application\Port\ApiLogWriter;
use App\Modules\Observability\Application\Port\ApiRequestLogReader;
use App\Modules\Observability\Application\Port\RequestLogAnonymizer;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Observability\Infrastructure\Eloquent\EloquentApiLogWriter;
use App\Modules\Observability\Infrastructure\Eloquent\EloquentApiRequestLogReader;
use App\Modules\Observability\Infrastructure\Eloquent\EloquentRequestLogAnonymizer;
use App\Modules\Observability\Infrastructure\Http\Middleware\LogApiRequests;
use App\Modules\Observability\Infrastructure\Listener\LogOutboundHttp;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ApiLogWriter::class, EloquentApiLogWriter::class);
        $this->app->bind(ApiRequestLogReader::class, EloquentApiRequestLogReader::class);
        $this->app->bind(RequestLogAnonymizer::class, EloquentRequestLogAnonymizer::class);
        // One ambient label stack per process — the listener that writes the row and the job that
        // opened the scope must see the same instance.
        $this->app->singleton(OutboundCallContext::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migration');

        // Inbound: log every API request once the response has been sent.
        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->pushMiddlewareToGroup('api', LogApiRequests::class);

        // Outbound: log every Http client call (OpenAI today, anything tomorrow).
        Event::listen(ResponseReceived::class, [LogOutboundHttp::class, 'handleResponse']);
        Event::listen(ConnectionFailed::class, [LogOutboundHttp::class, 'handleFailure']);
    }
}
