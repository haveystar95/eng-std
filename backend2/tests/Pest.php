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
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

        // THE VOICE AND THE PHOTOS OF A PLAN LAND ON A TEST DISK — for the whole Feature suite, not file
        // by file (наряд ACC-1 §4), and for the same reason as the fakes above: the file that forgets is
        // the file that leaks. Every test that walks a talk or voices a scene has the fake synthesizer
        // write its mp3s through the plan's disk, and the files that never faked it wrote them into the
        // tree's REAL `storage/app/private/plan-audio` — a serial run of the Plan folder left 57 files of
        // talks and 280 of scenes there. A file that wants the disk to itself fakes it again; the fake is
        // taken down after every test ({@see testDisks()}).
        foreach (testDisks() as $disk) {
            Storage::fake($disk);
        }
    })
    // …and the test disk goes with the test: after a run the tree's `storage` holds nothing new (§4). ONLY a test disk:
    // a disk that is not faked any more — a line above taken out, a test that set the real one back — has the tree's own
    // storage for its root, and in the main tree that is the production voice. Checked by a mutant that removed the
    // fake: the unguarded delete took `storage/app/private` with it.
    ->afterEach(function (): void {
        $scratch = storage_path('framework/testing/disks/');
        foreach (testDisks() as $disk) {
            $root = Storage::disk($disk)->path('');
            if (str_starts_with($root, $scratch)) {
                (new Filesystem)->deleteDirectory($root);
            }
        }
    })
    ->in('Feature');

/**
 * THE DISKS A FEATURE TEST NEVER WRITES FOR REAL (наряд ACC-1 §4): the plan's voice and the square copies of its photos —
 * the only files the application keeps on a disk of its own. Both are `local` unless configured apart.
 *
 * @return list<string>
 */
function testDisks(): array
{
    return array_values(array_unique([
        (string) config('plan.audio_disk', 'local'),
        (string) config('plan.image_disk', 'local'),
    ]));
}

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
    foreach (['words', 'phrases', 'dialogue', 'listen', 'speak', 'recall', 'repetition'] as $stage) {
        $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/plans/{$id}/days/{$number}/stages/{$stage}/close")->assertOk();
    }
    planTalkThrough($ctx, $token, $id, $number);

    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/{$number}/close")->assertOk()->json('data');
}

/**
 * THE SIXTH STAGE, WALKED (наряд CONV-1): start the talk and say something every turn until the role
 * says goodbye. `FakePlanModel` plays the role, so this buys nothing and ends where the turn limit
 * ends — which is exactly the rule «день пройден = шесть этапов насквозь» a closing day checks.
 *
 * A day dealt before the talk existed has no conversation stage: the 422 it answers with is the
 * right answer, and the day closes on its five.
 *
 * @return array<string, mixed>|null the talk as it ended, or null when the day has none
 */
function planTalkThrough(object $ctx, string $token, string $id, int $number, bool $again = false): ?array
{
    $start = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/{$number}/conversation", ['again' => $again]);
    if ($start->getStatusCode() === 422) {
        return null;
    }
    $talk = $start->assertOk()->json('data');

    $guard = 0;
    while ($talk['state'] !== 'ended' && $guard++ < 40) {
        $talk = $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'My lower back hurts.'])
            ->assertOk()->json('data');
    }

    return $talk;
}

function planShiftDay(string $planId, int $days = 1): void
{
    Artisan::call('plan:shift-day', ['plan' => $planId, '--days' => $days]);
}

/**
 * THE LANGUAGE PACKS AS THE DEPLOYMENT HAS THEM (наряд GEN-2b) — read from `config/lesson/lang/*.php` by path, so a
 * Unit test that boots no application checks a lesson with the very words production reads.
 *
 * EVERY FILE OF THE DIRECTORY (наряд LANG-1 §1), not a list of codes kept here: a pack a language executor adds is read
 * by every test the day it lands, as production reads it — and a test that needs a language WITHOUT a pack builds one
 * ({@see App\Modules\Plan\Domain\Check\Language\LanguagePack::none()}), it does not borrow a real pack that happens to
 * be empty today. Read once per process: the packs are immutable, and the directory does not change under a run.
 */
