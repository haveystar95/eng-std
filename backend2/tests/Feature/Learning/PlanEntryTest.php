<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanDefectReporter;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * THE ENTRY, END TO END — what the listening step buys, and what «Без даты» is.
 *
 * Two facts of наряд ENTRY-2 that only exist once they have crossed the whole path: the answers
 * the learner tapped on кадр V4·03б have to arrive in P1's `{{diagnostics}}` and in P2's
 * `{{balance}}`, and a plan created with `event_date: null` has to come out as a plan rather than
 * as an error. Both are asserted against the PROMPT TEXT the model was handed, because that is the
 * only place the value actually does anything.
 */
beforeEach(function (): void {
    $this->prompts = new class
    {
        /** @var list<string> */
        public array $seen = [];
    };

    $recorder = $this->prompts;

    $model = new class($recorder) implements ContentModelPort
    {
        public function __construct(private object $recorder) {}

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
            $this->recorder->seen[] = $prompt->text;

            $properties = $schema['properties'] ?? [];
            $isDay = is_array($properties) && isset($properties['hear']);
            $isListen = is_array($properties) && isset($properties['lines']);

            if ($isListen) {
                // The prompt's own rule: no target language → no lines, continuations only. The
                // fake obeys it, so the test measures the PATH rather than a model that ignores it.
                $languageChosen = ! str_contains($prompt->text, "Target language:  —");

                return $this->answer([
                    'lines' => $languageChosen ? [
                        ['text' => 'Do you have an appointment?', 'translation' => 'У вас есть запись?', 'place' => 'на стойке'],
                        ['text' => 'What seems to be the problem?', 'translation' => 'Что случилось?', 'place' => 'в кабинете'],
                        ['text' => 'Please take a seat.', 'translation' => 'Присаживайтесь.', 'place' => 'в коридоре'],
                    ] : [],
                    'continuations' => [
                        'и понять, что скажет врач про лечение',
                        'и записать ребёнка на приём',
                    ],
                ]);
            }

            /** @var array<string, mixed> $payload */
            $payload = json_decode(
                (string) file_get_contents(
                    __DIR__ . '/../../Fixtures/plan/' . ($isDay ? 's1-day1.v0.4.json' : 's1-outline.v0.4.json'),
                ),
                true,
            );

            return $this->answer($payload);
        }

        /** @param array<string, mixed> $payload */
        private function answer(array $payload): ModelAnswer
        {
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

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($model, $prompts, $ledger, app(PlanDefectReporter::class)));
    app()->instance(PlanDayComposer::class, new PlanDayComposer($model, $prompts, $ledger, app(PlanDefectReporter::class)));
    app()->instance(\App\Modules\Learning\Application\Port\ListenWarmupPort::class, new \App\Modules\Generation\Application\Service\PlanListenService(
        $model,
        $prompts,
        $ledger,
        app(\App\Modules\Generation\Application\Port\ListenWarmupReporter::class),
    ));
});

/** The whole entry, in the order the screens ask (кадры V4·01 → 06). */
function walkEntry(string $token, ?string $eventDate, array $listening): array
{
    return test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => $eventDate,
            'minutes_per_day' => 20,
            'listening' => $listening,
        ])->assertCreated()->json('data');
}

/** @param list<bool> $verdicts */
function tapped(array $verdicts): array
{
    $out = [];
    foreach ($verdicts as $i => $understood) {
        $n = $i + 1;
        $out[] = [
            'text' => "Line {$n} of the situation?",
            'translation' => "Реплика {$n}?",
            'place' => 'на стойке',
            'understood' => $understood,
        ];
    }

    return $out;
}

it('offers three lines to listen to before any plan exists', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $lines = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans/listen-warmup', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
        ])->assertOk()->json('data.lines');

    expect($lines)->toHaveCount(3)
        ->and($lines[0]['text'])->toBe('Do you have an appointment?')
        ->and($lines[0]['place'])->toBe('на стойке')
        // No plan was created by asking — the step is before the plan and must not leave one.
        ->and(\App\Modules\Learning\Infrastructure\Eloquent\PlanModel::query()->count())->toBe(0);
});

