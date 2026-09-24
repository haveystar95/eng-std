<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\RecallStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ВСПОМНИТЬ» (наряд CONV-1, кадры 37-1, 37-3, 37-4): the rehearsal's own stage — one sheet of the plan's lines, then
 * five or six of them said aloud. Which lines are said is the DIALOGUE's answer, not a shuffle: the frames the visit
 * leans on most, one line each. The fake lesson says `p6` in two exchanges (x7 and x8) and every other frame in one.
 */

/**
 * The scene the rehearsal is built from — the fake lesson, served, with its terms — named by the PLAN otherwise than the
 * lesson names itself («Приём у врача» / «At the doctor's with a child» in its `topic`), as the owner's plan was.
 */
function s1reScene(int $n = 1): SceneMaterial
{
    $sceneId = PlanSceneId::fromString(sprintf('01J8RECA11STAGE000000000%02d', $n));
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest(
        'Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, FakePlanModel::roles(), new EarlierDays,
    ));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));
    $terms = planTermsOf($sceneId, $lesson);

    return new SceneMaterial($sceneId, $lesson, $terms, $packs->for('en'), $packs->for('ru'), [], "Сцена плана {$n}", "Plan scene {$n}");
}

/** @return list<string> `kind@ref` of each draft */
function s1reShape(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->kind->value.'@'.$d->unitRef, $drafts);
}

// Canon (наряд BACK-TAILS-2 §§5–6): «обзор по сценам: сцена (title_native плана), реплики ученика {ref, text_target,
// text_native, audio} в порядке визита; реплики собеседника не едут ни в каком поле; имя сцены — из плана». CATCHES a
// sheet that lists somebody else's lines (the partner's) under any key, a sheet that skips a scene, a sheet with a right
// answer on it (it is read, not answered), and a scene named by the lesson's own title instead of the plan's.
it('lays the plan\'s own lines on one sheet, scene by scene, with their translation — named as the plan names them', function () {
    $scenes = [s1reScene(1), s1reScene(2)];
    $cards = (new RecallStage)->build($scenes);
    $sheet = $cards[0];
    /** @var array<int, array<string, mixed>> $onIt */
    $onIt = $sheet->payload['scenes'];

    expect($sheet->kind)->toBe(CardKind::RecallScenes)
        ->and($sheet->unitKind)->toBe(UnitKind::Day)
        ->and($sheet->payload['scene_id'])->toBe($scenes[0]->sceneId->value)
        ->and(array_column($onIt, 'scene_id'))->toBe([$scenes[0]->sceneId->value, $scenes[1]->sceneId->value])
        ->and(array_keys($sheet->payload))->toBe(['scene_id', 'scenes'])
        // The plan's names — not the lesson's «Приём у врача» / «At the doctor's with a child».
        ->and(array_column($onIt, 'title_native'))->toBe(['Сцена плана 1', 'Сцена плана 2'])
        ->and(array_column($onIt, 'title_target'))->toBe(['Plan scene 1', 'Plan scene 2'])
        ->and($scenes[0]->lesson->titleNative)->toBe('Приём у врача');

    $partner = [];
    foreach ($scenes[0]->lesson->exchanges as $exchange) {
        if ($exchange->partner() !== null) {
            $partner[] = $exchange->partner()->textTarget;
            $partner[] = $exchange->partner()->textNative;
        }
    }
    $strings = static function (mixed $value) use (&$strings): array {
        return is_array($value) ? array_merge(...array_values(array_map($strings, $value)) ?: [[]]) : (is_string($value) ? [$value] : []);
    };
    expect(array_intersect($strings($sheet->payload), $partner))->toBe([]);

    foreach ($onIt as $scene) {
        // The learner's own lines of the visit, in its order: x1b…x5b, then the two asks — the rescue x6 is no own line.
        expect(array_column($scene['lines'], 'ref'))->toBe(['x1b', 'x2b', 'x3b', 'x4b', 'x5b', 'x7b', 'x8b']);
        foreach ($scene['lines'] as $line) {
            expect(array_keys($line))->toBe(['ref', 'text_target', 'text_native', 'audio'])
                ->and($line['text_native'])->not->toBeEmpty()
                ->and($line['audio']['voice'])->toBe('learner');
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
