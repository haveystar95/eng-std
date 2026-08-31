<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanDayDefectReporter;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// This file writes users, plans, terms AND — since it switches the dark trainers on — a global
// settings row. Without the rollback those rows outlive the file and the next test measures them:
// `learning_mode_settings` left `intro` on turned every «two new words → four cards» assertion in
// StudyApiTest into six. Every other plan file already did this; this one only got away with it
// because it had never written anything global.
uses(RefreshDatabase::class);

/**
 * THE WHOLE PATH, on the material a person actually wrote.
 *
 * Every other plan test runs on {@see \App\Modules\Generation\Infrastructure\Adapter\FakePlanContentModel},
 * whose content is nonsense by design — «Day 1 line 3 about thing3» proves the machinery moves and
 * proves nothing about whether the machinery moves REAL material. This one replays the hand-written
 * S1 fixtures through the same pipeline: skeleton → schedule → day 1 → collection → session.
 *
 * It is the test that would have caught the two v0.1 defects at once. The frame rule, because a
 * fixture written by a person has frames a person would write; and the pictures, because a real day
 * has an `image_api_prompt` on every card and this asserts that it survives all the way onto the
 * term row.
 */
beforeEach(function (): void {
    $model = new class implements ContentModelPort
    {
        public function provider(): ProviderId
        {
            return ProviderId::OpenAi;
        }

        public function model(): string
        {
            return 'fixture-plan';
        }

        public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
        {
            $properties = $schema['properties'] ?? [];
            $isDay = is_array($properties) && isset($properties['phrases']);

            /** @var array<string, mixed> $payload */
            $payload = json_decode(
                (string) file_get_contents(
                    __DIR__ . '/../../Fixtures/plan/' . ($isDay ? 's1-day1.v0.3.json' : 's1-outline.v0.2.json'),
                ),
                true,
            );

            return new ModelAnswer(
                payload: $payload,
                model: 'fixture-plan',
                latencyMs: 0,
                tokensIn: 0,
                tokensOut: 0,
                costUsd: '0.000000',
                raw: '{}',
            );
        }
    };

    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($model, $prompts, $ledger));
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $model,
        $prompts,
        $ledger,
        app(PlanDayDefectReporter::class),
    ));
});

it('walks S1 from the skeleton to a ready day 1, and every gate lets it through', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])->assertCreated()->json('data');

    $outlined = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk()->json('data');

    // The скелет: four abilities at four cards each, two teaching days at fourteen cards a day.
    expect($outlined['computed']['need'])->toBe(16)
        ->and($outlined['computed']['capacity'])->toBe(14)
        ->and($outlined['computed']['intro_days'])->toBe(2)
        ->and($outlined['computed']['fits'])->toBeTrue();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $day1 = DB::table('learning_plan_days')->where('plan_id', $plan['id'])->where('day_index', 1)->first();

    expect($day1->status)->toBe('ready')
        ->and($day1->fail_reason)->toBeNull()
        ->and($day1->collection_id)->not->toBeNull();

    $terms = DB::table('terms as t')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $day1->collection_id)
        ->get(['t.id', 't.text', 't.kind', 't.frame', 't.speaker', 't.filler', 't.image_api_prompt']);

    // Eight lines, two connectors, four words — the split the day was asked for, landed.
    expect($terms)->toHaveCount(14)
        ->and($terms->where('kind', 'line'))->toHaveCount(8)
        ->and($terms->where('kind', 'chunk'))->toHaveCount(2)
        ->and($terms->where('kind', 'word'))->toHaveCount(4)
        // Two of the eight lines are the doctor's own, quoted from the skeleton.
        ->and($terms->where('speaker', 'role'))->toHaveCount(2)
        // Those two plus the learner's own repair move are the formulas — a frame with no hole is
        // stored as no frame at all, because a cloze gap cut from it would blank nothing.
        ->and($terms->where('kind', 'line')->whereNotNull('frame'))->toHaveCount(5)
        ->and($terms->where('kind', 'line')->whereNull('frame'))->toHaveCount(3)
        ->and($terms->whereNull('image_api_prompt'))->toHaveCount(0);

    // The frame is the line with a hole in it, the filler is what stands in the hole, and the line
    // is what the SERVER built out of the two — the model never wrote that sentence.
    $withFrame = $terms->firstWhere('text', 'It hurts in my lower back.');
    expect($withFrame->frame)->toBe('It hurts in my ___.')
        ->and($withFrame->filler)->toBe('lower back');
});

it('deals the ready day as a session, giving each card the chain its kind earns', function () {
    // `intro` and `speaking` ship dark — the release rule, not this test's subject. Thrown here so
    // the chain below is the full stage-A ladder and not a narrower one.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);

    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])->assertCreated()->json('data');

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/session")->assertOk()->json('data');

    $kinds = DB::table('terms')->pluck('kind', 'id')->all();

    $modesByKind = [];
    foreach ($session['tasks'] as $task) {
        $modesByKind[$kinds[$task['card']['term_id']] ?? 'word'][] = $task['card']['exercise_mode'];
    }

    expect(array_unique($modesByKind['line'] ?? []))->not->toContain('typing')
        ->and(array_unique($modesByKind['line'] ?? []))->not->toContain('dictation')
        ->and($modesByKind['word'] ?? [])->not->toBeEmpty();

    // THE READING, on the card that shows the word. The day's own hints («ит хётс ин май лоуэр
    // бэк») travelled from P2 through `term_transliterations` to the intro card, and stop there:
    // on any card that ASKS for the word, printing how it sounds prints the answer.
    $intro = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] === 'intro',
    ));
    $asked = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['card']['exercise_mode'] !== 'intro',
    ));

    expect($intro)->not->toBeEmpty()
        ->and(array_column(array_column($intro, 'card'), 'transliteration'))->not->toContain(null)
        ->and(array_unique(array_column(array_column($asked, 'card'), 'transliteration')))->toBe([null]);
});
