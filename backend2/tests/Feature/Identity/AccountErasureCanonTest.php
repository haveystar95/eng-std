<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

/**
 * THE ACCOUNT GOES, AND NOTHING OF IT STAYS (наряд ACC-1 §1) — the canon of `DELETE /auth/me`:
 *
 * «после удаления нет ни одной строки с этим user_id ни в одной таблице (перебор по схеме), файлов в storage нет, токен
 * мёртв, model_calls на месте». The learner here has the whole spread — two plans (one deleted) with their days, cards,
 * a talk and its lines, the voice of a scene and of the talk on the disk, a photo copy, the journal and a letter; a pool
 * with reviews, triages, exposures, a session and a day's stats; a collection; a generation, a practice dialog and its
 * transcript, an example regenerated; a search lookup paid for; a push address and visits; a trainer override; an admin's
 * tier change; a right to the paid plan; the request log of every call above. Then every text column of every table of the schema is searched for
 * the id AND the email: none.
 */

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * Every column of the schema that can hold the id or the email as text, and how many rows of it do.
 *
 * @return array<string, int> «table.column» → rows that mention the needle (only the columns that do)
 */
function acc1Mentions(string $needle): array
{
    $columns = DB::select("SELECT table_name, column_name FROM information_schema.columns
        WHERE table_schema = 'public' AND data_type IN ('character', 'character varying', 'text', 'json', 'jsonb')
        ORDER BY table_name, column_name");
    $out = [];
    foreach ($columns as $c) {
        $hits = (int) DB::selectOne(
            sprintf('SELECT count(*) AS n FROM "%s" WHERE "%s"::text LIKE ?', $c->table_name, $c->column_name),
            ['%'.$needle.'%'],
        )->n;
        if ($hits > 0) {
            $out["{$c->table_name}.{$c->column_name}"] = $hits;
        }
    }

    return $out;
}

/**
 * A learner with everything the product can keep about a person — through the API where it is cheap, by rows where
 * the door is a vendor's. The voice is bought from the fake vendor, so the scene's lines and the talk's are real files.
 *
 * @return array{0: User, 1: string, 2: list<string>} the learner, their token, and the paths of their files on the disks
 */
function acc1LearnerWithEverything(object $ctx): array
{
    [$user, $token] = planLearner();
    $as = static fn () => $ctx->withHeader('Authorization', "Bearer {$token}");

    // Two plans — the second one deleted — and on the first: day 1 open, cards answered, a talk said aloud.
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $gone = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $as()->deleteJson("/api/v1/plans/{$gone}")->assertNoContent();
    $as()->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $cards = planOpenDay($ctx, $token, $id, 1)['cards'];
    foreach (array_slice($cards, 0, 3) as $card) {
        planAnswer($ctx, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    $talk = $as()->postJson("/api/v1/plans/{$id}/days/1/conversation")->assertOk()->json('data');
    $as()->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'My son has a fever.'])->assertOk();
    $scene = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    Storage::disk((string) config('plan.image_disk'))->put("plan-images/{$scene}/112.jpg", 'photo');

    // The account's own doors: the profile read back, a push address, a visit.
    $as()->getJson('/api/v1/auth/me')->assertOk();
    $as()->putJson('/api/v1/devices/push-token', ['platform' => 'ios', 'token' => str_repeat('a', 64)])->assertOk();
    $as()->postJson('/api/v1/devices/visit', ['timezone' => 'Europe/Bucharest'])->assertNoContent();

    // The pool, a collection and what the other modules keep, by rows.
    $termId = seedWordFor($user, 'apple', 'яблоко');
    answerTimes($ctx, $token, $termId, 'apple', 2);
    $collectionId = (string) DB::table('collections')->where('owner_id', $user->id)->value('id');
    $now = now();
    DB::table('term_triages')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId, 'verdict' => 'known', 'decided_at' => $now]);
    DB::table('term_exposures')->insert(['user_id' => $user->id, 'term_id' => $termId, 'shown_at' => $now, 'created_at' => $now]);
    DB::table('study_sessions')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'is_practice' => false, 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('generation_requests')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'prompt' => 'x', 'normalized_prompt' => 'x', 'source_lang' => 'ru',
        'target_lang' => 'en', 'levels' => json_encode(['A2']), 'size' => 8, 'prompt_version' => 'v4', 'status' => 'succeeded', 'created_at' => $now,
    ]);
    $dialog = Ulid::generate();
    DB::table('practice_dialogs')->insert(['id' => $dialog, 'user_id' => $user->id, 'collection_id' => $collectionId, 'status' => 'finished', 'lesson_json' => json_encode(['words' => []]), 'expires_at' => $now, 'created_at' => $now]);
    DB::table('practice_dialog_messages')->insert(['id' => Ulid::generate(), 'dialog_id' => $dialog, 'role' => 'user', 'text' => 'I am '.$user->email, 'ts' => 1, 'created_at' => $now]);
    DB::table('example_regenerations')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId, 'model' => 'gpt-4o', 'created_at' => $now]);
    DB::table('search_lookups')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'normalized_query' => 'appointment', 'lang' => 'en', 'native_lang' => 'ru',
        'payload' => json_encode([]), 'model' => 'm', 'prompt_version' => 'v1', 'created_at' => $now, 'updated_at' => $now,
    ]);
    DB::table('learning_mode_settings')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'mode' => 'typing', 'enabled' => false, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('admin_audit_log')->insert(['id' => Ulid::generate(), 'admin_id' => Ulid::generate(), 'action' => 'user.tier.change', 'target_user_id' => $user->id, 'context' => json_encode(['from' => 'free', 'to' => 'premium']), 'created_at' => $now]);
    // A right to the paid plan (наряд ACC-1 §2) — the table cascades with the user row.
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime']);

    $files = [
        ...DB::table('plan_line_audios')->where('user_id', $user->id)->pluck('path')->all(),
        ...DB::table('conversation_turns')->where('user_id', $user->id)->whereNotNull('audio_path')->pluck('audio_path')->all(),
        "plan-images/{$scene}/112.jpg",
    ];

    return [$user, $token, array_map('strval', $files)];
}

