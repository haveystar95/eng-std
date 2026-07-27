<?php

use App\Modules\Identity\Domain\Exception\InvalidGoogleToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // Domain exception → HTTP mapping. Kept in a Laravel-validation shape so the
        // Flutter client parses auth errors the same way it does field validation.
        $exceptions->render(fn (InvalidGoogleToken $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
            'errors' => ['id_token' => [$e->getMessage()]],
        ], 422));
    })->create();
