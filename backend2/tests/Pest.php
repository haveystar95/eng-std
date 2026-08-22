<?php

declare(strict_types=1);

use App\Modules\Admin\Infrastructure\Eloquent\Admin;
use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslator;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Command\EnrollTerm;
use App\Modules\Learning\Application\Command\EnrollTermHandler;
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
 * Answer a term N times correctly over HTTP, one answer per «day» ending `$lastDaysAgo` days ago.
 *
 * Every pair now starts on the ACQUISITION LADDER: the first two correct answers are its
 * recognition steps and reach no scheduler at all, so a test that wants an SM-2 state has to walk
 * the pair off the ladder first. One answer after that enters SM-2 exactly where one answer used to.
 */
function answerTimes(object $ctx, string $token, string $termId, string $response, int $times, int $lastDaysAgo = 0): void
{
    $reviews = [];
    for ($i = 0; $i < $times; $i++) {
        $reviews[] = [
            'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $termId,
            'exercise_mode' => 'typing',
            'response' => $response,
            'answered_at' => now()->subDays($lastDaysAgo + $times - 1 - $i)->toIso8601String(),
            'client_seq' => $i + 1,
        ];
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews])
        ->assertOk();
}

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
function adminSeedTerm(User $user, string $title, string $text, string $translation = 'x', bool $enroll = true): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $title, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;
    if ($enroll) {
        enrollTerm($user, $termId);
    }

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

/**
 * Put one term into the user's POOL — the deliberate act that makes it studiable at all.
 *
 * Every seeding helper below does this by default, because «a word this user is studying» is what
 * almost every Learning test means by seeding one. Pass `enroll: false` to seed a word that sits in
 * the catalogue only; that is the case the pool gate exists for, and PoolApiTest leans on it.
 */
function enrollTerm(User $user, string $termId): void
{
    app(EnrollTermHandler::class)(new EnrollTerm(
        UserId::fromString($user->id), TermId::fromString($termId),
    ));
}

/** Create a collection + word for the user and return the term id (no HTTP). */
function seedWordFor(User $user, string $text = 'apple', string $translation = 'яблоко', bool $enroll = true): string
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Fruit', new LanguageCode('ru'), new LanguageCode('en'),
    ));

    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;
    if ($enroll) {
        enrollTerm($user, $termId);
    }

    return $termId;
}

/**
 * Like {@see seedWordFor} but also returns the collection id.
 *
 * @return array{0: string, 1: string}  [collectionId, termId]
 */
function seedCollectionWith(User $user, string $text, string $translation = 'x', bool $enroll = true): array
{
    $actor = UserId::fromString($user->id);
    $collectionId = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, $text, new LanguageCode('ru'), new LanguageCode('en'),
    ));
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection($collectionId, $actor, $text, $translation))->value;
    if ($enroll) {
        enrollTerm($user, $termId);
    }

    return [$collectionId->value, $termId];
}

/** Add a word to an existing collection (no HTTP) and return the term id. */
function addWordTo(string $collectionId, string $userId, string $text, string $translation = 'x', bool $enroll = true): string
{
    $termId = app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        CollectionId::fromString($collectionId), UserId::fromString($userId), $text, $translation,
    ))->value;
    if ($enroll) {
        app(EnrollTermHandler::class)(new EnrollTerm(UserId::fromString($userId), TermId::fromString($termId)));
    }

    return $termId;
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

/**
 * Bind the counting translator and hand it back, so a test can assert the vendor was NOT called.
 *
 * `app()` and not `$ctx->app`: the container property is protected, and this lives outside the
 * TestCase's scope by design (see the note at the top of this file).
 */
function fakeTranslator(): FakeTranslator
{
    $fake = new FakeTranslator();
    app()->instance(TranslationProvider::class, $fake);

    return $fake;
}

/**
 * `GET /search/instant`, unwrapped. Always a 200 for a supported pair — the endpoint has no error
 * path of its own; only an unserved language pair is refused, and that is a 422 the client cannot
 * reach through the pill.
 *
 * `$source`/`$target` are the pill. Omit both to let the learner's profile pair stand in.
 *
 * @return array<string, mixed>
 */
function instant(object $ctx, string $token, string $query, ?string $source = null, ?string $target = null): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/search/instant?' . http_build_query(array_filter([
            'q' => $query,
            'source' => $source,
            'target' => $target,
        ], static fn (?string $v): bool => $v !== null)))
        ->assertOk()
        ->json('data');
}
