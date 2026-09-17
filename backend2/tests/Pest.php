<?php

declare(strict_types=1);

use App\Modules\Admin\Infrastructure\Eloquent\Admin;
use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Collections\Application\Service\DefaultCollectionPair;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Generation\Application\Port\TermEnricherPort;
use App\Modules\Generation\Application\Port\TermTransliteratorPort;
use App\Modules\Generation\Application\Port\TranslationProvider;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\FakePexelsImageSearch;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermEnricher;
use App\Modules\Generation\Infrastructure\Adapter\FakeTermTransliterator;
use App\Modules\Generation\Infrastructure\Adapter\FakeTranslator;
use App\Modules\Generation\Infrastructure\Adapter\FakeWordLookup;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
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
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\FakeDefaultTargetLangReader;
use Tests\Doubles\FakeLatencyMedianReader;
use Tests\Doubles\FakeLearnerProfileReader;
use Tests\Doubles\FakeNativeLangReader;
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

pest()->extend(TestCase::class)
    // EVERY VENDOR THIS SUITE CAN REACH BY ACCIDENT IS FAKED HERE, for the whole Feature suite and
    // not file by file — because the file that forgets is the file that spends money.
    //
    // The queue is `sync` under test, so the two doors that build a single card — `POST /search/add`
    // and a word typed into a folder — run their follow-up jobs INSIDE the request: the reading hint,
    // the станок, the bare-term enricher and the stock photo. Dozens of tests walk through those
    // doors for reasons that have nothing to do with any of that.
    //
    // Measured before this bound anything but the transliterator: one full run put 289 unfaked calls
    // on the wire to `api.openai.com` and 20 to `api.pexels.com`. Worse, they were INVISIBLE — the
    // станок catches a throwing term and counts it (BuildTermEnrichmentsHandler), so a suite that was
    // buying gpt-4o-mini on every run looked exactly like a suite that was not.
    //
    // A test that wants to WATCH one of these calls binds its own double over the instance, or —
    // when it wants the real adapter with `Http::fake()` underneath — forgets the instance first
    // (`app()->forgetInstance(...)`, see MachineryStanokTest::livePacker()).
    ->beforeEach(function (): void {
        app()->instance(TermTransliteratorPort::class, new FakeTermTransliterator());
        // The станок: one gpt-4o-mini call per term, chained onto every newly saved word.
        app()->instance(EnrichmentPackerPort::class, new FakeEnrichmentPacker());
        // «Учить это слово»: one call per bare term added to a folder without a translation.
        app()->instance(TermEnricherPort::class, new FakeTermEnricher());
        // `POST /search/lookup` — the one paid call a LEARNER can trigger by typing. Most files that
        // use it already bind this fake; the one that forgot bought a live lookup on every run.
        app()->instance(WordLookupPort::class, new FakeWordLookup());
        // Not a model and not billed, but the same shape of accident: the photo job follows the
        // enricher, so faking the enricher without this one merely moves the stray call downstream.
        app()->instance(ImageSearchPort::class, new FakePexelsImageSearch(FakePexelsImageSearch::FOUND));
    })
    ->in('Feature');

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
 * Give the learner a profile row with these fields. `learner()` creates a user with NO profile —
 * every default the profile carries is a column default the readers never see — so a test about
 * anything the profile decides has to write one.
 *
 * @param  array<string, mixed>  $attrs
 */
function profileFor(User $user, array $attrs): void
{
    Profile::updateOrCreate(['user_id' => $user->id], $attrs);
}

/**
 * A {@see DefaultCollectionPair} over fakes, for the unit tests that build a collection handler by
 * hand. Defaults to `ru→en`, which is what every fixture in this suite means by «the usual pair».
 */