function lessonPacks(): App\Modules\Plan\Domain\Check\Language\LanguagePacks
{
    static $deployed = null;
    if ($deployed === null) {
        $packs = [];
        foreach (glob(dirname(__DIR__).'/config/lesson/lang/*.php') ?: [] as $file) {
            $packs[basename($file, '.php')] = require $file;
        }
        ksort($packs);
        $deployed = new App\Modules\Plan\Domain\Check\Language\LanguagePacks($packs);
    }

    return $deployed;
}

/**
 * THE FAKE'S DAY AS THE BUILD STORES IT (наряд GEN-4) — its skeleton, and its lesson assembled from the skeleton and the
 * dialogue with the options shuffled by the scene's seed, spoken in the fake's roles: exactly what {@see
 * App\Modules\Plan\Application\Service\LessonBuildService} writes for a clean day.
 *
 * @return array{0: App\Modules\Plan\Domain\Lesson\Skeleton, 1: App\Modules\Plan\Domain\Lesson\Lesson}
 */
function planFixtureDay(App\Modules\Plan\Application\Dto\LessonRequest $request): array
{
    $parser = new App\Modules\Plan\Domain\Lesson\LessonParser;
    $skeleton = $parser->skeleton(App\Modules\Plan\Infrastructure\Model\FakePlanModel::skeletonPayload($request));
    $dialogue = $parser->dialogue(App\Modules\Plan\Infrastructure\Model\FakePlanModel::dialoguePayload(new App\Modules\Plan\Application\Dto\DialogueRequest($request, $skeleton)));
    $dialogue = App\Modules\Plan\Domain\Lesson\OptionShuffle::of($dialogue, $request->sceneId);

    return [$skeleton, App\Modules\Plan\Domain\Lesson\LessonAssembler::assemble($skeleton, $dialogue)->withRoles($request->roles)];
}

/**
 * The skeleton a day written as one lesson stands on — the lesson split as the fake splits it
 * ({@see App\Modules\Plan\Infrastructure\Model\FakePlanModel::stagesOf()}): what a scene stores beside a lesson a test
 * writes by hand.
 *
 * @param  array<string, mixed>  $payload
 */
function planSkeletonOf(array $payload, ?App\Modules\Plan\Application\Dto\LessonRequest $request = null): App\Modules\Plan\Domain\Lesson\Skeleton
{
    $request ??= App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonRequest();

    return (new App\Modules\Plan\Domain\Lesson\LessonParser)->skeleton(App\Modules\Plan\Infrastructure\Model\FakePlanModel::stagesOf($payload, $request)['skeleton']);
}

/**
 * Every scene of a plan in hand gets the fake's clean day, its photos found — a plan whose days may be opened as far as
 * their lessons go (наряд GEN-3 §11: a day next in line without its lesson is `building`).
 */
function planWriteLessons(App\Modules\Plan\Domain\Entity\Plan $plan): void
{
    foreach ($plan->scenes() as $scene) {
        [$skeleton, $lesson] = planFixtureDay(App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonRequest('x', $plan->earlierDaysOf($scene->id()), $scene->id()->value));
        $scene->acceptLesson(
            $lesson,
            $skeleton,
            lessonPacks()->for('en'),
            new App\Modules\Plan\Domain\ValueObject\ModelCall('lesson_skeleton.v1.1+lesson_dialogue.v1.1', 'test', 'fake', '0.000000', 1, 2),
            [],
            new DateTimeImmutable('2026-09-10T09:00:00Z'),
        );
        $scene->finishIllustration(new DateTimeImmutable('2026-09-10T09:05:00Z'));
    }
}

