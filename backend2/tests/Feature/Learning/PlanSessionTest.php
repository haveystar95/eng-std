<?php

declare(strict_types=1);

use App\Modules\Learning\Infrastructure\Adapter\LoggingModeFallbackReporter;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `POST /plans/{id}/days/{n}/session` — the plan being STUDIED.
 *
 * Everything here runs on the offline plan model, so the material is real (it passes both
 * validators) and free. What is being tested is not the material but the mechanism: which trainer a
 * word is owed, in what order the day deals them, what closes a stage, when the focus moves, and
 * what a day opened out of turn gets instead.
 */
beforeEach(function (): void {
    // Offline, and resolved back to prove it — see fakePlanModel() in tests/Pest.php.
    fakePlanModel();

    // NOTHING IS SWITCHED ON HERE, and that is the point (Д-15).
    //
    // This block used to flip every `scope = global` row to `enabled` before each test, because
    // `intro` and `speaking` ship dark and `PlanStandings` intersected the plan matrix with them —
    // so without the flip the stage-A checklist was three steps and this file tested a narrower
    // ladder than the one it describes. That intersection is gone: a plan's matrix overrides the
    // learner's ({@see PlanStandings}), so the rows below are left exactly as a migration writes
    // them on a fresh account, and every «intro first, speaking last» expectation in this file is
    // now an assertion about the shipped default rather than about a test fixture.
});



// ── the shape of a strict session ─────────────────────────────────────────────────────────────

it('deals day 1 as stage A: intro first, then the two recognitions, the word bank and speaking', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    expect($session['strict'])->toBeTrue()
        ->and($session['day_index'])->toBe(1)
        ->and($session['focus_day_index'])->toBe(1)
        ->and($session['tasks'])->not->toBeEmpty();

    // THE SITTING OPENS ON THE WARM-UP — the plan's five rescue phrases, before the day itself
    // (канон §5). They are a section of their own, so «N из N» on the day screen still counts the
    // day's own cards and nothing else.
    $day = array_values(array_filter($session['tasks'], static fn (array $t): bool => $t['section'] === 'day'));
    $warmup = array_values(array_filter($session['tasks'], static fn (array $t): bool => $t['section'] === 'warmup'));

    expect($warmup)->not->toBeEmpty()
        ->and($session['tasks'][0]['section'])->toBe('warmup')
        ->and($session['day_task_count'])->toBe(count($day));

    // Every task of a never-studied day is stage A, and belongs to day 1.
    foreach ($day as $task) {
        expect($task['stage'])->toBe('a')
            ->and($task['source'])->toBe('new')
            ->and($task['from_day_index'])->toBe(1);
    }

    // The first word's own chain, in the stage's fixed order.
    $first = $day[0]['card']['term_id'];
    $chain = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $first) {
            $chain[] = $task['card']['exercise_mode'];
        }
    }

    // The chain a card is dealt depends on what it IS. A word is met, recognised twice and said;
    // a line is met, recognised once, put together and read aloud — and never typed.
    $kind = DB::table('terms')->where('id', $first)->value('kind');

    expect($chain)->toBe($kind === 'line'
        ? ['intro', 'multiple_choice', 'word_bank', 'speaking']
        : ['intro', 'multiple_choice', 'multiple_choice', 'speaking']);
});

it('lays the day out as pieces, connectors and then replies — whatever the level says', function () {
    // PLAN-FIX-4 п. 1.3. `conversational` used to invert this and open the day on a reply; on the
    // owner's 01.09 screen that reply was fifteen words long and its first card was `speaking`.
    [, $token, $planId] = startedPlan($this, ['level' => 'conversational']);

    $session = planSession($this, $token, $planId);
    $kinds = DB::table('terms')->pluck('kind', 'id')->all();

    $rank = ['word' => 0, 'chunk' => 1, 'line' => 2];
    $seen = [];
    $last = -1;
    // The DAY's own cards: the warm-up is a section of its own and stands before all of them, which
    // is the one thing about the running order that is fixed (канон §11 — the rest is SIT-1).
    $dayTasks = array_values(array_filter($session['tasks'], static fn (array $t): bool => $t['section'] === 'day'));
    foreach ($dayTasks as $task) {
        $kind = $kinds[$task['card']['term_id']] ?? 'word';
        $seen[$kind] = true;
        // Never back to an earlier block: every card of a block, with its whole checklist, before
        // the first card of the next one.
        expect($rank[$kind])->toBeGreaterThanOrEqual($last);
        $last = $rank[$kind];
    }

    // The fixture is worth testing only if it actually holds all three.
    expect($seen)->toHaveKeys(['word', 'chunk', 'line'])
        // And the day opens on a first meeting of a PIECE, not on the sentence built out of it.
        ->and($dayTasks[0]['card']['exercise_mode'])->toBe('intro')
        ->and($kinds[$dayTasks[0]['card']['term_id']])->toBe('word');
});

