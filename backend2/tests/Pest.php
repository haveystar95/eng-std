<?php

declare(strict_types=1);

use App\Modules\Admin\Infrastructure\Eloquent\Admin;
use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Command\SubmitReviewsHandler;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Learning\Domain\ValueObject\ModeAdmission;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Tests\Doubles\FakeLatencyMedianReader;
use Tests\Doubles\FakeLearnerProfileReader;
use Tests\Doubles\FakeTermAnswerKeyReader;
use Tests\Doubles\FakeTermExistenceReader;
use Tests\Doubles\FixedClock;
use Tests\Doubles\ImmediateTransactionManager;
use Tests\Doubles\InMemoryReviewRepository;
use Tests\Doubles\InMemoryStudySessions;
use Tests\Doubles\InMemoryTermExposureRepository;
use Tests\Doubles\InMemoryTermProgressRepository;
use Tests\Doubles\SpyStatsProjector;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');

// Every helper below is shared across more than one test file. It lives here — the one file Pest
// actually auto-loads for the whole run (Pest\Bootstrappers\BootFiles only boots tests/Pest.php, not
// a Pest.php per subdirectory) — because a plain top-level `function` declared inside one test file
// only happened to be visible to its siblings by the accident of serial load order: under
// `vendor/bin/pest --parallel`, a worker can run a file that calls it without ever having loaded the
// file that defines it.

/**
 * The SHIPPED admission matrix — the same value the migration seeds `learning_mode_settings` with,
 * so a unit test that never touches the database still asserts the policy that actually runs.
 */
function shippedMatrix(): ModeAdmission
{
    return ModeAdmission::shipped();
}

/**
 * Create a back-office admin and return it with a fresh bearer token. Password is fixed so
 * credential tests can log in with it.
 *
 * @return array{0: Admin, 1: string}
 */
function adminActor(string $email = 'root@wt.test'): array
{
    $admin = Admin::create(['email' => $email, 'name' => 'Root', 'password' => 'secret123']);

    return [$admin, $admin->createToken('panel')->plainTextToken];
}

/**
 * A study term added to a (new) custom collection for the user, without HTTP.
 *
 * @return array{0: string, 1: string}  [collectionId, termId]
 */
function adminSeedTerm(User $user, string $title, string $text, string $translation = 'x'): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $title, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;

    return [$collectionId->value, $termId];
}

/**
 * A fresh user with a bearer token, for HTTP-driven Learning/Vocabulary tests.
 *
 * @return array{0: User, 1: string}
 */
function learner(): array
{
    $user = User::factory()->create();

    return [$user, $user->createToken('test-device')->plainTextToken];
}

/** Create a collection + word for the user and return the term id (no HTTP). */
function seedWordFor(User $user, string $text = 'apple', string $translation = 'яблоко'): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Fruit', new LanguageCode('ru'), new LanguageCode('en'),
    ));

    return app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;
}

/**
 * Like {@see seedWordFor} but also returns the collection id.
 *
 * @return array{0: string, 1: string}  [collectionId, termId]
 */
function seedCollectionWith(User $user, string $text, string $translation = 'x'): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $text, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;

    return [$collectionId->value, $termId];
}

/** Add a word to an existing collection (no HTTP) and return the term id. */
function addWordTo(string $collectionId, string $userId, string $text, string $translation = 'x'): string
{
    return app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId), UserId::fromString($userId), $text, $translation,
    ))->value;
}

/** GET /sync and return the `data` envelope. */
function sync(object $ctx, string $token, string $query = ''): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync' . ($query !== '' ? "?{$query}" : ''))
        ->assertOk()
        ->json('data');
}

/**
 * A real `SubmitReviewsHandler` wired to in-memory doubles, for Unit/Learning tests. `$ctx` is the
 * Pest test case (`$this`) — the doubles are stashed on it so a test can assert against them after
 * calling the handler.
 *
 * @param  list<TermId>|null  $known  null = all known
 */
function buildSubmitHandler(object $ctx, ?array $known = null): SubmitReviewsHandler
{
    $ctx->reviews = new InMemoryReviewRepository();
    $ctx->exposures = new InMemoryTermExposureRepository();
    $ctx->progress = new InMemoryTermProgressRepository();
    $ctx->stats = new SpyStatsProjector();
    $ctx->median = new FakeLatencyMedianReader();
    $ctx->sessions = new InMemoryStudySessions();

    return new SubmitReviewsHandler(
        reviews: $ctx->reviews,
        exposures: $ctx->exposures,
        progress: $ctx->progress,
        scheduler: new Sm2Scheduler(Fuzz::none()),
        terms: $known === null ? FakeTermExistenceReader::knowingAll() : FakeTermExistenceReader::knowing($known),
        answerKeys: new FakeTermAnswerKeyReader(),
        grader: new AnswerGrader(),
        median: $ctx->median,
        sessionContexts: $ctx->sessions,
        sessions: $ctx->sessions,
        snapshots: $ctx->progress, // the in-memory repo doubles as the snapshot reader
        stats: $ctx->stats,
        profile: new FakeLearnerProfileReader(),
        tx: new ImmediateTransactionManager(),
        clock: new FixedClock(new DateTimeImmutable('2026-07-27T12:00:00Z')),
    );
}