it('deletes the account to the last row and file: no table mentions the learner, the token is dead, the call journal stays', function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer);

    // Another learner, whose plan, voice, token and log must come out of it untouched.
    [$other, $otherToken] = planLearner();
    $otherPlan = planCreate($this, $otherToken, ['days_total' => 2])['id'];
    $otherFiles = DB::table('plan_line_audios')->where('user_id', $other->id)->pluck('path')->map(static fn (mixed $p): string => (string) $p)->all();
    app('auth')->forgetGuards();

    [$user, $token, $files] = acc1LearnerWithEverything($this);
    DB::table('model_calls')->insert(['id' => Ulid::generate(), 'status' => 'completed', 'provider' => 'openai', 'model' => 'gpt-5.4-mini', 'purpose' => 'conversation', 'estimated_tokens_in' => 10, 'timeout_seconds' => 20, 'started_at' => now()]);
    $calls = DB::table('model_calls')->count();
    $disk = Storage::disk((string) config('plan.audio_disk'));
    $images = Storage::disk((string) config('plan.image_disk'));

    // Before: the learner is everywhere — the check below is not vacuous.
    $before = acc1Mentions($user->id);
    expect(count($files))->toBeGreaterThan(20)
        ->and(array_keys($before))->toContain('plans.user_id', 'day_cards.user_id', 'conversation_turns.user_id', 'plan_line_audios.user_id',
            'reviews.user_id', 'term_triages.user_id', 'collections.owner_id', 'device_push_tokens.user_id', 'user_visits.user_id',
            'search_lookups.user_id', 'learning_mode_settings.user_id', 'admin_audit_log.target_user_id', 'api_request_logs.user_id',
            'personal_access_tokens.tokenable_id', 'profiles.user_id', 'practice_dialogs.user_id', 'plan_events.user_id', 'plan_notifications.user_id',
            'entitlements.user_id')
        ->and(array_keys(acc1Mentions($user->email)))->toContain('api_request_logs.response_body', 'users.email');
    foreach ($files as $path) {
        expect($disk->exists($path) || $images->exists($path))->toBeTrue($path);
    }

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/v1/auth/me')->assertNoContent();

    // Nothing of them in any table — neither the id nor the email: every text column of the schema searched.
    expect(acc1Mentions($user->id))->toBe([])
        ->and(acc1Mentions($user->email))->toBe([]);
    // No file of theirs on the disks, not even the folders.
    foreach ($files as $path) {
        expect($disk->exists($path) || $images->exists($path))->toBeFalse($path);
    }
    expect($disk->directories('plan-audio/conversations'))->toBe([]);

    // The journal of model calls stays as it was; the request log stays, unlinked; one line counts the deletion.
    $deletion = DB::table('account_deletions')->first();
    expect(DB::table('model_calls')->count())->toBe($calls)
        ->and(DB::table('api_request_logs')->whereNull('user_id')->count())->toBeGreaterThan(5)
        ->and(DB::table('account_deletions')->count())->toBe(1)
        ->and($deletion?->user_hash)->toBe(hash_hmac('sha256', $user->id, (string) config('app.key')))
        ->and($deletion?->plans_count)->toBe(2)
        ->and($deletion?->deleted_at)->not->toBeNull();

    // The token is dead: the same call again is refused at the door (401 — no token, no account behind it).
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/v1/auth/me')->assertUnauthorized();

    // The other learner is whole.
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$otherToken}")->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.id', $other->id);
    expect(DB::table('plans')->where('id', $otherPlan)->count())->toBe(1)
        ->and($otherFiles)->not->toBeEmpty()
        ->and(collect($otherFiles)->every(static fn (string $p): bool => $disk->exists($p)))->toBeTrue()
        ->and(DB::table('api_request_logs')->where('user_id', $other->id)->count())->toBeGreaterThan(0);
});

