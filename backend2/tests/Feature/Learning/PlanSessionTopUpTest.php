<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE TOP-UP BESIDE A PLAN DAY — what may join it, and where the seam falls.
 *
 * A plan session deals its day and then tops the sitting up from the learner's ordinary queue, so a
 * plan does not suspend the rest of their vocabulary. On a live day that top-up dealt two FRENCH
 * cards inside an `ru→en` plan, and one of them asked «выбери французский эквивалент» in the middle
 * of an English lesson. They were not unlucky either: both stood at `acquisition: learning` with no
 * `due_at`, and the queue orders `due_at ASC NULLS FIRST`, so they led the session.
 *
 * The other half of the same incident: the client counted the whole session as the day and told the
 * learner «День 1 пройден · 21 фраза и слово» over a day of fourteen. The payload now says where
 * the day ends instead of leaving that to be derived.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * A term in `$lang`, in a collection of `$support` → `$lang`, enrolled in this learner's pool and
 * due since yesterday.
 *
 * The COLLECTION is what makes this a pair rather than a language: the support side of a card is
 * read off the collection the term is shown through (DECISIONS п. 81), so a term with no collection
 * would fall back to the profile and the test would be measuring the fallback.
 */
function duePoolTerm(object $user, string $lang, string $support, string $text, string $translation): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => $lang, 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'phrase', 'source' => 'ai', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_translations')->insert([
        'id' => Ulid::generate(), 'term_id' => $termId, 'lang' => $support, 'text' => $translation,
        'is_primary' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $collectionId = Ulid::generate();
    DB::table('collections')->insert([
        'id' => $collectionId, 'owner_id' => $user->id, 'type' => 'custom', 'source' => 'user',
        'title' => "Папка {$lang}", 'topic' => null, 'source_lang' => $support, 'target_lang' => $lang,
        'visibility' => 'private', 'items_count' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'review', 'acquisition' => 'graduated', 'learning_step' => 0,
        'reps' => 2, 'successful_reviews' => 2, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 3, 'due_at' => now()->subDay(),
        'enrolled_at' => now()->subDays(2), 'enrollment_sources' => json_encode(['manual']),
        'created_at' => now()->subDays(2), 'updated_at' => now(),
    ]);

    return $termId;
}

it('never tops a plan day up with a term of another language pair', function () {
    [$user, $token, $planId] = startedPlan($this);

    // Two words due beside the plan: one this learner is studying in the plan's own pair, one in a
    // pair the plan has nothing to do with.
    $french = duePoolTerm($user, 'fr', 'ru', 'Je voudrais enregistrer mon bagage.', 'Я хотел бы сдать багаж.');
    $english = duePoolTerm($user, 'en', 'ru', 'boarding pass', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    $dealt = array_column(array_column($session['tasks'], 'card'), 'term_id');

    expect($dealt)->not->toContain($french)
        // …and the filter is a PAIR filter, not «only the day»: the English word in the same pair is
        // exactly what bucket 3 is for, and it is still dealt.
        ->and($dealt)->toContain($english);
});

it('keeps a foreign term out of the option pool as well, not only out of the running order', function () {
    // The French pair was hydrated alongside the day's own content, which made it a candidate
    // DISTRACTOR on an English card even before it was dealt as a task of its own.
    [$user, $token, $planId] = startedPlan($this);
    $french = duePoolTerm($user, 'fr', 'ru', 'la carte d’embarquement', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    foreach ($session['tasks'] as $task) {
        expect($task['card']['option_ids'] ?? [])->not->toContain($french);
    }
});

it('puts every task of the day before every task of the top-up, and says where the seam is', function () {
    [$user, $token, $planId] = startedPlan($this);
    duePoolTerm($user, 'en', 'ru', 'boarding pass', 'посадочный талон');

    $session = planSession($this, $token, $planId);

    expect($session)->toHaveKey('day_task_count');

    $sections = array_column($session['tasks'], 'section');

    // The seam, from both ends: the count says where it falls, and the order says it falls once.
    expect(array_slice($sections, 0, $session['day_task_count']))
        ->each->toBe(PlanSessionTaskView::SECTION_DAY)
        ->and(array_slice($sections, $session['day_task_count']))
        ->each->toBe(PlanSessionTaskView::SECTION_REVIEW)
        ->and($session['day_task_count'])->toBeLessThan(count($session['tasks']));

    // And `section` agrees with the field a client would otherwise have had to derive it from.
    foreach ($session['tasks'] as $task) {
        expect($task['section'])->toBe(
            $task['from_day_index'] === null
                ? PlanSessionTaskView::SECTION_REVIEW
                : PlanSessionTaskView::SECTION_DAY,
        );
    }
});

it('counts the day out of its own material when nothing else is due', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    expect($session['day_task_count'])->toBe(count($session['tasks']))
        ->and(array_column($session['tasks'], 'section'))->each->toBe(PlanSessionTaskView::SECTION_DAY);
});

// ── the day is judged when the sitting ENDS ───────────────────────────────────────────────────

it('marks the day passed as soon as its session is completed, without building another one', function () {
    // The live shape of the complaint: sixty-three exercises answered, «День 1 пройден» on the
    // client, and a plan card that still said «День 1 из 5». The verdict was derived correctly and
    // only ever WRITTEN while assembling the next session, so nobody had asked for it yet.
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);
    answerTasks($this, $token, $session);

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('ready');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete", [
            'ended_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    // NO second session built — this is the whole point.
    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('done');
});

it('leaves the day alone when the sitting ended with a word still owed a card', function () {
    // One card answered wrong is what actually kept the live day open, and it SHOULD keep it open:
    // stage A closes when every word of the day has closed it.
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);
    // Everything except the last task of the day.
    $partial = [
        'session_id' => $session['session_id'],
        'tasks' => array_slice($session['tasks'], 0, max(0, count($session['tasks']) - 1)),
    ];
    answerTasks($this, $token, $partial);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete", [
            'ended_at' => now()->toIso8601String(),
        ])
        ->assertOk();

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('ready');
});
