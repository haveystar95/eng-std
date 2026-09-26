<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\DayBuildLog;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Assembly\PhrasesDeal;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * THE STOP SIGNAL OF «ФРАЗЫ» IN THE DAY'S BUILD LOG (наряд BACK-TAILS-2 §1, п. 4): when the ladder has spent every rung and
 * the stage is still over its ceiling, the day is dealt anyway and the dealing says so — once, when the day is dealt, with
 * the stage's numbers. Reading a day (the window, the room, the route draw the dealer's outline) is not dealing it.
 */

/** A build log that keeps what it was told. */
function bt2BuildLog(): DayBuildLog
{
    return new class implements DayBuildLog
    {
        /** @var list<array{plan: string, day: int, scene: string, deal: PhrasesDeal}> */
        public array $signals = [];

        public function phrasesOverCeiling(PlanId $plan, PlanDayId $day, int $dayNumber, PlanSceneId $scene, PhrasesDeal $deal): void
        {
            $this->signals[] = ['plan' => $plan->value, 'day' => $dayNumber, 'scene' => $scene->value, 'deal' => $deal];
        }

        /** @var list<array{plan: string, scene: string, refs: list<string>}> */
        public array $assembled = [];

        public function hintsAssembled(PlanId $plan, PlanSceneId $scene, array $refs): void
        {
            $this->assembled[] = ['plan' => $plan->value, 'scene' => $scene->value, 'refs' => $refs];
        }
    };
}

/** @return array{log: DayBuildLog, token: string, id: string} a started plan of two days, the build log listening */
function bt2Started(object $ctx): array
{
    // Bound before the first request: a route keeps the controller it built, handlers and ports and all.
    $log = bt2BuildLog();
    app()->instance(DayBuildLog::class, $log);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    return ['log' => $log, 'token' => $token, 'id' => $id];
}

// Canon: «после ступени 3 — стоп-сигнал в лог сборки дня (warning с числами этапа), не поломка и не откат». CATCHES a
// signal that is not written, written on every read of the day, written without the stage's numbers, or a day that is not
// dealt because of it.
it('writes the stop signal of «Фразы» to the day\'s build log once, with its numbers, and deals the day anyway', function () {
    // A ceiling the clean day cannot fit in at any rung (read before the plan's config is first resolved).
    config(['plan.phrases_budget' => 100]);
    ['log' => $log, 'token' => $token, 'id' => $id] = bt2Started($this);

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk();
    expect($log->signals)->toBe([]);

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    $phrases = array_values(array_filter($cards, static fn (array $c): bool => $c['stage'] === 'phrases'));

    expect($log->signals)->toHaveCount(1)
        ->and($log->signals[0]['plan'])->toBe($id)
        ->and($log->signals[0]['day'])->toBe(1)
        ->and($log->signals[0]['scene'])->toBe($room->json('data.scene.id'))
        ->and($log->signals[0]['deal']->budget)->toBe(100)
        ->and($log->signals[0]['deal']->seconds)->toBeGreaterThan(100)
        ->and(array_column($log->signals[0]['deal']->rungs, 'rung'))->toBe([0, 1, 2])
        // The day is the stage the signal speaks of: every card of it dealt, the floor's shape and nothing less.
        ->and($phrases)->toHaveCount(count($log->signals[0]['deal']->drafts))
        ->and(array_values(array_unique(array_map(static fn (array $f): int => $f['recognitions'], $log->signals[0]['deal']->frames))))->toBe([1]);

    // Reading the dealt day again deals nothing and says nothing more.
    $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk();
    planRead($this, $token, $id);
    expect($log->signals)->toHaveCount(1);
});

// Canon: a stage that fits its ceiling is no signal. CATCHES a log line on every dealing.
it('writes nothing to the build log when «Фразы» fit their ceiling', function () {
    ['log' => $log, 'token' => $token, 'id' => $id] = bt2Started($this);

    planOpenDay($this, $token, $id, 1);

    expect($log->signals)->toBe([]);
});

// Наряд LANG-1b §3: «где строки нет — склейка как запас, с пометкой в журнале». The clean «врач» with no learner line on its
// frame p1 (the line re-pointed to no frame): the talk will hint p1 with the frame put together, and the lesson's build says
// so once, in the day's build log. CATCHES a hint put together without a word in the log — and a log line for a lesson that
// has a line of every frame.
it('names in the build log the frames the talk will hint by putting the frame together, once, when the lesson is written', function () {
    $log = bt2BuildLog();
    app()->instance(DayBuildLog::class, $log);
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function ($request): array {
        $p = planCleanLesson($request);
        foreach ($p['dialogue'] as $x => $exchange) {
            foreach ($exchange['messages'] as $m => $message) {
                if (($message['phrase_id'] ?? null) === 'p1') {
                    $p['dialogue'][$x]['messages'][$m]['phrase_id'] = null;
                }
            }
        }

        return $p;
    }));
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = (string) DB::table('plan_scenes')->where('plan_id', $id)->value('id');

    expect($log->assembled)->toBe([['plan' => $id, 'scene' => $scene, 'refs' => ['p1']]]);
});

it('writes nothing of hints to the build log for a lesson that says every frame', function () {
    $log = bt2BuildLog();
    app()->instance(DayBuildLog::class, $log);
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: planCleanLesson(...)));
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 1]);

    expect($log->assembled)->toBe([]);
});