it('deals a line and a word different chains in the same session', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $kinds = DB::table('terms')->pluck('kind', 'id')->all();
    $chains = [];
    foreach ($session['tasks'] as $task) {
        $termId = $task['card']['term_id'];
        $chains[$kinds[$termId] ?? 'word'][$termId][] = $task['card']['exercise_mode'];
    }

    // A LINE is never dealt typing or dictation, at any stage — «нечего печатать по буквам».
    foreach ($chains['line'] ?? [] as $chain) {
        expect($chain)->not->toContain('typing')
            ->and($chain)->not->toContain('dictation')
            ->and($chain[0])->toBe('intro');
    }

    // A WORD gets two recognitions and no assembly step.
    foreach ($chains['word'] ?? [] as $chain) {
        expect(array_slice($chain, 0, 4))->toBe(['intro', 'multiple_choice', 'multiple_choice', 'speaking']);
    }

    expect($chains['line'] ?? [])->not->toBeEmpty()
        ->and($chains['word'] ?? [])->not->toBeEmpty();
});

it('never pads a choice with another kind — a connector is offered connectors (Д-2)', function () {
    Cache::forget(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED);

    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    // The day is 8 lines + 4 words + 2 connectors ({@see DayCapacity::split()} on a budget of 14).
    // A connector therefore has ONE other connector in its own day — and since PLAN-FIX-5 the
    // question is not «does the day hold three», it is «does the CATALOGUE», which the reader has
    // answered from all along (DECISIONS п. 213). What has never been allowed, and is what Д-2 is
    // actually about, is filling the gap with another KIND: «which of these is a connector»
    // answered by length, among single words.
    $kinds = DB::table('terms')->pluck('kind', 'id')->all();
    $kindOfText = DB::table('terms')->pluck('kind', 'text')->all();

    $checked = [];
    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        $options = $card['options'] ?? null;
        // The identity-graded rung-1 card offers TRANSLATIONS, not term texts — a different
        // question, judged by its own test.
        if ($card['exercise_mode'] !== 'multiple_choice' || $options === null || ($card['option_ids'] ?? null) !== null) {
            continue;
        }
        $targetKind = $kinds[$card['term_id']] ?? null;
        $checked[$targetKind] = true;
        foreach ($options as $option) {
            expect($kindOfText[$option] ?? null)->toBe($targetKind);
        }
    }

    // And the connector now HAS a choice card, which is the half that changed: its two wrong
    // answers come out of the catalogue instead of out of a day that holds one.
    expect($checked)->toHaveKeys(['word', 'chunk', 'line']);
});


/**
 * Keep the first `$keep` WORD cards of day 1 inside the length band and push every other word of
 * the plan out of it, by making it far too long to stand beside them.
 *
 * The band is the mechanism under test ({@see DistractorLength}), so the fixture uses it rather
 * than deleting rows: a term that is still there, still a `word`, still the plan's, and still
 * unusable as an option is exactly the shape of the live starvation.
 *
 * @return string the term id of the target — the first word of day 1
 */
function narrowWordPoolTo(string $planId, int $keep): string
{
    $collections = DB::table('learning_plan_days')->where('plan_id', $planId)
        ->whereNotNull('collection_id')->pluck('collection_id');
    $day1 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');

    $inDay1 = DB::table('collection_items')->where('collection_id', $day1)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->where('terms.kind', 'word')->orderBy('terms.text')->pluck('terms.id')->all();

    $everyWord = DB::table('collection_items')->whereIn('collection_id', $collections)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->where('terms.kind', 'word')->orderBy('terms.text')->pluck('terms.id')->all();

    $kept = array_slice($inDay1, 0, $keep);
    $i = 0;
    foreach ($everyWord as $termId) {
        if (in_array($termId, $kept, true)) {
            continue;
        }
        DB::table('terms')->where('id', $termId)->update(['text' => 'far too long to stand beside them ' . $i++]);
    }

    return $kept[0];
}

it('owes a choice the DAY cannot furnish but the catalogue can — one population, not two', function () {
    // The стык PLAN-FIX-5 exists for. The checklist decides whether a card is OWED and the assembler
    // decides whether it can be BUILT; while the first counted the day and the second the catalogue,
    // the first refused cards the second would have dealt and was never asked about them. Here the
    // day holds ONE other card of the target's shape and length — below the floor — and the
    // catalogue holds more, so the step must be owed AND dealt.
    Cache::forget(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED);

    [, $token, $planId] = startedPlan($this, ['level' => 'conversational']);

    $day1 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');
    $words = DB::table('collection_items')->where('collection_id', $day1)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->where('terms.kind', 'word')->orderBy('terms.text')->pluck('terms.id')->all();

    // Two words left in band INSIDE THE DAY — one short of the floor of three. What is pushed out is
    // still there, still the plan's and still `word`: only the length band excludes it, from the day
    // AND from the catalogue, so the shortfall has to be answered by day 2's words.
    $target = (string) $words[0];
    foreach (array_slice($words, 2) as $i => $termId) {
        DB::table('terms')->where('id', $termId)->update(['text' => 'far too long to stand beside them ' . $i]);
    }

    $session = planSession($this, $token, $planId);

    $choices = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['term_id'] === $target
            && $t['card']['exercise_mode'] === 'multiple_choice',
    ));

    expect($choices)->not->toBeEmpty()
        ->and(count($choices[0]['card']['options']))->toBeGreaterThanOrEqual(3);

    // …and at least one wrong answer came from OUTSIDE the day, which is the claim: the day could
    // not furnish the card and the card was dealt anyway, out of the same population the reader has
    // always drawn from.
    $outsideDay1 = DB::table('collection_items')->where('collection_id', '!=', $day1)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->pluck('terms.text')->all();

    expect(array_intersect($choices[0]['card']['options'], $outsideDay1))->not->toBeEmpty();
});