it('answers a repeat that came through the door before the first deletion committed with 404', function () {
    [$user] = learner();
    app(App\Modules\Identity\Application\Port\AccountEraser::class)->eraseFor(UserId::fromString($user->id));

    // The second request was authenticated before the account went: its user is the one in memory, the row is gone.
    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/auth/me')->assertNotFound()->assertJsonPath('code', 'account_not_found');
    expect(DB::table('account_deletions')->count())->toBe(1);
});

it('keeps the whole account — rows and files — when a module fails half way through its deletion', function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer);
    [$user, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $files = DB::table('plan_line_audios')->where('user_id', $user->id)->pluck('path')->map(static fn (mixed $p): string => (string) $p)->all();
    app()->instance(LearningAccountEraser::class, new class implements LearningAccountEraser
    {
        public function eraseFor(UserId $userId): void
        {
            throw new RuntimeException('learning refused');
        }
    });

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/v1/auth/me')->assertStatus(500);

    $disk = Storage::disk((string) config('plan.audio_disk'));
    expect(DB::table('users')->where('id', $user->id)->count())->toBe(1)
        ->and(DB::table('plans')->where('id', $id)->count())->toBe(1)
        ->and(DB::table('account_deletions')->count())->toBe(0)
        ->and($files)->not->toBeEmpty()
        ->and(collect($files)->every(static fn (string $p): bool => $disk->exists($p)))->toBeTrue();
});

it('signs out one device: POST /auth/logout revokes the current token and leaves the other device signed in', function () {
    [$user, $phone] = learner();
    $tablet = $user->createToken('tablet')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$phone}")->postJson('/api/v1/auth/logout')->assertNoContent();

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$phone}")->getJson('/api/v1/auth/me')->assertUnauthorized();
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$tablet}")->getJson('/api/v1/auth/me')->assertOk();
});
