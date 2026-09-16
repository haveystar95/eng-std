<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\Options;
use App\Modules\Plan\Domain\Assembly\PartnerLines;
use App\Modules\Plan\Domain\Assembly\Retry;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\Spacing;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameParts;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE SHARED PIECES OF THE DAY'S ASSEMBLY (наряд SESSION-1a, разд. 0–2): the spacing of a stage, the seeded rotation,
 * the options of a choice, the copy's reshuffle, the audio stub, the frame's own words, the partner lines picked by
 * length and the scene's material.
 */

function s1hDraft(string $name): CardDraft
{
    return new CardDraft(CardKind::WordIntro, UnitKind::Word, 'v1', ['name' => $name]);
}

/**
 * @param  list<CardDraft>  $drafts
 * @return list<string>
 */
function s1hNames(array $drafts): array
{
    return array_map(static fn (CardDraft $d): string => $d->payload['name'], $drafts);
}

function s1hScene(): SceneMaterial
{
    $sceneId = PlanSceneId::fromString('01J8SESS10N1AHE1PERS000000');
    $packs = lessonPacks();
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));

    return new SceneMaterial(
        $sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()),
        $packs->for('en'), $packs->for('ru'),
    );
}

// Canon: at step i — A_i, then B_{i−1}, then C_{i−2}; what a list does not have is skipped.
it('spaces three lists of four as A_i, B_i−1, C_i−2', function () {
    $list = static fn (string $p): array => array_map(static fn (int $i): CardDraft => s1hDraft($p.$i), [1, 2, 3, 4]);

    expect(s1hNames(Spacing::interleave($list('a'), $list('b'), $list('c'))))
        ->toBe(['a1', 'a2', 'b1', 'a3', 'b2', 'c1', 'a4', 'b3', 'c2', 'b4', 'c3', 'c4'])
        ->and(s1hNames(Spacing::interleave($list('a'), $list('b'), [s1hDraft('c1'), s1hDraft('c2')])))
        ->toBe(['a1', 'a2', 'b1', 'a3', 'b2', 'c1', 'a4', 'b3', 'c2', 'b4'])
        // A unit without its third card leaves a hole, not a shift: the others keep their places.
        ->and(s1hNames(Spacing::interleave($list('a'), $list('b'), [s1hDraft('c1'), null, s1hDraft('c3'), s1hDraft('c4')])))
        ->toBe(['a1', 'a2', 'b1', 'a3', 'b2', 'c1', 'a4', 'b3', 'b4', 'c3', 'c4'])
        ->and(Spacing::interleave([], [], []))->toBe([]);
});

it('rotates reproducibly from a seeded place and walks the cycle unit by unit', function () {
    $cycle = [CardKind::WordChoose, CardKind::WordListen, CardKind::WordInLine];
    $walk = static fn (string $seed): array => array_map(static fn (int $i): CardKind => Rotation::pick($seed, $i, $cycle), range(0, 5));

    $picked = $walk('scene:words:check');
    $offset = (crc32('scene:words:check') & 0x7FFFFFFF) % 3;

    expect($picked)->toBe($walk('scene:words:check'))
        ->and($picked[0])->toBe($cycle[$offset])
        ->and($picked[1])->toBe($cycle[($offset + 1) % 3])
        ->and($picked[2])->toBe($cycle[($offset + 2) % 3])
        ->and(array_slice($picked, 3))->toBe(array_slice($picked, 0, 3));

    $starts = [];
    foreach (range(1, 12) as $n) {
        $starts[Rotation::pick("scene-{$n}:words:check", 0, $cycle)->value] = true;
    }
    expect(count($starts))->toBeGreaterThan(1)
        ->and(fn () => Rotation::pick('seed', 0, []))->toThrow(InvalidArgumentException::class);
});

// Canon: a wrong option never equals the right one or another wrong one, case aside; ids follow what is shown.
it('chooses distinct options, numbers them in the order shown and names the right one by its id', function () {
    $correct = ['text' => 'Боль', 'audio' => Audio::of('v1')];
    $candidates = [
        ['text' => ' боль '], ['text' => 'Жар', 'audio' => Audio::of('v2')], ['text' => ''], ['text' => 'жар'],
        ['text' => 'Кашель'], ['text' => 'Насморк'], ['text' => 'Лишнее'],
    ];
    $chosen = Options::choose('scene:v1:choose', $correct, $candidates, 4);
    $byId = array_column($chosen['options'], null, 'id');

    expect(array_column($chosen['options'], 'id'))->toBe(['o1', 'o2', 'o3', 'o4'])
        ->and(array_column($chosen['options'], 'text'))->toEqualCanonicalizing(['Боль', 'Жар', 'Кашель', 'Насморк'])
        ->and($byId[$chosen['correct']]['text'])->toBe('Боль')
        ->and($byId[$chosen['correct']]['audio'])->toBe(Audio::of('v1'))
        ->and(array_keys($byId[$chosen['correct']]))->toBe(['id', 'text', 'audio'])
        ->and(Options::choose('scene:v1:choose', $correct, $candidates, 4))->toBe($chosen)
        ->and(Options::choose('scene:v1:choose', $correct, $candidates, 3)['options'])->toHaveCount(3)
        ->and(Options::choose('scene:v1:choose', $correct, [], 4))->toBe(['options' => [['id' => 'o1', 'text' => 'Боль', 'audio' => Audio::of('v1')]], 'correct' => 'o1']);

    // Shuffled, not always first: over a dozen seeds the right option stands at more than one place.
    $places = [];
    foreach (range(1, 12) as $n) {
        $places[Options::choose("seed-{$n}", $correct, $candidates, 4)['correct']] = true;
    }
    expect(count($places))->toBeGreaterThan(1);
});