it('shrinks a starved choice to three options instead of dropping it (PLAN-FIX-5)', function () {
    Cache::forget(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED);

    // `conversational` PREFERS four options. The floor under that preference is three.
    [, $token, $planId] = startedPlan($this, ['level' => 'conversational']);
    $target = narrowWordPoolTo($planId, keep: 3);

    $session = planSession($this, $token, $planId);

    $choices = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['term_id'] === $target
            && $t['card']['exercise_mode'] === 'multiple_choice',
    ));

    expect($choices)->not->toBeEmpty()
        // Three, not four — and dealt, not dropped. Before the floor this card did not exist at all
        // and the word went straight from «met it» to «say it».
        ->and($choices[0]['card']['options'])->toHaveCount(3)
        ->and($choices[0]['card']['options'])->toContain($choices[0]['card']['answer']);
});

it('still drops the choice when even three cannot be furnished, and counts it', function () {
    Cache::forget(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED);

    [, $token, $planId] = startedPlan($this, ['level' => 'conversational']);
    $target = narrowWordPoolTo($planId, keep: 2);

    $session = planSession($this, $token, $planId);

    $modes = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $target) {
            $modes[] = $task['card']['exercise_mode'];
        }
    }

    // Two options is a coin toss, and a coin toss writes a correct answer nobody gave.
    expect($modes)->not->toBeEmpty()
        ->and($modes)->not->toContain('multiple_choice')
        // The word is still taught — it is only the CHOICE that cannot be built honestly.
        ->and($modes)->toContain('intro')
        ->and(Cache::get(LoggingModeFallbackReporter::PLAN_DISTRACTOR_STARVED))->toBeGreaterThan(0);
});
it('deals the interlocutor’s own line for recognition only, and says whose it is (Д-8)', function () {
    [, $token, $planId] = startedPlan($this);

    // A whole SHELF of the day is the other person's turn now — «Тебе скажут» — and nothing has to
    // be marked by hand any more: the shelf says whose line it is and the server derives the tier
    // from it. «Hello. What seems to be the problem with your child?» in the live run was a card
    // like this one, and the app spent a word bank making the learner build it word by word.
    $roleLine = DB::table('terms')->where('shelf', 'hear')->orderBy('id')->value('id');
    $learnerLine = DB::table('terms')->where('shelf', 'say')->orderBy('id')->value('id');

    expect($roleLine)->not->toBeNull()
        ->and($learnerLine)->not->toBeNull()
        ->and(DB::table('terms')->where('id', $roleLine)->value('tier'))->toBe('understand')
        ->and(DB::table('terms')->where('id', $learnerLine)->value('tier'))->toBe('speak');

    $session = planSession($this, $token, $planId);

    $modes = [];
    $speakers = [];
    foreach ($session['tasks'] as $task) {
        $modes[$task['card']['term_id']][] = $task['card']['exercise_mode'];
        $speakers[$task['card']['term_id']] = $task['speaker'];
    }

    // Meeting it and choosing its meaning stay. Assembling it, typing it and reading it aloud do
    // not: the live run spent a word bank making the learner build the doctor's question word by
    // word, and then a speaking card making them read it out.
    expect($modes[$roleLine])->toContain('intro')
        ->and($modes[$roleLine])->toContain('multiple_choice')
        ->and($modes[$roleLine])->not->toContain('word_bank')
        ->and($modes[$roleLine])->not->toContain('scramble')
        ->and($modes[$roleLine])->not->toContain('typing')
        ->and($modes[$roleLine])->not->toContain('speaking');

    // The learner's OWN lines are untouched — this is about whose turn it is, not about lines.
    expect($modes[$learnerLine])->toContain('speaking');

    // …and the card SAYS whose line it is, in both directions. A recognition card that did not
    // would be indistinguishable from one the learner is expected to produce.
    expect($speakers[$roleLine])->toBe('role')
        ->and($speakers[$learnerLine])->toBe('learner');

    // The DAY screen carries it too, so the register does not read as «eleven sentences you are
    // learning to say» with the doctor's among them.
    $dayTerms = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")
        ->assertOk()
        ->json('data.terms');

    $byId = array_column($dayTerms, 'speaker', 'id');
    expect($byId[$roleLine])->toBe('role')
        ->and($byId[$learnerLine])->toBe('learner');
});

it('deals a role line the day’s SKELETON named, even when the card carries no speaker (Д-33)', function () {
    // The other source of «whose line is this», and the one the live run tripped over. The day's
    // skeleton holds `role.opening_lines`; when the model puts one of them among the day's cards it
    // arrives with `terms.speaker` empty, and the checklist then owed it a dictation — the learner
    // was asked to write down «Does your child have a fever?» from hearing the doctor say it.
    [, $token, $planId] = startedPlan($this);

    $day1 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->first();
    $line = DB::table('collection_items')->where('collection_id', $day1->collection_id)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->where('terms.kind', 'line')->orderBy('terms.id')->first(['terms.id', 'terms.text']);

    // Deliberately NOT marked: this card is the interlocutor's by the skeleton alone.
    DB::table('terms')->where('id', $line->id)->update(['speaker' => null]);

    $brief = json_decode((string) $day1->role_brief, true);
    $brief['role'] = ['name' => 'Врач-терапевт', 'opening_lines' => [['text' => $line->text]]];
    DB::table('learning_plan_days')->where('id', $day1->id)->update(['role_brief' => json_encode($brief)]);

    $session = planSession($this, $token, $planId);

    $modes = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $line->id) {
            $modes[] = $task['card']['exercise_mode'];
        }
    }

    expect($modes)->not->toBeEmpty()
        ->and($modes)->toContain('intro')
        ->and($modes)->not->toContain('dictation')
        ->and($modes)->not->toContain('word_bank')
        ->and($modes)->not->toContain('scramble')
        ->and($modes)->not->toContain('typing')
        ->and($modes)->not->toContain('speaking');
});

