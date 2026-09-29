<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\BlueprintChecker;
use App\Modules\Plan\Domain\Check\BlueprintContext;
use App\Modules\Plan\Domain\Check\CheckReport;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Domain\ValueObject\Finding;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/** THE PLAN CHECKS (docs/plan-v2.md §5, the plan half of the table). */
function bpPayload(int $scenes = 5): array
{
    return FakePlanModel::planPayload(new PlanRequest('Иду к врачу с ребёнком', 'English', 'Russian', PlanLevel::Beginner, $scenes));
}

/** @return CheckReport<Blueprint> */
function bpRun(array $payload, string $check, CheckMode $mode, int $scenesCount = 5): CheckReport
{
    $blueprint = (new BlueprintParser)->parse($payload);

    return (new BlueprintChecker(CheckModes::fromArray([$check => $mode->value])))->run($blueprint, new BlueprintContext($scenesCount, 0));
}

function bpFindingsOf(CheckReport $report, string $check): array
{
    return array_values(array_filter($report->findings, static fn (Finding $f): bool => $f->check === $check));
}

it('lets an unclear answer through untouched — it is an answer, not a defect', function () {
    $payload = FakePlanModel::planPayload(new PlanRequest('хочу подтянуть английский', 'English', 'Russian', PlanLevel::Beginner, 3));
    $report = (new BlueprintChecker(CheckModes::allObserve()))->run((new BlueprintParser)->parse($payload), new BlueprintContext(3, 0));

    expect($report->answer->isUnclear())->toBeTrue()
        ->and($report->answer->unclearReason)->not->toBe('')
        ->and($report->findings)->toBe([])
        ->and($report->gated)->toBeFalse();
});

it('refuses a status outside ok/unclear as off-schema', function () {
    $p = bpPayload();
    $p['status'] = 'maybe';

    expect(fn () => (new BlueprintParser)->parse($p))->toThrow(ModelAnswerOffSchema::class);
});

it('plan_shape: the wrong scene count is counted in observe and refused in gate', function () {
    $p = bpPayload(4);

    $observed = bpRun($p, 'plan_shape', CheckMode::Observe);
    expect(bpFindingsOf($observed, 'plan_shape')[0]->action)->toBe(CheckAction::Counted)
        ->and($observed->gated)->toBeFalse()
        ->and(count($observed->answer->scenes))->toBe(4);

    expect(bpRun($p, 'plan_shape', CheckMode::Gate)->gated)->toBeTrue();
});

it('plan_shape: an order that is not 1..N is caught', function () {
    $p = bpPayload();
    $p['scenes'][2]['order'] = 7;

    expect(bpFindingsOf(bpRun($p, 'plan_shape', CheckMode::Observe), 'plan_shape'))->toHaveCount(1)
        ->and(bpRun($p, 'plan_shape', CheckMode::Gate)->gated)->toBeTrue();
});

it('priorities: duplicates are counted in observe and renumbered by order in drop, the first situation as core', function () {
    $p = bpPayload();
    foreach ($p['scenes'] as $i => $_) {
        $p['scenes'][$i]['priority'] = 1;
    }

    $observed = bpRun($p, 'priorities', CheckMode::Observe);
    expect(bpFindingsOf($observed, 'priorities'))->not->toBeEmpty()
        ->and(array_map(static fn (SceneBrief $s): int => $s->priority, $observed->answer->scenes))->toBe([1, 1, 1, 1, 1]);

    $dropped = bpRun($p, 'priorities', CheckMode::Drop);
    expect($dropped->gated)->toBeFalse()
        ->and(array_map(static fn (SceneBrief $s): int => $s->priority, $dropped->answer->scenes))->toBe([1, 2, 3, 4, 5])
        ->and(bpFindingsOf($dropped, 'priorities')[0]->action)->toBe(CheckAction::Dropped);
});

it('priorities: no priority 1 at all is caught and drop makes the first situation the core', function () {
    $p = bpPayload();
    $p['scenes'][0]['kind'] = 'variant';
    foreach ($p['scenes'] as $i => $_) {
        $p['scenes'][$i]['priority'] = $i + 2;
    }

    $dropped = bpRun($p, 'priorities', CheckMode::Drop);
    $priorities = array_map(static fn (SceneBrief $s): int => $s->priority, $dropped->answer->scenes);

    // Scene 1 is a variant now, so the core is scene 2 — the first `situation`.
    expect($priorities)->toBe([2, 1, 3, 4, 5]);
});

it('topic_parts: a brief that is not the three labelled lines of v2.1 is counted or refused', function (string $brief) {
    $p = bpPayload();
    $p['scenes'][1]['topic_description'] = $brief;

    expect(bpFindingsOf(bpRun($p, 'topic_parts', CheckMode::Observe), 'topic_parts'))->toHaveCount(1)
        ->and(bpRun($p, 'topic_parts', CheckMode::Gate)->gated)->toBeTrue()
        ->and(bpRun($p, 'topic_parts', CheckMode::Drop)->gated)->toBeFalse()
        ->and(bpFindingsOf(bpRun(bpPayload(), 'topic_parts', CheckMode::Observe), 'topic_parts'))->toBe([]);
})->with([
    'one line' => ['Situation: consultation. Learner: parent. Partner: doctor. Not in this scene: payment.'],
    'the five parts of v2' => ["Situation: consultation.\nLearner: parent.\nPartner: doctor.\nPartner will: ask.\nLearner must: answer."],
    'no partner' => ["Situation: consultation.\nLearner: parent.\nNot in this scene: payment."],
    'out of order' => ["Learner: parent. Partner: doctor.\nSituation: consultation.\nNot in this scene: payment."],
]);

it('goals_count: five goals are cut to four in drop; two goals are only counted', function () {
    $p = bpPayload();
    $p['scenes'][0]['goals_native'] = ['а', 'б', 'в', 'г', 'д'];
    $p['scenes'][1]['goals_native'] = ['а', 'б'];

    $dropped = bpRun($p, 'goals_count', CheckMode::Drop);
    expect(bpFindingsOf($dropped, 'goals_count'))->toHaveCount(2)
        ->and($dropped->answer->scenes[0]->goalsNative)->toBe(['а', 'б', 'в', 'г'])
        ->and($dropped->answer->scenes[1]->goalsNative)->toBe(['а', 'б']);
});

it('char_limits: an overlong title, teaches or goal is counted whatever the config says', function () {
    $p = bpPayload();
    $p['scenes'][0]['title_native'] = 'Очень длинное название дня';
    $p['scenes'][0]['teaches_native'] = 'научиться описывать боль и понимать все назначения врача';
    $p['scenes'][0]['goals_native'] = ['описать, где и как именно болит спина', 'б', 'в'];

    $report = bpRun($p, 'char_limits', CheckMode::Gate);

    expect(bpFindingsOf($report, 'char_limits'))->toHaveCount(3)
        ->and(bpFindingsOf($report, 'char_limits')[0]->action)->toBe(CheckAction::Counted)
        ->and($report->gated)->toBeFalse();
});

it('passes a clean plan with no findings', function () {
    $report = (new BlueprintChecker(CheckModes::allObserve()))->run((new BlueprintParser)->parse(bpPayload()), new BlueprintContext(5, 0));

    expect($report->findings)->toBe([])->and($report->gated)->toBeFalse();
});