function defaultPair(string $support = 'ru', string $studied = 'en'): DefaultCollectionPair
{
    return new DefaultCollectionPair(new FakeNativeLangReader($support), new FakeDefaultTargetLangReader($studied));
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
 * «THIS TEST IS ABOUT THE REAL ADAPTER» — the one way past
 * {@see \App\Modules\Generation\Infrastructure\Adapter\LiveModelGuard}.
 *
 * The gate refuses to construct any live vendor adapter under `APP_ENV=testing`, whatever the
 * driver says. A handful of files legitimately want the real thing, because the adapter itself is
 * the subject: what it puts in the request body, which model name it sends, how it reads the
 * response. They call this, and they put `Http::fake()` underneath, so nothing reaches the wire.
 *
 * Call it BEFORE resolving the port, and grep for it before adding a fourth caller: every one of
 * them is a place where a mistake becomes an invoice.
 */
function allowLiveAdapters(): void
{
    config([\App\Modules\Generation\Infrastructure\Adapter\LiveModelGuard::OPT_IN => true]);
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

/**
 * Seed one row of `term_examples` — and its translation, when the test supplies one.
 *
 * A test used to write the whole example with a single `DB::table('term_examples')->insert()`, which
 * stopped being one row the moment an example gained a `lang` of its own. Rather than have two dozen
 * test files each remember to derive the sentence's language from its term, the derivation lives
 * here once: `lang` defaults to the TERM's language, which is what the writers do and what the
 * backfill did.
 *
 * `translation` is the example's translation and `translation_lang` the language it is written in
 * (defaulting to `ru`, the only support language the fixtures use). They are NOT columns of
 * `term_examples` — that is the point.
 *
 * @param  array<string, mixed>  $attrs  columns of `term_examples`, plus `translation` /
 *                                       `translation_lang`. `id` and the timestamps are filled in.
 * @return string  the example's id
 */
function seedExample(array $attrs): string
{
    $translation = $attrs['translation'] ?? null;
    $translationLang = (string) ($attrs['translation_lang'] ?? 'ru');
    unset($attrs['translation'], $attrs['translation_lang']);

    $attrs['id'] ??= \App\Modules\Shared\Domain\ValueObject\Ulid::generate();
    $attrs['lang'] ??= (string) DB::table('terms')->where('id', $attrs['term_id'])->value('lang');
    $attrs['created_at'] ??= now();
    $attrs['updated_at'] ??= now();

    DB::table('term_examples')->insert($attrs);

    if (is_string($translation) && $translation !== '') {
        DB::table('example_translations')->insert([
            'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_example_id' => $attrs['id'],
            'lang' => $translationLang,
            'text' => $translation,
            'created_at' => $attrs['created_at'],
            'updated_at' => $attrs['updated_at'],
        ]);
    }

    return (string) $attrs['id'];
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

/**
 * THE PLAN OVER HTTP (docs/plan-api.md): the queue is `sync` under test, so a created plan is built
 * inside the request by the fake model, day 1's lesson right after it — exactly the order the
 * canon asks for (§4), only without the wait.
 *
 * @return array{0: User, 1: string}
 */
function planLearner(string $tz = 'UTC'): array
{
    [$user, $token] = learner();
    profileFor($user, ['daily_goal' => 10, 'timezone' => $tz, 'native_language' => 'ru', 'target_language' => 'en']);

    return [$user, $token];
}

function planCreate(object $ctx, string $token, array $overrides = []): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', $overrides + [
            'goal_text' => 'Иду к врачу с ребёнком, болит спина. Первый раз в местной клинике.',
            'target_lang' => 'en',
            'level' => 'beginner',
            'days_total' => 5,
        ])
        ->assertStatus(202)
        ->json('data');
}

function planRead(object $ctx, string $token, string $id): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}")->assertOk()->json('data');
}

function planOpenDay(object $ctx, string $token, string $id, int $number): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/{$number}/open")->assertOk()->json('data');
}

/**
 * One answer over HTTP, and its reply (наряд SESSION-1a, D-02): `{card, requeued, unit {kind, ref, returns_tomorrow,
 * returns_day}, day {cards_total, cards_done, minutes_spent}, stage {stage, minutes_spent}}`.
 *
 * @param  array<string, mixed>|null  $response  what the answer left behind (D-30), sent only when given
 */
function planAnswer(object $ctx, string $token, string $id, int $number, string $cardId, string $result, int $attempts = 1, ?array $response = null): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/{$number}/cards/{$cardId}/answer", ['result' => $result, 'attempts' => $attempts] + ($response === null ? [] : ['response' => $response]))
        ->assertOk()->json('data');
}

/**
 * The result a walk gives a card of this kind when nothing else is asked (наряд SESSION-1a, D-31): a judged card is
 * given up — its pass is the judge's, not the client's; a spoken card, a walkthrough and a choice pass.
 */
function planWalkResult(string $kind): string
{
    return App\Modules\Plan\Domain\ValueObject\CardKind::from($kind)->isJudged() ? 'skipped' : 'passed';
}

/**
 * Walk the whole day: every card given its kind's result ({@see planWalkResult}), except what `$script` says (card
 * index in walking order → [result, attempts]); a copy dealt at the end of a stage is walked too.
 */