it('refuses to BUILD a production card for a role line, even off the checklist (Д-33)', function () {
    // The second gate. It was written for the SOFT session — a day opened out of turn used to pick
    // its trainer off the ordinary ladder and never see a checklist, which is where the live run's
    // dictation on the doctor's question came from. That path is gone (E2E-SIM-2 С-1): a day opened
    // ahead of the focus is dealt strictly, off the plan's ladder, like any other.
    //
    // The gate is kept and the assertion is unchanged, because the gate is what makes the rule true
    // BELOW the checklist: the interlocutor's line is recognition-only wherever the mode came from,
    // and a fixture whose `speaker` column is edited after the day was written is exactly the case
    // where the checklist and the card can disagree.
    [, $token, $planId] = startedPlan($this);

    $day2 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->value('collection_id');
    if ($day2 === null) {
        $this->markTestSkipped('the fixture plan wrote only one day');
    }

    $roleLine = DB::table('collection_items')->where('collection_id', $day2)
        ->join('terms', 'terms.id', '=', 'collection_items.term_id')
        ->where('terms.kind', 'line')->orderBy('terms.id')->value('terms.id');
    DB::table('terms')->where('id', $roleLine)->update(['speaker' => 'role']);

    $session = planSession($this, $token, $planId, 2);

    expect($session['strict'])->toBeTrue();
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] !== $roleLine) {
            continue;
        }
        expect(['word_bank', 'scramble', 'typing', 'speaking', 'cloze', 'dictation'])
            ->not->toContain($task['card']['exercise_mode']);
    }
});

it('measures the length band on the text the card SHOWS, not on the term behind it (Д-2)', function () {
    // The `zero` level deals the FAR-option card: the prompt is the term and the options are the
    // neighbours' TRANSLATIONS. The band used to be measured on those neighbours' English while
    // their Russian was what went on screen, so the answer kept coming out the only long option
    // there (скрины 120, 247) with every English side comfortably inside the band.
    [, $token, $planId] = startedPlan($this, ['level' => 'zero']);

    $words = DB::table('terms')->where('kind', 'word')->orderBy('id')->pluck('id')->all();
    expect(count($words))->toBeGreaterThan(2);

    // Every OTHER word keeps its English length and loses its Russian one: a two-letter option
    // beside a whole word is the one nobody has to read.
    $target = (string) $words[0];
    foreach (array_slice($words, 1) as $termId) {
        DB::table('term_translations')->where('term_id', $termId)->update(['text' => 'да']);
    }
    // …and the target's own translation is a long one, so «да» is unmistakably out of its band.
    DB::table('term_translations')->where('term_id', $target)->update(['text' => 'жаропонижающее']);

    $session = planSession($this, $token, $planId);

    $seen = 0;
    foreach ($session['tasks'] as $task) {
        $card = $task['card'];
        if ($card['term_id'] !== $target || $card['exercise_mode'] !== 'multiple_choice') {
            continue;
        }
        $seen++;
        // «да» never stands on this card. Either the band refused it and other neighbours filled the
        // slots, or the belt starved and the card fell through to the catalogue-backed one, whose
        // options are English. Both are honest; the answer standing alone at its own length is not.
        expect($card['options'])->not->toContain('да');
    }

    expect($seen)->toBeGreaterThan(0, 'the target must actually be dealt a choice card');
});

it('carries the level’s knobs, and says which of them the card actually honoured', function () {
    [, $token, $planId] = startedPlan($this, ['level' => 'zero']);

    $session = planSession($this, $token, $planId);

    expect($session['knobs'])->toBe([
        'mc_options' => 3,
        'distractor_closeness' => 'far',
        'cloze_blanks' => 1,
        'bank_extra' => 0,
        'typing_hint' => 'first_letter',
        'tts_rate' => 'slow',
    ]);

    $mc = null;
    foreach ($session['tasks'] as $task) {
        if ($task['card']['exercise_mode'] === 'multiple_choice') {
            $mc = $task;

            break;
        }
    }

    expect($mc)->not->toBeNull()
        ->and($mc['knobs_applied'])->toBe(['mc_options', 'distractor_closeness'])
        ->and($mc['knobs_ignored'])->toBe([])
        // `mc_options: 3` at level `zero` — the knob that reaches the trainer.
        ->and(count($mc['card']['options']))->toBeLessThanOrEqual(3);
});

it('names the speaking card’s form per stage, so the client knows what to put on screen', function () {
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId);

    $speaking = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'speaking',
    ));

    expect($speaking)->not->toBeEmpty()
        // Stage A: the word is on the screen and the learner reads it aloud.
        ->and($speaking[0]['speaking_form'])->toBe('word_on_screen');
});

// ── the day passing, and the focus moving ─────────────────────────────────────────────────────