/**
 * A STORED LESSON SERVED AS THE BUILD WOULD HAVE STORED IT (наряд GEN-4): the options of every check and every listening
 * question put where the seed puts them ({@see App\Modules\Plan\Domain\Lesson\OptionShuffle}) — a lesson built by the
 * two stages is stored so — and then served. A fixture written as a model writes it reads here as the day deals it.
 */
function planServed(App\Modules\Plan\Domain\Lesson\Lesson $answer, string $seed, App\Modules\Plan\Domain\Check\Language\LanguagePack $target): App\Modules\Plan\Domain\Lesson\Lesson
{
    return App\Modules\Plan\Domain\Lesson\LessonAssembly::serve(App\Modules\Plan\Domain\Lesson\OptionShuffle::lesson($answer, $seed), $target);
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
    $payload ??= App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload(App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonRequest());

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
    $lesson = planServed((new App\Modules\Plan\Domain\Lesson\LessonParser)->parse($payload), $id->value, $packs->for('en'));
    $terms = planTermsOf($id, $lesson);

    return new App\Modules\Plan\Domain\Assembly\SceneMaterial($id, $lesson, $terms, $packs->for('en'), $packs->for('ru'), $unreadable);
}

/**
 * THE UNITS OF A SERVED LESSON as the server writes them for an English scene of a Russian learner — each sentence put
 * together by its language's rule of sentence ends (наряд FIX-4 §6: «3 p.m.» closes «I can come at ___.» with one dot).
 *
 * @return list<App\Modules\Plan\Domain\Entity\PlanTerm>
 */