function planWalkDay(object $ctx, string $token, string $id, int $number, array $script = []): array
{
    $cards = planOpenDay($ctx, $token, $id, $number)['cards'];
    $seen = [];
    $queue = $cards;
    $index = 0;
    while ($queue !== []) {
        $card = array_shift($queue);
        if (isset($seen[$card['id']]) || $card['result'] !== null) {
            continue;
        }
        $seen[$card['id']] = true;
        [$result, $attempts] = $script[$index] ?? [planWalkResult($card['kind']), 1];
        $index++;
        $outcome = planAnswer($ctx, $token, $id, $number, $card['id'], $result, $attempts);
        if ($outcome['requeued'] !== null) {
            $queue[] = $outcome['requeued'];
        }
    }
    foreach (['words', 'phrases', 'dialogue', 'listen', 'speak'] as $stage) {
        $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/{$number}/stages/{$stage}/close")->assertOk();
    }

    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/{$number}/close")->assertOk()->json('data');
}

function planShiftDay(string $planId, int $days = 1): void
{
    Artisan::call('plan:shift-day', ['plan' => $planId, '--days' => $days]);
}

/**
 * THE LANGUAGE PACKS AS THE DEPLOYMENT HAS THEM (наряд GEN-2b) — read from `config/lesson/lang/*.php` by path, so a
 * Unit test that boots no application checks a lesson with the very words production reads.
 */
function lessonPacks(): App\Modules\Plan\Domain\Check\Language\LanguagePacks
{
    $packs = [];
    foreach (['en', 'ru', 'uk', 'ro'] as $code) {
        $packs[$code] = require dirname(__DIR__)."/config/lesson/lang/{$code}.php";
    }

    return new App\Modules\Plan\Domain\Check\Language\LanguagePacks($packs);
}

/**
 * What the validator is given for a lesson of the pair (`$native`, `$target`) ordered with 8 words and 8 exchanges — and,
 * for a later day of a plan (наряд GEN-3), the story so far with the scene's partner role.
 */
function lessonContext(
    string $native = 'ru',
    string $target = 'en',
    ?App\Modules\Shared\Domain\ValueObject\VoiceGender $gender = null,
    ?App\Modules\Plan\Domain\Lesson\EarlierDays $earlierDays = null,
    string $partnerRole = 'Doctor',
): App\Modules\Plan\Domain\Check\LessonValidationContext {
    $packs = lessonPacks();

    return new App\Modules\Plan\Domain\Check\LessonValidationContext(
        8, 8, $packs->for($native), $packs->for($target), $gender,
        $earlierDays ?? new App\Modules\Plan\Domain\Lesson\EarlierDays, $partnerRole,
    );
}

/**
 * WHAT THE FAKE'S CLEAN LESSON STILL BREAKS UNDER v4.6 (наряд GEN-3) — `code@address` of each finding. It was written to
 * v4.5 and says frame p6 in exchanges 7 and 8, one after the other; every test of the day's dealing reads that order, so
 * the lesson keeps it, and a test that asks for «no findings» of it asks for exactly these.
 *
 * @return list<string>
 */
function planFixtureWarnings(): array
{
    return ['frame.adjacent_repeat@x8'];
}

/**
 * Every scene of a plan in hand gets the fake's clean lesson, its photos found — a plan whose days may be opened as far as
 * their lessons go (наряд GEN-3 §11: a day next in line without its lesson is `building`).
 */
function planWriteLessons(App\Modules\Plan\Domain\Entity\Plan $plan): void
{
    foreach ($plan->scenes() as $scene) {
        $request = new App\Modules\Plan\Application\Dto\LessonRequest('x', 'x', 'English', 'Russian', $plan->level(), null, 8, 8, App\Modules\Plan\Infrastructure\Model\FakePlanModel::roles(), $plan->earlierDaysOf($scene->id()));
        $scene->acceptLesson(
            (new App\Modules\Plan\Domain\Lesson\LessonParser)->parse(App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload($request)),
            lessonPacks()->for('en'),
            new App\Modules\Plan\Domain\ValueObject\ModelCall('lesson_day.v4.7', 'test', 'fake', '0.000000', 1, 1),
            [],
            new DateTimeImmutable('2026-09-10T09:00:00Z'),
        );
        $scene->finishIllustration();
    }
}

/**
 * The fake's lesson for a request told so that it breaks no rule of v4.6 either: its exchanges 4 and 7 change places (and
 * the words' `used_in` with them), so frame p6 is said in exchanges 4 and 8, never twice in a row. The lesson a test asks
 * for «no findings» of.
 *
 * @return array<string, mixed>
 */