it('never lets readiness FALL as a new day is written (Д-31)', function () {
    // The live run: 23 % after day 1, 13 % after day 2, 8 % after day 3. The denominator was «the
    // standings that exist», and standings exist only for days that have been written — so every
    // day the learner finished added fourteen fresh stage-A cards to the bottom of the fraction and
    // the percentage went backwards. A person doing the work watched their readiness for the
    // appointment fall.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $readiness = static fn (object $ctx): float => (float) $ctx
        ->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")
        ->assertOk()
        ->json('data.readiness');

    // Day 1, then two nights of its own revision — enough for its lines to reach the last stage a
    // line has, which is what `ready` counts.
    $seq = walkDay($this, $token, $planId, 1);
    for ($night = 0; $night < 3; $night++) {
        ageHistory($user->id, days: 1);
        $session = planSession($this, $token, $planId, 1);
        if ($session['tasks'] === []) {
            break;
        }
        $seq = answerTasks($this, $token, $session, $seq);
    }

    // Day 2 is written by now (passing day 1 is what queues it). Put it back to `pending` for one
    // read: this is the state the live run was measuring in, and the two readings must agree —
    // «сколько дней уже написано» is not a fact about how ready the learner is.
    $day2 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->first();
    expect($day2)->not->toBeNull()->and($day2->collection_id)->not->toBeNull();

    DB::table('learning_plan_days')->where('id', $day2->id)
        ->update(['collection_id' => null, 'status' => 'pending']);
    $beforeDay2Written = $readiness($this);

    DB::table('learning_plan_days')->where('id', $day2->id)
        ->update(['collection_id' => $day2->collection_id, 'status' => $day2->status]);
    $afterDay2Written = $readiness($this);

    expect($beforeDay2Written)->toBeGreaterThan(0.0, 'the fixture must actually acquire something')
        ->and($afterDay2Written)->toBe($beforeDay2Written);
});

it('counts the cards and the stage-A closures the plan card shows', function () {
    // What `readiness` cannot say while half its formula does not exist. It counts the cards that
    // reached their LAST stage — a line after B, a word after C — so a day that closes stage A moves
    // its cards ONTO stage B and the percentage honestly stays at zero. The owner walked fifty-six
    // cards on 02.09 and the plan card said «0% готовность к событию».
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $census = static fn (object $ctx): array => (array) $ctx
        ->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")
        ->assertOk()
        ->json('data.stage_census');

    $before = $census($this);
    expect($before['total'])->toBeGreaterThan(0)
        ->and($before['stage_a_closed'])->toBe(0);

    walkDay($this, $token, $planId, 1);

    $after = $census($this);
    // The cards of day 1 that a SESSION can deal. The numbers of the scene are stored with the day
    // and dealt by nothing yet (канон §6, NUM-1), so they are not owed a stage and are not counted
    // as one that closed — a card no sitting can play must not hold a day open.
    $day1 = DB::table('collection_items as ci')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('ci.collection_id', DB::table('learning_plan_days')
            ->where('plan_id', $planId)->where('day_index', 1)->value('collection_id'))
        ->where('t.kind', '!=', 'number')
        ->count();

    // Every card of day 1 closed its stage A, and the count SAYS SO on the day it happened — the
    // percentage will not move until a night has passed and stage B has been walked too.
    expect($after['stage_a_closed'])->toBe($day1)
        ->and($after['total'])->toBeGreaterThanOrEqual($day1)
        ->and((float) $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/plans/{$planId}")->json('data.readiness'))->toBe(0.0);
});

it('passes the day when every word closes stage A, and moves the focus to day 2', function () {
    [, $token, $planId] = startedPlan($this);

    walkDay($this, $token, $planId, 1);

    $after = planSession($this, $token, $planId);

    expect($after['focus_day_index'])->toBe(2)
        ->and(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->toBe('done');
});

/**
 * The handover from stage A to stage B — and the invariant that constrains it.
 *
 * The plan says WHICH card; the repetition planner says WHEN a word comes back. So a word that
 * closed stage A does not reappear the next morning merely because a night has passed: it reappears
 * when SM-2 makes it DUE, and the plan then deals it the stage-B checklist. The night is a
 * necessary condition for the stage to advance and never a sufficient one to summon the word — and
 * this test asserts both halves, because getting the second one wrong is how «чем» would quietly
 * start deciding «когда».
 */
it('opens stage B when the planner makes the word due again — the night alone does not summon it', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);

    $day1Terms = DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id'))
        ->pluck('term_id')
        ->all();

    // Same day: day 1's words owe nothing at all, so nothing of theirs is dealt.
    $sameDay = planSession($this, $token, $planId, 2);
    $stageB = array_filter($sameDay['tasks'] ?? [], static fn (array $t): bool => ($t['stage'] ?? null) === 'b');
    expect($stageB)->toBe([]);

    // A night passes and the words move to stage B — but the PLANNER still decides when each of
    // them comes back. Whatever the session carries as `plan_review` is a word SM-2 has made due;
    // the plan never pulls one forward, which is the whole «чем ≠ когда» split.
    //
    // (Under v0.1 this step could assert the stronger «nothing at all», because a stage A of five
    // cards pushed every word past a single night. v0.2's checklists are shorter — a word gets four
    // cards, a line four — so some of them are legitimately due the next morning. The assertion
    // moved to the rule rather than to the arithmetic that happened to follow from it.)
    ageHistory($user->id, days: 1);
    $tomorrow = planSession($this, $token, $planId);
    // «Owed a card», which is the planner's own predicate and not a narrower hand-rolled one: due
    // now, OR never scheduled at all. The second half is not a loophole — a pair still on the
    // recognition rungs has no `due_at` because those rungs never schedule (DECISIONS п. 203), and
    // «незаконченное» is the most urgent thing the trainer has. What the plan may never do is pull
    // forward a word the planner has scheduled for LATER, and that is what this asserts.
    $notOwed = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->where('due_at', '>', now())
        ->pluck('term_id')
        ->all();

    foreach ($tomorrow['tasks'] as $task) {
        if ($task['source'] === 'plan_review') {
            expect($task['card']['term_id'])->not->toBeIn($notOwed);
        }
    }

    // Now the planner says they are due — and the plan deals them their stage-B checklist as the
    // SEAM, after the day's own material. (Under PLAN-FIX-3 the revision moved behind the day: it is
    // a section the learner reads a label over — «Повторение · из прошлых дней» — and a section
    // announced after the cards it labels is not a section. The budget agrees, since the day is what
    // must not be cut.)
    ageHistory($user->id, days: 7);
    $later = planSession($this, $token, $planId);

    $review = array_values(array_filter(
        $later['tasks'],
        static fn (array $t): bool => $t['section'] === 'review',
    ));
    // The warm-up leads every sitting (канон §5); the DAY is what follows it, and the seam of
    // earlier days follows that.
    $day = array_values(array_filter($later['tasks'], static fn (array $t): bool => $t['section'] === 'day'));
    $first = $day[0] ?? null;
    $firstReview = $review[0] ?? null;

    expect($later['tasks'])->not->toBeEmpty()
        ->and($later['tasks'][0]['section'])->toBe('warmup')
        // The day leads, and a first meeting is stage A.
        ->and($first['source'])->toBe('new')
        ->and($first['stage'])->toBe('a')
        ->and($first['from_day_index'])->toBe(2)
        // …and day 1's words follow it, on stage B, carried in as the seam.
        ->and($firstReview)->not->toBeNull()
        ->and($firstReview['stage'])->toBe('b')
        ->and($firstReview['source'])->toBe('plan_review')
        ->and($firstReview['from_day_index'])->toBe(1)
        ->and($firstReview['card']['term_id'])->toBeIn($day1Terms);
});

