<?php

use App\Modules\Generation\Presentation\Console\EvalGenerationCommand;
use App\Modules\Generation\Presentation\Console\GenerateCollectionCommand;
use App\Modules\Learning\Presentation\Console\VerificationStatsCommand;
use App\Modules\Shared\Domain\Exception\ProblemDetails;
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
    ->withCommands([
        GenerateCollectionCommand::class,
        EvalGenerationCommand::class,
        VerificationStatsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // One place turns any ProblemDetails domain exception into RFC 7807
        // application/problem+json. A new domain error surfaces correctly by implementing
        // the interface — no change here. (Input validation keeps Laravel's 422 shape.)
        $exceptions->render(function (ProblemDetails $e): JsonResponse {
            return new JsonResponse([
                'type' => 'https://api.wordtrainer.app/errors/' . str_replace('_', '-', $e->problemCode()),
                'title' => $e->problemTitle(),
                'status' => $e->problemStatus(),
                'code' => $e->problemCode(),
                'detail' => $e instanceof Throwable ? $e->getMessage() : '',
                'meta' => $e->problemMeta(),
            ], $e->problemStatus(), ['Content-Type' => 'application/problem+json']);
        });
    })->create();