function planCleanLesson(App\Modules\Plan\Application\Dto\LessonRequest $request): array
{
    $p = App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload($request);
    [$p['dialogue'][3], $p['dialogue'][6]] = [$p['dialogue'][6], $p['dialogue'][3]];
    $p['dialogue'][3]['step'] = 4;
    $p['dialogue'][6]['step'] = 7;
    foreach ($p['vocabulary'] as $i => $item) {
        $p['vocabulary'][$i]['used_in'] = array_map(static fn (string $ref): string => ['A4' => 'A7', 'A7' => 'A4'][$ref] ?? $ref, $item['used_in']);
    }

    return $p;
}

/**
 * An earlier day of a plan (наряд GEN-3) made of a lesson payload — the fake's clean lesson unless one is given — as the
 * next day's lesson reads it.
 *
 * @param  array<string, mixed>|null  $payload
 */
function planEarlierDay(
    int $number = 1,
    ?array $payload = null,
    string $partnerRole = 'Doctor',
    App\Modules\Shared\Domain\ValueObject\VoiceGender $gender = App\Modules\Shared\Domain\ValueObject\VoiceGender::Female,
    string $title = 'Consultation',
): App\Modules\Plan\Domain\Lesson\EarlierDay {
    $payload ??= App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload(new App\Modules\Plan\Application\Dto\LessonRequest(
        'Приём у врача', 'x', 'English', 'Russian', App\Modules\Plan\Domain\ValueObject\PlanLevel::Beginner, null, 8, 8,
        App\Modules\Plan\Infrastructure\Model\FakePlanModel::roles(), new App\Modules\Plan\Domain\Lesson\EarlierDays,
    ));

    return App\Modules\Plan\Domain\Lesson\EarlierDay::of($number, $title, $partnerRole, $gender, (new App\Modules\Plan\Domain\Lesson\LessonParser)->parse($payload));
}

/**
 * THE LIVE DOCTOR'S LESSON (наряд SESSION-1e) — the model's answer of day 1 of the e2e plan «врач»
 * (`01M2H13E1QT6F5D4FKJSEKTAD7`, scene `01M2H13KSAS23K4YPF1M65SJQD`, `lesson_day.v4.4`) as stored, copied into
 * `tests/Fixtures/plan-lesson/doctor-e2e-v4.4.json`: the day the phone found the check of a word always an assembly on.
 * Served under the scene id given (seeds depend on it), with its terms, in English for a Russian learner — and the
 * seam-judge findings given, when a test wants some.
 *
 * @param  list<string>  $unreadable
 */
function planLiveDoctorScene(string $sceneId = '01M2H13KSAS23K4YPF1M65SJQD', array $unreadable = []): App\Modules\Plan\Domain\Assembly\SceneMaterial
{
    $id = App\Modules\Plan\Domain\ValueObject\PlanSceneId::fromString($sceneId);
    $packs = lessonPacks();
    $payload = json_decode((string) file_get_contents(__DIR__.'/Fixtures/plan-lesson/doctor-e2e-v4.4.json'), true, flags: JSON_THROW_ON_ERROR);
    $lesson = App\Modules\Plan\Domain\Lesson\LessonAssembly::serve((new App\Modules\Plan\Domain\Lesson\LessonParser)->parse($payload), $id->value, $packs->for('en'));
    $terms = App\Modules\Plan\Domain\Entity\PlanTerm::fromLesson($id, $lesson, static fn (): App\Modules\Plan\Domain\ValueObject\PlanTermId => App\Modules\Plan\Domain\ValueObject\PlanTermId::generate());

    return new App\Modules\Plan\Domain\Assembly\SceneMaterial($id, $lesson, $terms, $packs->for('en'), $packs->for('ru'), $unreadable);
}

/**
 * Scene ids under which the words' circle starts at each of its four places (наряд SESSION-1e): the start is picked by
 * the scene's seed, so «at any start» is tried by dealing the same lesson under these ids.
 *
 * @return array<string, string> the kind the circle starts with → a scene id
 */
function planCircleStarts(): array
{
    $found = [];
    for ($n = 1; count($found) < 4; $n++) {
        $id = sprintf('01J8SESS1ESTART%011d', $n);
        $found[App\Modules\Plan\Domain\Assembly\Rotation::pick($id.':words:check', 0, App\Modules\Plan\Domain\Assembly\WordChecks::CYCLE)->value] ??= $id;
    }
    ksort($found);

    return $found;
}