// ── what the plan screen reads ────────────────────────────────────────────────────────────────

it('reports the focus, the next day, the days to the event and «ты уже можешь»', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(4)->format('Y-m-d')]);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/plans/active')->assertOk()->json('data');

    expect($plan['focus_day_index'])->toBe(1)
        ->and($plan['next_day_index'])->toBe(2)
        ->and($plan['days_to_event'])->toBe(4)
        ->and($plan['deadline_tight'])->toBeFalse()
        // Nothing studied yet: no word has reached stage C, and no conversation has confirmed a
        // checkpoint. Both halves of the formula are zero and the number says so.
        ->and($plan['readiness'])->toBe(0)
        ->and($plan['can_already'])->not->toBeEmpty();

    foreach ($plan['can_already'] as $line) {
        expect($line['hit'])->toBeFalse()
            ->and($line['text'])->toBeString()
            ->and($line['day_index'])->toBeInt();
    }
});

it('moves the reported focus as days are passed', function () {
    [, $token, $planId] = startedPlan($this);

    walkDay($this, $token, $planId, 1);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');

    // Two introduction days on a three-day plan, so once the focus is on the second there is no
    // «next» — the thing after it is the final day, which teaches nothing.
    expect($plan['focus_day_index'])->toBe(2)
        ->and($plan['next_day_index'])->toBeNull();
});

// ── when a plan spends money ──────────────────────────────────────────────────────────────────

/**
 * A short plan arrives whole — one day at a time, never side by side.
 *
 * The sequence is the assertion. Day n is written FROM days 1…n−1: its terms go into the prompt's
 * KNOWN block so the model gives them fresh examples in the new situation instead of teaching them
 * again. The first version of the eager branch dispatched every day at once, and the live S1 run
 * showed the cost — day 2's call finished before day 1's collection existed, its KNOWN block went
 * out empty, and not one of day 1's nine terms got its day-2 example. Nothing failed; the material
 * was simply written as if the previous day had not happened.
 */
it('writes a short plan whole, but strictly one day at a time', function () {
    [, $token, $planId] = startedPlan($this);

    $collections = DB::table('learning_plan_days')->where('plan_id', $planId)
        ->whereNotNull('collection_id')->orderBy('day_index')->pluck('collection_id', 'day_index')->all();

    expect($collections)->toHaveCount(2);

    // Day 2's material was written while day 1 already existed — so day 1's terms were in the
    // KNOWN block, and day 2 introduces none of them.
    $terms = fn (int $day): array => DB::table('collection_items')
        ->where('collection_id', $collections[$day])->pluck('term_id')->all();

    expect(array_intersect($terms(1), $terms(2)))->toBe([]);

    // The proof that the ORDER held: the day-2 collection is younger than every term of day 1.
    $day2CreatedAt = DB::table('collections')->where('id', $collections[2])->value('created_at');
    $lastDay1Term = DB::table('terms')->whereIn('id', $terms(1))->max('created_at');

    expect($day2CreatedAt)->toBeGreaterThanOrEqual($lastDay1Term);
});