function planTermsOf(App\Modules\Plan\Domain\ValueObject\PlanSceneId $sceneId, App\Modules\Plan\Domain\Lesson\Lesson $lesson): array
{
    $packs = lessonPacks();

    return App\Modules\Plan\Domain\Entity\PlanTerm::fromLesson(
        $sceneId, $lesson, static fn (): App\Modules\Plan\Domain\ValueObject\PlanTermId => App\Modules\Plan\Domain\ValueObject\PlanTermId::generate(),
        $packs->for('en')->sentenceEnds(), $packs->for('ru')->sentenceEnds(),
    );
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

/**
 * The bar Learning's whole-line reading is held to, over the ONE rule in the kernel (наряд FIX-2, п. 2): this module
 * has no language pack — a collection is in any language, and its articles are taken off both sides before the count
 * — so nothing is forgiven here beyond what the rule forgives everyone.
 */
function learningCovers(string $heard, string $expected): bool
{
    return (new App\Modules\Shared\Domain\Service\SpeechMatch)->covers(
        $heard, $expected,
        (new App\Modules\Learning\Domain\ValueObject\SpeechGradingRules)->wholeLine,
        App\Modules\Shared\Domain\ValueObject\SpeechPack::none(),
    );
}

/** The share of the expected sentence's words heard, by the same rule. */
function learningRatio(string $heard, string $expected): float
{
    return (new App\Modules\Shared\Domain\Service\SpeechMatch)->ratio(
        $heard, $expected, App\Modules\Shared\Domain\ValueObject\SpeechPack::none(),
    );
}

/**
 * THE OWNER'S GYM PLAN, DAYS 1 AND 2, AS THE LIVE SERVER HELD THEM (GYM-DUMP-2, read-only, 22.09.2026) — for the learner
 * given: the plan, its days, its two scenes with their lessons, their terms, every dealt and answered card of both days
 * and the scenes' voice files — each file under the key the pack of THIS environment gives its (role, gender), so the
 * index finds it the way it found it on the server. Nothing is regenerated: this is the material the наряд FIX-3 is about.
 *
 * @return string the plan's id
 */
function planGymLoad(string $userId): string
{
    $data = json_decode((string) file_get_contents(__DIR__.'/Fixtures/plan-gym/zal-days-1-2.json'), true, flags: JSON_THROW_ON_ERROR);
    $row = static function (array $r) use ($userId): array {
        foreach ($r as $k => $v) {
            if (is_array($v)) {
                $r[$k] = json_encode($v, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
        }
        if (array_key_exists('user_id', $r)) {
            $r['user_id'] = $userId;
        }

        return $r;
    };
    DB::table('plans')->insert($row($data['plan']));
    foreach ($data['scenes'] as $scene) {
        DB::table('plan_scenes')->insert($row($scene));
    }
    foreach ($data['days'] as $day) {
        DB::table('plan_days')->insert($row($day));
    }
    foreach ($data['terms'] as $term) {
        DB::table('plan_terms')->insert($row($term));
    }
    foreach ($data['cards'] as $card) {
        DB::table('day_cards')->insert($row($card));
    }
    foreach ($data['audios'] as $audio) {
        [$speaker, $gender] = $audio['voice'];
        unset($audio['voice']);
        $audio['voice_key'] = (string) app(App\Modules\Plan\Application\Port\LineSpeaker::class)->voiceKeyFor(
            'en', App\Modules\Plan\Domain\ValueObject\Speaker::from($speaker), App\Modules\Shared\Domain\ValueObject\VoiceGender::from($gender),
        );
        DB::table('plan_line_audios')->insert($row($audio));
    }

    return (string) $data['plan']['id'];
}

/**
 * THE CLEAN FIXTURE DAY as one lesson — the doctor's visit the default fake writes
 * ({@see \App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload()}):
 * for `new FakePlanModel(lesson: …)`, a test that changes what a day says and reads it back.
 *
 * @return array<string, mixed>
 */
function planCleanLesson(App\Modules\Plan\Application\Dto\LessonRequest $request): array
{
    return App\Modules\Plan\Infrastructure\Model\FakePlanModel::lessonPayload($request);
}

/**
 * THE CANON DAY OF THE STAGES' CHECKS (наряд GEN-4) — the day of the architect's own TEST INPUT (`lesson_dialogue.v1.1`: ru→ro,
 * the candidate's work experience, a male learner): its survival set, its skeleton, and a dialogue written to it that breaks
 * no rule, in `tests/Fixtures/plan-day/`. Every rule of `SkeletonCheck` and `DialogueCheck` is tested on it with one defect.
 *
 * @return array<string, mixed>
 */
function dayCanonJson(string $part): array
{
    return json_decode((string) file_get_contents(__DIR__."/Fixtures/plan-day/interview-ro-{$part}.json"), true, flags: JSON_THROW_ON_ERROR);
}

/** @param (Closure(array<string, mixed>): array<string, mixed>)|null $edit */
function dayCanonSkeleton(?Closure $edit = null): App\Modules\Plan\Domain\Lesson\Skeleton
{
    $raw = dayCanonJson('skeleton');

    return (new App\Modules\Plan\Domain\Lesson\LessonParser)->skeleton($edit === null ? $raw : $edit($raw));
}

/** @param (Closure(array<string, mixed>): array<string, mixed>)|null $edit */
function dayCanonDialogue(?Closure $edit = null): App\Modules\Plan\Domain\Lesson\Dialogue
{
    $raw = dayCanonJson('dialogue');

    return (new App\Modules\Plan\Domain\Lesson\LessonParser)->dialogue($edit === null ? $raw : $edit($raw));
}

/**
 * The canon skeleton's raw JSON with one frame, partner line or word replaced by what `$edit` makes of it.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
 * @return Closure(array<string, mixed>): array<string, mixed>
 */
function scAt(string $list, string $id, Closure $edit): Closure
{
    return static function (array $raw) use ($list, $id, $edit): array {
        foreach ($raw[$list] as $i => $row) {
            if ($row['id'] === $id) {
                $raw[$list][$i] = $edit($row);
            }
        }

        return $raw;
    };
}

/**
 * The canon dialogue's raw JSON with the exchange of `$step` replaced by what `$edit` makes of it.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
 * @return Closure(array<string, mixed>): array<string, mixed>
 */
function dcAt(int $step, Closure $edit): Closure
{
    return static function (array $raw) use ($step, $edit): array {
        foreach ($raw['dialogue'] as $i => $exchange) {
            if ($exchange['step'] === $step) {
                $raw['dialogue'][$i] = $edit($exchange);
            }
        }

        return $raw;
    };
}

/**
 * The same, with one message of the exchange — the learner's (`B`) or the partner's (`A`) — edited.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
 * @return Closure(array<string, mixed>): array<string, mixed>
 */
function dcSaid(int $step, string $speaker, Closure $edit): Closure
{
    return dcAt($step, static function (array $exchange) use ($speaker, $edit): array {
        foreach ($exchange['messages'] as $i => $message) {
            if ($message['speaker'] === $speaker) {
                $exchange['messages'][$i] = $edit($message);
            }
        }

        return $exchange;
    });
}

/**
 * The same, with the listening question `$number` (from 1) edited.
 *
 * @param  Closure(array<string, mixed>): array<string, mixed>  $edit
 * @return Closure(array<string, mixed>): array<string, mixed>
 */
function dcHeard(int $number, Closure $edit): Closure
{
    return static function (array $raw) use ($number, $edit): array {
        $raw['listening']['questions'][$number - 1] = $edit($raw['listening']['questions'][$number - 1]);

        return $raw;
    };
}

function dayCanonSurvival(): App\Modules\Plan\Domain\Blueprint\SurvivalSet
{
    $raw = dayCanonJson('survival');

    return App\Modules\Plan\Domain\Blueprint\SurvivalSet::fromModel($raw['must_say'], $raw['must_understand']);
}

function dayCanonSkeletonContext(
    ?App\Modules\Shared\Domain\ValueObject\VoiceGender $gender = App\Modules\Shared\Domain\ValueObject\VoiceGender::Male,
    ?App\Modules\Plan\Domain\Lesson\EarlierDays $earlier = null,
    ?App\Modules\Plan\Domain\Blueprint\SurvivalSet $survival = null,
    string $native = 'ru',
    string $target = 'ro',
): App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext {
    return new App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext(
        $survival ?? dayCanonSurvival(), 8, 12, lessonPacks()->for($native), lessonPacks()->for($target), $gender,
        $earlier ?? new App\Modules\Plan\Domain\Lesson\EarlierDays,
    );
}

function dayCanonDialogueContext(?App\Modules\Plan\Domain\Lesson\Skeleton $skeleton = null): App\Modules\Plan\Domain\Check\Dialogue\DialogueContext
{
    return new App\Modules\Plan\Domain\Check\Dialogue\DialogueContext($skeleton ?? dayCanonSkeleton(), lessonPacks()->for('ru'), lessonPacks()->for('ro'));
}

/**
 * What the skeleton's check finds, as `code@address` — each once, in the order found.
 *
 * @return list<string>
 */
function skeletonFound(App\Modules\Plan\Domain\Lesson\Skeleton $skeleton, ?App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext $context = null): array
{
    return array_values(array_unique(array_map(
        static fn (App\Modules\Plan\Domain\Check\LessonViolation $v): string => "{$v->code}@{$v->address}",
        (new App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck)->run($skeleton, $context ?? dayCanonSkeletonContext()),
    )));
}

/**
 * What the dialogue's check finds, as `code@address` — each once, in the order found.
 *
 * @return list<string>
 */
function dialogueFound(App\Modules\Plan\Domain\Lesson\Dialogue $dialogue, ?App\Modules\Plan\Domain\Check\Dialogue\DialogueContext $context = null): array
{
    return array_values(array_unique(array_map(
        static fn (App\Modules\Plan\Domain\Check\LessonViolation $v): string => "{$v->code}@{$v->address}",
        (new App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck)->run($dialogue, $context ?? dayCanonDialogueContext()),
    )));
}
