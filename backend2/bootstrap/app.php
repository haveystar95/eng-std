<?php

use App\Console\Commands\BatchAgeProgressCommand;
use App\Modules\Admin\Presentation\Console\AdminCreateCommand;
use App\Modules\Collections\Presentation\Console\StorePublishCommand;
use App\Modules\Generation\Presentation\Console\ApplyEnrichmentReviewCommand;
use App\Modules\Generation\Presentation\Console\EnrichBackfillCommand;
use App\Modules\Generation\Presentation\Console\EvalGenerationCommand;
use App\Modules\Generation\Presentation\Console\ExpireStaleDialogsCommand;
use App\Modules\Generation\Presentation\Console\GenerateCollectionCommand;
use App\Modules\Generation\Presentation\Console\SmokePracticeDialogCommand;
use App\Modules\Identity\Presentation\Console\GrantPremiumCommand;
use App\Modules\Learning\Presentation\Console\VerificationStatsCommand;
use App\Modules\Shared\Domain\Exception\ProblemDetails;
use Illuminate\Auth\AuthenticationException;
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
        EnrichBackfillCommand::class,
        ApplyEnrichmentReviewCommand::class,
        SmokePracticeDialogCommand::class,
        ExpireStaleDialogsCommand::class,
        GrantPremiumCommand::class,
        VerificationStatsCommand::class,
        StorePublishCommand::class,
        BatchAgeProgressCommand::class,
        AdminCreateCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only app (mobile + admin panel, no web login page). Laravel's default
        // guest-redirect resolver points unauthenticated requests at route('login'), which does not
        // exist here — so a browser request (no `Accept: application/json`) to a guarded route threw
        // RouteNotFoundException (500) instead of 401. Null the resolver: with no redirect target the
        // handler answers 401 for every unauthenticated request (JSON body on our api/admin paths).
        $middleware->redirectGuestsTo(fn (): ?string => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('admin/api/*'),
        );

        // An unauthenticated API/admin request must get a JSON 401 — never a redirect to the
        // (nonexistent) `login` route, which browsers hit because they don't send
        // `Accept: application/json`. `shouldRenderJsonWhen` alone does not cover the auth guard's
        // redirect decision, so handle AuthenticationException explicitly here for our API paths.
        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->is('api/*') && ! $request->is('admin/api/*')) {
                return null; // let non-API requests keep the default behaviour
            }

            return new JsonResponse([
                'type' => 'https://api.wordtrainer.app/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'code' => 'unauthenticated',
                'detail' => $e->getMessage(),
            ], 401, ['Content-Type' => 'application/problem+json']);
        });

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