// D-06: the copy of a failed choice is shown in another order; the ids travel with their texts, `correct` stays right.
it('reshuffles the options and tiles of a copy by its seed and keeps the right answer', function () {
    $payload = [
        'scene_id' => 'scene',
        'options' => [['id' => 'o1', 'text' => 'one'], ['id' => 'o2', 'text' => 'two'], ['id' => 'o3', 'text' => 'three'], ['id' => 'o4', 'text' => 'four']],
        'correct' => 'o3',
        'tiles' => ['it', 'hurts', 'in', 'his', 'lower', 'back', 'upper'],
        'expected' => ['words' => ['It', 'hurts'], 'slot_at' => 2],
    ];
    $seed = '01J0CARD00000000000000000A:retry';
    $copy = Retry::payload($payload, $seed);

    expect($copy['options'])->toBe(Shuffle::seeded($seed, $payload['options']))
        ->and($copy['options'])->not->toBe($payload['options'])
        ->and($copy['tiles'])->toBe(Shuffle::seeded($seed, $payload['tiles']))
        ->and($copy['tiles'])->not->toBe($payload['tiles'])
        ->and($copy['tiles'])->toEqualCanonicalizing($payload['tiles'])
        ->and(array_column($copy['options'], 'text', 'id')[$copy['correct']])->toBe('three')
        ->and($copy['correct'])->toBe('o3')
        ->and($copy['expected'])->toBe($payload['expected'])
        ->and($copy['scene_id'])->toBe('scene')
        ->and(Retry::payload(['scene_id' => 'scene', 'lines' => [1, 2]], $seed))->toBe(['scene_id' => 'scene', 'lines' => [1, 2]]);
});

it('stubs a sound with its ref and voice, the address and length left for the reader', function () {
    expect(Audio::of('x3'))->toBe(['ref' => 'x3', 'voice' => 'partner', 'url' => null, 'duration_ms' => null])
        ->and(Audio::of('x3b')['voice'])->toBe('learner')
        ->and(Audio::of('p2.f3')['voice'])->toBe('learner')
        ->and(Audio::of('v5')['voice'])->toBe('learner')
        ->and(SpokenLines::exchangeRef(7))->toBe('x7')
        ->and(SpokenLines::stepOfRef('x12'))->toBe(12)
        ->and(SpokenLines::stepOfRef('x3b'))->toBeNull()
        ->and(SpokenLines::stepOfRef('p3'))->toBeNull();
});

it('reads a frame\'s own words outside its slot, and how many stand before it', function () {
    expect(FrameParts::part('It hurts in his ___.'))->toBe('It hurts in his')
        ->and(FrameParts::words('It hurts in his ___.'))->toBe(['It', 'hurts', 'in', 'his'])
        ->and(FrameParts::slotAt('It hurts in his ___.'))->toBe(4)
        ->and(FrameParts::part('The pain is ___ when he bends.'))->toBe('The pain is when he bends')
        ->and(FrameParts::slotAt('The pain is ___ when he bends.'))->toBe(3)
        ->and(FrameParts::part("I'd like a ___, please."))->toBe("I'd like a, please")
        ->and(FrameParts::words("I'd like a ___, please."))->toBe(["I'd", 'like', 'a', 'please'])
        ->and(FrameParts::part('I work ___ .'))->toBe('I work')
        ->and(FrameParts::part("He doesn't have a fever."))->toBe("He doesn't have a fever")
        ->and(FrameParts::slotAt("He doesn't have a fever."))->toBe(0);
});

// Canon: listen_pace takes the longest partner line of ≤ 10 words, speak_echo the longest of ≤ 18 other than it.
it('picks partner lines by length, the lower step between equals, never an excluded one', function () {
    $scene = s1hScene();

    expect(array_column($scene->partnerLines(), 'step'))->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        // Steps 3 and 7 both say ten words: the lower wins.
        ->and(PartnerLines::pace($scene)['step'])->toBe(3)
        ->and(PartnerLines::longest($scene, 10, [3])['step'])->toBe(7)
        ->and(PartnerLines::longest($scene, PartnerLines::SPEAK_MAX_WORDS, [3])['step'])->toBe(5)
        ->and(PartnerLines::longest($scene, PartnerLines::SPEAK_MAX_WORDS, [3, 5])['step'])->toBe(1)
        ->and(PartnerLines::longest($scene, 4))->toBeNull();
});

it('gives a scene\'s material its frames, the filler each phrase is said with, and seeds by the scene', function () {
    $scene = s1hScene();

    expect(array_map(static fn (PlanTerm $t): string => $t->ref(), $scene->phrases()))->toBe(['p1', 'p2', 'p3', 'p4', 'p5', 'p6'])
        ->and(array_map(static fn (PlanTerm $t): string => $t->ref(), $scene->vocabulary()))->toHaveCount(8)
        ->and($scene->phraseTerm('p1')?->ref())->toBe('p1')
        ->and($scene->phraseTerm('v1'))->toBeNull()
        ->and($scene->phraseTerm(null))->toBeNull()
        ->and($scene->saidIndex($scene->term('p2')))->toBe(0)
        ->and($scene->saidIndex($scene->term('p4')))->toBeNull()
        ->and($scene->seed('v1:choose'))->toBe('01J8SESS10N1AHE1PERS000000:v1:choose')
        ->and($scene->target->code)->toBe('en')
        ->and($scene->native->code)->toBe('ru');
});
