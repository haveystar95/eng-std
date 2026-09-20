<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\RecallStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ВСПОМНИТЬ» (наряд CONV-1, кадры 37-1, 37-3, 37-4): the rehearsal's own stage — one sheet of the plan's lines, then
 * five or six of them said aloud. Which lines are said is the DIALOGUE's answer, not a shuffle: the frames the visit
 * leans on most, one line each. The fake lesson says `p6` in two exchanges (x7 and x8) and every other frame in one.
 */

/** The scene the rehearsal is built from — the fake lesson, served, with its terms. */
function s1reScene(int $n = 1): SceneMaterial
{
    $sceneId = PlanSceneId::fromString(sprintf('01J8RECA11STAGE000000000%02d', $n));
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest(
        'Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays,
    ));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));
    $terms = PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate());

    return new SceneMaterial($sceneId, $lesson, $terms, $packs->for('en'), $packs->for('ru'));
}

/** @return list<string> `kind@ref` of each draft */
function s1reShape(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->kind->value.'@'.$d->unitRef, $drafts);
}

// Catches a sheet that lists somebody else's lines (the partner's), a sheet that skips a scene, and a sheet with a
// right answer on it: it is read, not answered.
it('lays the plan\'s own lines on one sheet, scene by scene, with their translation', function () {
    $scenes = [s1reScene(1), s1reScene(2)];
    $cards = (new RecallStage)->build($scenes);
    $sheet = $cards[0];
    /** @var array<int, array<string, mixed>> $onIt */
    $onIt = $sheet->payload['scenes'];

    expect($sheet->kind)->toBe(CardKind::RecallScenes)
        ->and($sheet->unitKind)->toBe(UnitKind::Day)
        ->and($sheet->payload['scene_id'])->toBe($scenes[0]->sceneId->value)
        ->and(array_column($onIt, 'scene_id'))->toBe([$scenes[0]->sceneId->value, $scenes[1]->sceneId->value])
        ->and(array_keys($sheet->payload))->toBe(['scene_id', 'scenes']);

    foreach ($onIt as $scene) {
        expect($scene['lines'])->not->toBeEmpty();
        foreach ($scene['lines'] as $line) {
            expect(array_keys($line))->toBe(['step', 'ref', 'text_target', 'text_native', 'frame_ref', 'filler_index', 'key', 'audio'])
                // The learner's own line: `x3b`, not the partner's `x3` (кадр 37-3 — «Вспомни СВОИ реплики»).
                ->and($line['ref'])->toEndWith('b')
                ->and($line['text_native'])->not->toBeEmpty();
        }
    }
});

/**
 * Canon: «5–6 карточек speak_retell по самым частым в диалоге репликам всех сцен». Catches a selection by chance, a
 * selection that says one frame twice, and a rehearsal that says more lines than the order allows.
 */
it('says aloud the lines of the frames the dialogue leans on most, one line per frame, at most six', function () {
    $scene = s1reScene(1);
    $said = array_slice((new RecallStage)->build([$scene]), 1);

    // `p6` stands on two exchanges of the fake visit, every other frame on one: it goes first, as its earliest line.
    expect(s1reShape($said)[0])->toBe('speak_retell@x7')
        // The ceiling is written out here on purpose: an assertion against the constant cannot catch the constant moving.
        ->and(count($said))->toBeLessThanOrEqual(6)
        ->and(array_unique(array_map(static fn (CardDraft $d): string => (string) $d->payload['own_line']['frame_ref'], $said)))
        ->toHaveCount(count($said))
        ->and(array_unique(array_map(static fn (CardDraft $d): string => $d->kind->value, $said)))->toBe(['speak_retell'])
        // Dealt twice — the same lines in the same order.
        ->and(s1reShape(array_slice((new RecallStage)->build([$scene]), 1)))->toBe(s1reShape($said));
});

// Three scenes of the same material: every scene's most-said frame comes before any scene's second, by plan order.
it('takes the scenes in the plan\'s order when they lean on their frames equally', function () {
    $scenes = array_map(static fn (int $n): SceneMaterial => s1reScene($n), [1, 2, 3]);
    $said = array_slice((new RecallStage)->build($scenes), 1);
    $byScene = array_map(static fn (CardDraft $d): string => (string) $d->payload['scene_id'], $said);

    expect($said)->toHaveCount(6)
        ->and(array_slice($byScene, 0, 3))->toBe(array_map(static fn (SceneMaterial $s): string => $s->sceneId->value, $scenes))
        ->and(s1reShape(array_slice($said, 0, 3)))->toBe(['speak_retell@x7', 'speak_retell@x7', 'speak_retell@x7']);
});

// A plan with no ready scene has no sheet and no lines: the stage is simply not dealt.
it('deals nothing at all without a scene', function () {
    expect((new RecallStage)->build([]))->toBe([]);
});