it('answers the goal step with continuations alone — no language, no lines', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // «Дописать за тебя» на кадре V4·01в: язык ещё не выбран, поэтому его в запросе НЕТ.
    $data = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans/listen-warmup', [
            'goal_text' => 'Иду к врачу, болит спина',
            'level' => 'basic',
        ])->assertOk()->json('data');

    expect($data['continuations'])->toHaveCount(2)
        ->and($data['continuations'][0])->toContain('понять, что скажет врач')
        ->and($data['lines'])->toBe([])
        // Ни плана, ни черновика: блок подсказок ничего не создаёт.
        ->and(\App\Modules\Learning\Infrastructure\Eloquent\PlanModel::query()->count())->toBe(0);
});

it('still refuses a target language it does not teach', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    // Отсутствие языка — вопрос, а мусор на его месте — по-прежнему 422.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans/listen-warmup', [
            'goal_text' => 'Иду к врачу, болит спина',
            'target_lang' => 'klingon',
            'level' => 'basic',
        ])->assertStatus(422);
});

it('carries a mixed listening result into both prompts as «упор на понимание»', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = walkEntry($token, now()->addDays(3)->format('Y-m-d'), tapped([true, false, true]));

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $outlinePrompt = $this->prompts->seen[0];
    $dayPrompt = collect($this->prompts->seen)->first(fn (string $p): bool => str_contains($p, 'SHELF SPECIFICS'));

    expect($outlinePrompt)->toContain('Line 1 of the situation?')
        ->and($outlinePrompt)->toContain('LISTENING COMPREHENSION')
        ->and($dayPrompt)->not->toBeNull()
        // THE BALANCE RULE: the value reaches P2, and the rule that reads it is in the prompt.
        ->and($dayPrompt)->toContain("Balance (may be empty): understanding")
        ->and($dayPrompt)->toContain('fill the hear shelf toward the upper end of its guide');
});

it('carries an all-understood result as «упор на говорение»', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = walkEntry($token, now()->addDays(3)->format('Y-m-d'), tapped([true, true, true]));
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $dayPrompt = collect($this->prompts->seen)->first(fn (string $p): bool => str_contains($p, 'SHELF SPECIFICS'));

    expect($this->prompts->seen[0])->toContain('put the weight on SPEAKING')
        ->and($dayPrompt)->toContain('Balance (may be empty): speaking');
});

it('builds the same plan with the step skipped, and sends an empty balance', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = walkEntry($token, now()->addDays(3)->format('Y-m-d'), []);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    $dayPrompt = collect($this->prompts->seen)->first(fn (string $p): bool => str_contains($p, 'SHELF SPECIFICS'));

    expect($this->prompts->seen[0])->toContain('empty — the user skipped the listening step')
        // The placeholder is REPLACED with nothing, never left standing.
        ->and($dayPrompt)->toContain('Balance (may be empty):  —')
        ->and($dayPrompt)->not->toContain('{{balance}}');
});

// ── «Без даты» (кадры V4·04б, 06б) ────────────────────────────────────────────────────────────

it('builds a plan with no date: scenes in order, nothing scheduled, no countdown', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = walkEntry($token, null, []);

    expect($plan['event_date'])->toBeNull();

    $outlined = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk()->json('data');

    expect($outlined['event_date'])->toBeNull()
        // Nothing to count down to, and NOT zero — zero would read as «событие сегодня».
        ->and($outlined['days_to_event'])->toBeNull()
        // A deadline that does not exist cannot be tight.
        ->and($outlined['deadline_tight'])->toBeFalse()
        // The S1 skeleton has two scenes, so: two teaching days plus the rehearsal at the end.
        ->and($outlined['computed']['intro_days'])->toBe(2)
        ->and($outlined['computed']['fits'])->toBeTrue()
        ->and($outlined['computed']['rest_days'])->toBe(0)
        ->and($outlined['days'])->toHaveCount(3)
        ->and($outlined['days'][2]['kind'])->toBe('final');

    // No day carries a date: they open one after another instead.
    foreach ($outlined['days'] as $day) {
        expect($day['scheduled_on'])->toBeNull();
    }
});

it('lets an undated plan be given a date later — «Поставить дату»', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = walkEntry($token, null, []);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();

    $dated = $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/plans/{$plan['id']}/outline", [
            'event_date' => now()->addDays(4)->format('Y-m-d'),
        ])->assertOk()->json('data');

    expect($dated['event_date'])->toBe(now()->addDays(4)->format('Y-m-d'))
        ->and($dated['days_to_event'])->toBe(4)
        ->and($dated['days'][0]['scheduled_on'])->not->toBeNull();
});

it('refuses a create that simply forgot the date, rather than making an undated plan', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'minutes_per_day' => 20,
        ])->assertStatus(422);
});
