<?php

use App\Console\Commands\BatchAgeProgressCommand;
use App\Console\Commands\QaCostCommand;
use App\Console\Commands\QaResetCommand;
use App\Console\Commands\QaTimeTravelCommand;
use App\Modules\Admin\Presentation\Console\AdminCreateCommand;
use App\Modules\Collections\Presentation\Console\StorePublishCommand;
use App\Modules\Generation\Presentation\Console\ApplyEnrichmentReviewCommand;
use App\Modules\Generation\Presentation\Console\AuditDistractorsCommand;
use App\Modules\Generation\Presentation\Console\AuditTranslationsCommand;
use App\Modules\Generation\Presentation\Console\RegenerateShowcaseCommand;
use App\Modules\Generation\Presentation\Console\AuditTranslationKeysCommand;
use App\Modules\Generation\Presentation\Console\BackfillEnrichmentSuppressionsCommand;
use App\Modules\Generation\Presentation\Console\BakeoffCommand;
use App\Modules\Generation\Presentation\Console\EnrichBackfillCommand;
use App\Modules\Generation\Presentation\Console\RepairEchoExamplesCommand;
use App\Modules\Generation\Presentation\Console\EvalGenerationCommand;
use App\Modules\Generation\Presentation\Console\ExpireStaleDialogsCommand;
use App\Modules\Generation\Presentation\Console\GenerateCollectionCommand;
use App\Modules\Generation\Presentation\Console\RecoverLostTermsCommand;
use App\Modules\Generation\Presentation\Console\RepairContentLanguageCommand;
use App\Modules\Generation\Presentation\Console\SmokePracticeDialogCommand;
use App\Modules\Identity\Presentation\Console\GrantPremiumCommand;
use App\Modules\Plan\Infrastructure\Console\PlanImagesBackfillCommand;
use App\Modules\Plan\Infrastructure\Console\PlanSeedLoadCommand;
use App\Modules\Plan\Infrastructure\Console\PlanSpeakBackfillCommand;
use App\Modules\Plan\Infrastructure\Console\PlanShiftDayCommand;
use App\Modules\Plan\Presentation\Console\PlanNotifyTestCommand;
use App\Modules\Plan\Presentation\Console\PlanRepairCardCommand;
use App\Modules\Plan\Presentation\Console\PlanNotifyTickCommand;
use App\Modules\Learning\Presentation\Console\VerificationStatsCommand;
use App\Modules\Vocabulary\Presentation\Console\RelabelRepairedTranslationsCommand;
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
        BakeoffCommand::class,
        EnrichBackfillCommand::class,
        RepairEchoExamplesCommand::class,
        ApplyEnrichmentReviewCommand::class,
        AuditDistractorsCommand::class,
        AuditTranslationsCommand::class,
        RegenerateShowcaseCommand::class,
        AuditTranslationKeysCommand::class,
        BackfillEnrichmentSuppressionsCommand::class,
        RepairContentLanguageCommand::class,
        RecoverLostTermsCommand::class,
        SmokePracticeDialogCommand::class,
        ExpireStaleDialogsCommand::class,
        GrantPremiumCommand::class,
        PlanShiftDayCommand::class,
        PlanSeedLoadCommand::class,
        // Plan notifications (PLAN-UI-3): the 15-minute tick (scheduled in routes/console.php) and
        // the QA «send one letter now».
        PlanNotifyTickCommand::class,
        // P2R by hand (GEN-2a): one card of a lesson repaired for what the validator finds at it.
        PlanRepairCardCommand::class,
        PlanNotifyTestCommand::class,
        PlanImagesBackfillCommand::class,
        PlanSpeakBackfillCommand::class,
        VerificationStatsCommand::class,
        StorePublishCommand::class,
        BatchAgeProgressCommand::class,
        // The QA bench: forced-time, reset and the budget read. All three refuse in production;
        // the two that write refuse on any account not marked `users.is_qa`.
        QaTimeTravelCommand::class,
        QaResetCommand::class,
        QaCostCommand::class,
        AdminCreateCommand::class,
        RelabelRepairedTranslationsCommand::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API-only app (mobile + admin panel, no web login page). Laravel's default
        // guest-redirect resolver points unauthenticated requests at route('login'), which does not
        // exist here — so a browser request (no `Accept: application/json`) to a guarded route threw
        // RouteNotFoundException (500) instead of 401. Null the resolver: with no redirect target the
        // handler answers 401 for every unauthenticated request (JSON body on our api/admin paths).
        $middleware->redirectGuestsTo(fn (): ?string => null);

        /*
         * ДОВЕРЯТЬ ЗАГОЛОВКАМ ПРОКСИ — иначе приложение врёт о собственной схеме.
         *
         * Телефон ходит через ngrok по HTTPS, ngrok приходит в контейнер по HTTP и ставит
         * `X-Forwarded-Proto: https`. Без доверия к прокси Laravel читает схему СОКЕТА, и всё, что
         * он генерирует абсолютным адресом, выходит с `http://`.
         *
         * Поймано живьём (наряд TTS-1): `audio_url` реплики уезжал как
         * `http://greedily-thermos-finer.ngrok-free.dev/...`, iOS резал cleartext-запрос по ATS,
         * докачка молча падала — и ВСЕ реплики на телефоне звучали системным голосом, на всех
         * экранах сразу. Дев-экран при этом работал, потому что играет ассеты из бандла и в сеть не
         * ходит вовсе, — и это ровно та разница, из-за которой дефект читался как «озвучка не
         * доехала до диалога».
         *
         * `at: '*'` — потому что перед приложением стоит ровно один прокси и его адрес не наш:
         * ngrok меняет IP от запуска к запуску. Приложение слушает только внутри compose-сети,
         * снаружи в него никто не ходит напрямую.
         */
        $middleware->trustProxies(at: '*');
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