it('writes only day 1 of a LONG plan at the start, and the next when a day is walked', function () {
    // A goal big enough for five introduction days, and ten calendar days to teach them in: past
    // the eager threshold, so the plan pays for one day and stops. The SIZE has to come from the
    // goal now — P1 v0.2 is not told the calendar, so a later event date no longer buys more days.
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $statuses = fn (): array => DB::table('learning_plan_days')
        ->where('plan_id', $planId)->orderBy('day_index')->pluck('status', 'day_index')->all();

    $before = $statuses();
    expect($before[1])->toBe('ready')
        ->and($before[2])->toBe('pending')
        ->and(count(array_filter($before, static fn (string $s): bool => $s === 'ready')))->toBe(1);

    // Day 1 walked → day 2 queued. «Done», not «ready»: the evidence that the learner will come
    // back is that they came back.
    walkDay($this, $token, $planId, 1);
    $after = $statuses();

    expect($after[1])->toBe('done')
        ->and($after[2])->toBe('ready');
});

it('closes the day off the CLIENT’S complete, and queues day 2 from that alone (Д-1)', function () {
    // THE WHOLE CHAIN, in one test, because it was broken in the join and not in any of its parts:
    // client → `POST /study/sessions/{id}/complete` → `CompleteStudySession` → `PlanDayPassing` →
    // `PlanGenerationPolicy::nextAfterDone` → day 2 written.
    //
    // Every piece of that worked in isolation and the live run still ended with day 1 `ready` and
    // day 2 never queued, because the app never sent the completion (`session_screen.dart` called
    // `record` only from the ordinary summary). The day turned `done` on the NEXT session build —
    // the very fallback PLAN-SESSION-FIX declared closed — which is why this test must not build a
    // second session to prove anything.
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $statuses = fn (): array => DB::table('learning_plan_days')
        ->where('plan_id', $planId)->orderBy('day_index')->pluck('status', 'day_index')->all();

    expect($statuses()[1])->toBe('ready')
        ->and($statuses()[2])->toBe('pending');

    // ONE sitting: build it, answer every task, and close it the way the app does.
    $session = planSession($this, $token, $planId);
    answerTasks($this, $token, $session);

    // …and before the completion, nothing has moved. This is the state the live run was stuck in.
    expect($statuses()[1])->toBe('ready')
        ->and($statuses()[2])->toBe('pending')
        ->and(DB::table('study_sessions')->where('id', $session['session_id'])->value('ended_at'))
        ->toBeNull();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$session['session_id']}/complete")
        ->assertOk();

    // The completion alone did all three things.
    expect(DB::table('study_sessions')->where('id', $session['session_id'])->value('ended_at'))
        ->not->toBeNull();
    expect($statuses()[1])->toBe('done')
        ->and($statuses()[2])->toBe('ready');
});

it('builds a day on demand, idempotently, and refuses to run more than two ahead', function () {
    [, $token, $planId] = startedPlan($this, [
        'goal_text' => 'Большая цель [scenes:5]',
        'event_date' => now()->addDays(10)->format('Y-m-d'),
    ]);

    $generate = fn (int $n) => $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/{$n}/generate");

    // Two days ahead of the focus (day 1) is what the ceiling allows.
    expect($generate(2)->assertOk()->json('data.status'))->toBe('ready')
        ->and($generate(3)->assertOk()->json('data.status'))->toBe('ready')
        // Calling it again on a day that is already written is not an error and does not pay twice.
        ->and($generate(2)->assertOk()->json('data.status'))->toBe('ready');

    $attempts = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->value('generation_attempts');
    expect($attempts)->toBe(1);

    // The third is over the ceiling: a 409 with a sentence, so the screen can say why.
    $generate(4)->assertStatus(409)->assertJsonPath('code', 'plan_day_capped');

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 4)->value('status'))
        ->toBe('pending');
});

// ── a day opened out of turn ──────────────────────────────────────────────────────────────────

/**
 * Э3.2 OF E2E-SIM-2, AS A TEST — the owner's own live repro, and the наряд's «падает на старом
 * поведении» case.
 *
 * On the stand: day 1 left one card short of closing, day 2 opened from the plan screen's «можно
 * открыть раньше». What came back was seventeen tasks with `strict = false`, `source = soft`,
 * `stage = null`, not one `intro` among them, six `listening` cards dictating sentences the learner
 * had never been shown, and a card of the `numbers` shelf that no session is supposed to deal. The
 * screen captioned the first of them «СТУПЕНЬ A · ПОВТОРЕНИЕ». The same thing is in the owner's
 * production log for the evening of 03.09, four sittings of it.
 *
 * Every assertion below fails on that payload.
 */
