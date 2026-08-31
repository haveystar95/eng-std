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
use App\Modules\Shared\Domain\ValueObject\Ulid;
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
 * Put the OFFLINE plan model behind both of a plan's paid calls — and PROVE it landed.
 *
 * The proof is the point of this helper existing at all. Every plan test file used to write these
 * two `instance()` calls by hand, and one of them wrote the port's name with the wrong namespace
 * (`Generation\…\PlanOutlinePort`; the port lives in `Learning`). The container takes any string as
 * a key, so the binding «succeeded», resolved nothing, and the real adapter served the suite —
 * ≈39 `gpt-5.4` calls, symptom: a slow test.
 *
 * A binding under a name nobody resolves cannot be told from a working one by looking at the
 * binding. It can be told by RESOLVING THE PORT BACK, which is what the assertions below do, once,
 * for everybody. {@see \App\Modules\Generation\Infrastructure\Adapter\LiveModelGuard} is the other
 * half: it stops the real adapter being built at all.
 */
function fakePlanModel(): void
{
    $model = new \App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel();
    $prompts = new \App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary();
    $ledger = app(\App\Modules\Generation\Application\Port\RecordsPlanSpend::class);

    $outlines = new \App\Modules\Generation\Application\Service\PlanOutlineService(
        $model,
        $prompts,
        $ledger,
        app(\App\Modules\Generation\Application\Port\PlanDefectReporter::class),
    );
    $days = new \App\Modules\Generation\Application\Service\PlanDayComposer(
        $model,
        $prompts,
        $ledger,
        app(\App\Modules\Generation\Application\Port\PlanDefectReporter::class),
    );

    app()->instance(\App\Modules\Learning\Application\Port\PlanOutlinePort::class, $outlines);
    app()->instance(\App\Modules\Generation\Application\Service\PlanDayComposer::class, $days);

    // Resolve them back. Without this the helper would be exactly as trustworthy as the two lines
    // it replaced, which is to say not at all.
    expect(app(\App\Modules\Learning\Application\Port\PlanOutlinePort::class))->toBe($outlines)
        ->and(app(\App\Modules\Generation\Application\Service\PlanDayComposer::class))->toBe($days);
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

// ── plan sessions ─────────────────────────────────────────────────────────────────────────────
//
// Shared by every file that studies a plan day. They live here rather than in one test file because
// a second file needing them is how a copy gets made, and two copies of «start a plan» drift.

/** A started plan: draft → outline → start, with day 1 generated inside the request. */
function startedPlan(object $ctx, array $overrides = []): array
{
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
            ...$overrides,
        ])
        ->assertCreated()
        ->json('data');

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")
        ->assertOk();
    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")
        ->assertOk();

    return [$user, $token, $plan['id']];
}

function planSession(object $ctx, string $token, string $planId, ?int $dayIndex = null): array
{
    $url = $dayIndex === null
        ? "/api/v1/plans/{$planId}/session"
        : "/api/v1/plans/{$planId}/days/{$dayIndex}/session";

    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson($url)->assertOk()->json('data');
}

/** Answer every task of a session correctly, in the order it was dealt. */
function answerTasks(object $ctx, string $token, array $session, int $seq = 1, ?string $at = null): int
{
    $reviews = [];
    $exposures = [];

    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        if ($card['exercise_mode'] === 'intro') {
            $exposures[] = ['term_id' => $card['term_id'], 'shown_at' => $at ?? now()->toIso8601String()];

            continue;
        }
        $reviews[] = [
            'id' => (string) Ulid::generate(),
            'term_id' => $card['term_id'],
            'exercise_mode' => $card['exercise_mode'],
            'response' => $card['answer'],
            'answered_at' => $at ?? now()->toIso8601String(),
            'client_seq' => $seq++,
            'session_id' => $session['session_id'],
            'ladder_step' => $card['ladder_step'],
        ];
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews, 'exposures' => $exposures])
        ->assertOk();

    return $seq;
}

/**
 * PUSH THE LEARNER'S HISTORY BACK `$days` DAYS — the same trick `qa:time-travel` plays on a real
 * account, and the reason a plan test uses it instead of moving the clock.
 *
 * The server reads {@see Clock}, whose `SystemClock` builds a plain `DateTimeImmutable` that
 * `Carbon::setTestNow` never reaches; binding a `FixedClock` mid-test works for a container the test
 * itself resolves from and did not reach the handler here. Ageing the ROWS is the honest inversion:
 * «a night has passed» and «this word was answered a week ago» are statements about stored data, and
 * moving the data is both simpler and closer to what actually happens to a learner.
 */
function ageHistory(string $userId, int $days): void
{
    $shift = "INTERVAL '{$days} days'";
    DB::statement("UPDATE reviews SET answered_at = answered_at - {$shift}, created_at = created_at - {$shift} WHERE user_id = ?", [$userId]);
    DB::statement("UPDATE term_exposures SET shown_at = shown_at - {$shift} WHERE user_id = ?", [$userId]);
    DB::statement("UPDATE user_term_progress SET due_at = due_at - {$shift}, last_reviewed_at = last_reviewed_at - {$shift} WHERE user_id = ?", [$userId]);
}

/**
 * Play day `$dayIndex` until the focus leaves it — the session's card budget can be smaller than the
 * day's whole checklist, so «пройти день» is more than one sitting and the test has to say so.
 */
function walkDay(object $ctx, string $token, string $planId, int $dayIndex, int $seq = 1): int
{
    for ($i = 0; $i < 8; $i++) {
        $session = planSession($ctx, $token, $planId);
        if ($session['focus_day_index'] !== $dayIndex || $session['tasks'] === []) {
            break;
        }
        $seq = answerTasks($ctx, $token, $session, $seq);
    }

    return $seq;
}