it('deals a day opened ahead of the focus its own stage A — strictly, intro first, no В-modes (Э3.2)', function () {
    [, $token, $planId] = startedPlan($this);

    // The focus is still day 1 — nothing has been answered — and day 2 is asked for anyway.
    $session = planSession($this, $token, $planId, 2);

    expect($session['strict'])->toBeTrue()
        ->and($session['focus_day_index'])->toBe(1)
        ->and($session['day_index'])->toBe(2)
        ->and($session['tasks'])->not->toBeEmpty();

    $modes = [];
    $introOf = [];
    $seenOf = [];
    foreach ($session['tasks'] as $task) {
        $mode = (string) $task['card']['exercise_mode'];
        $termId = (string) $task['card']['term_id'];
        $modes[] = $mode;
        $seenOf[$termId] = ($seenOf[$termId] ?? 0) + 1;
        if ($mode === 'intro') {
            // FIRST FOR ITS OWN CARD, and once. `$seenOf` is the ordinal this card has reached.
            expect($seenOf[$termId])->toBe(1);
            $introOf[$termId] = ($introOf[$termId] ?? 0) + 1;
        }

        // Stage A, in the plan's own ladder, on every card of the sitting — the day's own and the
        // rescue kit's. The kit is here because it has not been answered today (день 1 не открывали)
        // and «разогрев каждый день» does not care which day the learner opened; it stands on stage
        // A for the same reason the day does, so «только A-режимы» covers both.
        expect($task['stage'])->toBe('a');

        if ($task['section'] === 'warmup') {
            expect($task['shelf'])->toBe('rescue')
                ->and($task['source'])->toBe('warmup');

            continue;
        }

        expect($task['section'])->toBe('day')
            ->and($task['source'])->toBe('new')
            ->and($task['from_day_index'])->toBe(2);
    }

    // The day's own cards are the bulk of it, and the seam is empty — a day opened early revises
    // nothing (there is nothing behind it that has closed a stage).
    $sections = array_column($session['tasks'], 'section');
    expect($sections)->toContain('day')
        ->and($sections)->not->toContain('review');

    // Every card of the sitting was introduced, exactly once.
    expect($introOf)->toEqual(array_map(static fn (): int => 1, $introOf))
        ->and(count($introOf))->toBe(count($seenOf));

    // Стage B's trainers are the ones a soft run reached for, and there is no way to any of them:
    // stage A has not closed, and a night has not passed.
    foreach (['listening', 'typing', 'cloze', 'dictation', 'situational_hear', 'situational_say', 'situational_ask'] as $forbidden) {
        expect($modes)->not->toContain($forbidden);
    }

    // …and no card off the `numbers` shelf, which no session deals yet (канон §6).
    foreach ($session['tasks'] as $task) {
        expect($task['shelf'] ?? null)->not->toBe('numbers');
        expect($task['kind'] ?? null)->not->toBe('number');
    }

    // STRICT = STUDY. The answers of a day opened early are ordinary reviews that close its stage.
    expect(DB::table('study_sessions')->where('id', $session['session_id'])->value('is_practice'))->toBeFalsy();
});

it('closes a day opened early when its stage A closes, and moves the focus onto it', function () {
    // The other half of «фокус и закрытие дней работают как у фокусного»: an early day is not a
    // read-through any more, so walking it has to COUNT. Under the soft run it counted for nothing —
    // `ladder_step` came back null on every answer and no stage ever closed.
    [$user, $token, $planId] = startedPlan($this);

    $seq = 1;
    for ($i = 0; $i < 8; $i++) {
        $session = planSession($this, $token, $planId, 2);
        if ($session['tasks'] === []) {
            break;
        }
        $seq = answerTasks($this, $token, $session, $seq);
    }

    expect(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->value('status'))
        ->toBe('done')
        // Day 1 was never opened, so the focus stays on it: «пройден» is a fact about a day, and
        // the focus is the first day that is not.
        ->and(DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('status'))
        ->not->toBe('done');

    expect(planSession($this, $token, $planId)['focus_day_index'])->toBe(1);
});

it('answers `scope=plan` on the ordinary session path with the plan’s own day', function () {
    [, $token, $planId] = startedPlan($this);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['scope' => 'plan'])
        ->assertOk()
        ->json('data');

    expect($body['plan_id'])->toBe($planId)
        ->and($body['strict'])->toBeTrue()
        ->and($body['day_index'])->toBe(1);
});

it('falls back to the ordinary session when `scope=plan` and no plan is running', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['scope' => 'plan'])
        ->assertOk()
        ->json('data');

    // The ordinary payload, not the plan one: `cards`, no `plan_id`.
    expect($body)->toHaveKey('cards')
        ->and($body)->not->toHaveKey('plan_id');
});

it('404s a plan that belongs to somebody else', function () {
    [, , $planId] = startedPlan($this);
    [, $other] = learner();

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — without this the second bearer token is never
    // looked at and the test would pass while proving nothing.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$other}")
        ->postJson("/api/v1/plans/{$planId}/session")
        ->assertStatus(404);
});

it('gives every reply of a day its assembly step — four, not three (С-7)', function () {
    // The live measure behind Ч-6: on the stand three of day 1's four «Ты ответишь» replies came
    // back with `of_steps = 3`, and one — the pair whose counter happened to prefer `word_bank` —
    // with four. The missing step was the assembly one, dropped because `scramble` needs an example
    // and a line has none. Nothing in the payload said a step had gone missing; `of_steps` simply
    // said 3, and a stage that owed three steps closed on three.
    [, $token, $planId] = startedPlan($this);

    $session = planSession($this, $token, $planId, 1);

    $seen = [];
    foreach ($session['tasks'] as $task) {
        if ($task['section'] !== 'day' || ! in_array($task['shelf'] ?? null, ['say', 'ask'], true)) {
            continue;
        }
        $seen[$task['card']['term_id']][] = $task['card']['exercise_mode'];
        expect($task['of_steps'])->toBe(4);
    }

    expect($seen)->not->toBeEmpty();
    foreach ($seen as $modes) {
        // …and the step that was missing is a real assembly card, whichever of the two it is.
        expect(array_intersect(['word_bank', 'scramble'], $modes))->not->toBeEmpty();
    }
});
