<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\CardObjects;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\Shuffle;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «ФРАЗЫ» (наряд SESSION-1a, разд. 1–2; SPEC §4): three cards per frame spaced A_i, B_i−1, C_i−2 and one
 * phrase_combine last; the seeded rotations of recognition and production; what a frame without a window and a frame
 * with one filler get; and the payload of every one of the nine kinds, key by key, on the clean fake lesson — p1…p5
 * answer frames said once (p4 without a window), p6 an ask frame said twice (x7, x8), x6 a rescue.
 */

/** Seeds depend on the scene: every seeded outcome below is asserted on this one. */
function s1pSceneId(): string
{
    return '01J8SESS10N1APHRASES000000';
}

/** @return array<string, mixed> the clean fake lesson as the model writes it */
function s1pPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8));
}

/** @param  (Closure(array<string, mixed>): array<string, mixed>)|null  $edit  a change to the model's answer before it is served */
function s1pScene(?Closure $edit = null, ?string $sceneId = null): SceneMaterial
{
    $id = PlanSceneId::fromString($sceneId ?? s1pSceneId());
    $packs = lessonPacks();
    $payload = s1pPayload();
    if ($edit !== null) {
        $payload = $edit($payload);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));

    return new SceneMaterial(
        $id, $lesson, PlanTerm::fromLesson($id, $lesson, static fn (): PlanTermId => PlanTermId::generate()),
        $packs->for('en'), $packs->for('ru'),
    );
}

function s1pTerm(SceneMaterial $scene, string $ref): PlanTerm
{
    $term = $scene->term($ref);
    expect($term)->not->toBeNull();

    return $term;
}

/**
 * The kinds dealt at the given places of the stage, by the frame they are about.
 *
 * @param  list<CardDraft>  $drafts
 * @param  list<int>  $places
 * @return array<string, string>
 */
function s1pKindsAt(array $drafts, array $places): array
{
    $out = [];
    foreach ($places as $place) {
        $out[$drafts[$place]->unitRef] = $drafts[$place]->kind->value;
    }

    return $out;
}

/**
 * @param  list<array<string, mixed>>  $options
 * @return array<string, mixed> the text of every option, by id
 */
function s1pTexts(array $options): array
{
    return array_column($options, 'text', 'id');
}

/**
 * Where the intro, the recognition and the production of p1…p6 stand in a stage of six frames (A_i, B_i−1, C_i−2).
 *
 * @return list<int>
 */
function s1pPlaces(string $which): array
{
    return ['intro' => [0, 1, 3, 6, 9, 12], 'recognise' => [2, 4, 7, 10, 13, 15], 'produce' => [5, 8, 11, 14, 16, 17]][$which];
}

it('deals three cards per frame spaced A_i, B_i−1, C_i−2, then one phrase_combine last', function () {
    $scene = s1pScene();

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = (new PhrasesStage)->build($scene, $level);

        expect($drafts)->toHaveCount(19)
            ->and(array_slice(array_map(static fn (CardDraft $d): string => $d->unitRef, $drafts), 0, 18))
            ->toBe(['p1', 'p2', 'p1', 'p3', 'p2', 'p1', 'p4', 'p3', 'p2', 'p5', 'p4', 'p3', 'p6', 'p5', 'p4', 'p6', 'p5', 'p6'])
            ->and(array_values(array_unique(s1pKindsAt($drafts, s1pPlaces('intro')))))->toBe(['phrase_intro'])
            ->and(array_keys(s1pKindsAt($drafts, s1pPlaces('recognise'))))->toBe(['p1', 'p2', 'p3', 'p4', 'p5', 'p6'])
            ->and(array_diff(s1pKindsAt($drafts, s1pPlaces('recognise')), ['phrase_slot', 'phrase_slot_listen', 'phrase_choose_back', 'phrase_assemble']))->toBe([])
            ->and(array_keys(s1pKindsAt($drafts, s1pPlaces('produce'))))->toBe(['p1', 'p2', 'p3', 'p4', 'p5', 'p6'])
            ->and(array_diff(s1pKindsAt($drafts, s1pPlaces('produce')), ['phrase_repeat', 'phrase_other_slot', 'phrase_own_slot']))->toBe([])
            ->and($drafts[18]->kind)->toBe(CardKind::PhraseCombine)
            ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toHaveCount(1);

        foreach ($drafts as $draft) {
            expect($draft->kind->stage())->toBe(Stage::Phrases)
                ->and($draft->unitKind)->toBe(UnitKind::Phrase)
                ->and(array_key_first($draft->payload))->toBe('scene_id')
                ->and($draft->payload['scene_id'])->toBe(s1pSceneId());
        }
    }
});

it('rotates recognition from a seeded place over the frames with a window, and deals the same day twice', function () {
    $scene = s1pScene();
    $drafts = (new PhrasesStage)->build($scene, PlanLevel::Beginner);
    $cycle = [CardKind::PhraseSlot, CardKind::PhraseSlotListen, CardKind::PhraseChooseBack, CardKind::PhraseAssemble];
    $pick = static fn (int $j): string => Rotation::pick($scene->seed('phrases:recognize'), $j, $cycle)->value;

    // p4 has no window: it takes no place in the cycle, so p5 and p6 go on from where p3 stopped.
    expect(s1pKindsAt($drafts, s1pPlaces('recognise')))->toBe([
        'p1' => $pick(0), 'p2' => $pick(1), 'p3' => $pick(2), 'p4' => 'phrase_choose_back', 'p5' => $pick(3), 'p6' => $pick(4),
    ])
        ->and(array_unique(s1pKindsAt($drafts, s1pPlaces('recognise'))))->toHaveCount(4)
        // A day dealt again from the same material — even with fresh term ids — is the same day.
        ->and((new PhrasesStage)->build(s1pScene(), PlanLevel::Beginner))->toEqual($drafts)
        ->and((new PhrasesStage)->build(s1pScene(), PlanLevel::Intermediate))->toEqual((new PhrasesStage)->build($scene, PlanLevel::Intermediate));

    // Seeded by the scene: over a dozen scenes the first frame is not always recognised the same way.
    $first = [];
    foreach (range(10, 21) as $n) {
        $first[(new PhrasesStage)->build(s1pScene(null, '01J8SESS10N1APHRASES0000'.$n), PlanLevel::Beginner)[2]->kind->value] = true;
    }
    expect(count($first))->toBeGreaterThan(1);
});

it('recognises a frame without a window back and has it repeated, at both levels', function () {
    $scene = s1pScene();
    $p4 = s1pTerm($scene, 'p4');
    $cards = new PhraseCards;

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        $drafts = (new PhrasesStage)->build($scene, $level);
        expect(s1pKindsAt($drafts, s1pPlaces('recognise'))['p4'])->toBe('phrase_choose_back')
            ->and(s1pKindsAt($drafts, s1pPlaces('produce'))['p4'])->toBe('phrase_repeat');
    }

    $intro = $cards->intro($scene, $p4)->payload;
    $back = $cards->chooseBack($scene, $p4)->payload;
    $others = array_map(static fn (PlanTerm $t): string => $t->textNative(), array_filter($scene->phrases(), static fn (PlanTerm $t): bool => $t->ref() !== 'p4'));

    expect($intro['frame']['slot'])->toBeNull()
        ->and($intro['said'])->toBe([
            'filler_index' => null, 'text_target' => "He doesn't have a fever.", 'text_native' => 'Температуры у него нет.',
            'pronunciation_native' => 'хи дазнт хэв э фивер', 'audio' => Audio::of('p4'),
        ])
        ->and($back['prompt']['filler_index'])->toBeNull()
        ->and(s1pTexts($back['options'])[$back['correct']])->toBe('Температуры у него нет.')
        ->and($back['options'])->toHaveCount(4)
        // No fillers of its own: the three wrong ones are what the other frames say.
        ->and(array_diff(array_values(array_diff(s1pTexts($back['options']), ['Температуры у него нет.'])), $others))->toBe([])
        ->and($cards->assemble($scene, $p4))->toBeNull()
        ->and($cards->slot($scene, $p4))->toBeNull()
        ->and($cards->slotListen($scene, $p4))->toBeNull()
        ->and($cards->otherSlot($scene, $p4))->toBeNull()
        ->and($cards->ownSlot($scene, $p4))->toBeNull()
        ->and((new PhrasesStage)->returned($scene, $p4))->toEqual($cards->chooseBack($scene, $p4));
});

it('deals no phrase_choose_back with nothing to choose between: a lone frame without a window is met and repeated, and goes unrecognised', function () {
    $full = s1pScene();
    $p4 = s1pTerm($full, 'p4');
    $lone = new SceneMaterial($full->sceneId, $full->lesson, [$p4], $full->target, $full->native);

    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        expect(array_map(static fn (CardDraft $d): string => $d->kind->value.':'.$d->unitRef, (new PhrasesStage)->build($lone, $level)))
            ->toBe(['phrase_intro:p4', 'phrase_repeat:p4'], $level->value);
    }

    // No filler of its own and no other frame to say something else: one option is no choice.
    expect((new PhraseCards)->chooseBack($lone, $p4))->toBeNull()
        ->and((new PhrasesStage)->returned($lone, $p4))->toBeNull()
        // The same frame among the day's others is recognised back as before.
        ->and((new PhraseCards)->chooseBack($full, $p4)?->kind)->toBe(CardKind::PhraseChooseBack);
});

it('has a beginner repeat every frame, an intermediate learner vary the frames of two fillers and repeat a frame of one', function () {
    $scene = s1pScene();
    $cycle = [CardKind::PhraseOtherSlot, CardKind::PhraseOwnSlot];
    $pick = static fn (int $j): string => Rotation::pick($scene->seed('phrases:produce'), $j, $cycle)->value;

    expect(array_values(array_unique(s1pKindsAt((new PhrasesStage)->build($scene, PlanLevel::Beginner), s1pPlaces('produce')))))->toBe(['phrase_repeat']);

    $intermediate = s1pKindsAt((new PhrasesStage)->build($scene, PlanLevel::Intermediate), s1pPlaces('produce'));
    expect($intermediate)->toBe([
        'p1' => $pick(0), 'p2' => $pick(1), 'p3' => $pick(2), 'p4' => 'phrase_repeat', 'p5' => $pick(3), 'p6' => $pick(4),
    ])
        ->and(array_values(array_unique(array_diff($intermediate, ['phrase_repeat']))))->toEqualCanonicalizing(['phrase_other_slot', 'phrase_own_slot']);

    // p5 with a single filler: repeated, and p6 takes the place p5 left in the cycle.
    $oneFiller = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['slot']['fillers'] = [$payload['phrases'][4]['slot']['fillers'][0]];

        return $payload;
    });
    expect(s1pKindsAt((new PhrasesStage)->build($oneFiller, PlanLevel::Intermediate), s1pPlaces('produce')))->toBe([
        'p1' => $pick(0), 'p2' => $pick(1), 'p3' => $pick(2), 'p4' => 'phrase_repeat', 'p5' => 'phrase_repeat', 'p6' => $pick(3),
    ])
        ->and((new PhraseCards)->otherSlot($oneFiller, s1pTerm($oneFiller, 'p5')))->toBeNull();
});

it('builds phrase_combine on an answer exchange whose frame is said once, among three frames with a window', function () {
    $scene = s1pScene();
    $drafts = (new PhrasesStage)->build($scene, PlanLevel::Intermediate);
    $card = $drafts[18];
    $payload = $card->payload;
    $step = $payload['exchange']['step'];
    $exchange = $scene->exchange($step);
    $correct = s1pTerm($scene, $payload['correct_frame']);
    $eligible = array_values(array_filter($scene->lesson->exchanges, static fn (Exchange $e): bool => in_array($e->step, [1, 2, 3, 5], true)));

    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'])
        ->and($card->unitRef)->toBe($payload['correct_frame'])
        ->and($exchange?->kind)->toBe(ExchangeKind::Answer)
        ->and($step)->toBe(Rotation::pick($scene->seed('phrases:combine'), 0, $eligible)->step)
        ->and($payload['exchange'])->toBe(['ref' => 'x'.$step, 'step' => $step, 'kind' => 'answer'])
        ->and($exchange?->learner()?->phraseId)->toBe($payload['correct_frame'])
        ->and($scene->lesson->linesOf($payload['correct_frame']))->toHaveCount(1)
        ->and($payload['partner_line'])->toBe([
            'ref' => 'x'.$step, 'text_target' => $exchange?->partner()?->textTarget, 'text_native' => $exchange?->partner()?->textNative, 'audio' => Audio::of('x'.$step),
        ])
        ->and($payload['frames'])->toHaveCount(3)
        ->and(array_unique(array_column($payload['frames'], 'ref')))->toHaveCount(3)
        ->and(array_column($payload['frames'], 'ref'))->toContain($payload['correct_frame'])
        ->and($payload['chips'])->toBe(CardObjects::fillers($correct))
        ->and($payload['correct_filler'])->toBe(0);

    foreach ($payload['frames'] as $frame) {
        $term = s1pTerm($scene, $frame['ref']);
        expect(array_keys($frame))->toBe(['ref', 'frame_target', 'frame_native'])
            ->and($frame['frame_target'])->toBe($term->frame()?->frameTarget)
            ->and($frame['frame_native'])->toBe($term->frame()?->frameNative)
            ->and(PhraseCards::hasSlot($term))->toBeTrue()
            // Three answer frames besides the right one: the ask frame p6 is not needed.
            ->and($term->frame()?->kind)->toBe(ExchangeKind::Answer);
    }

    // Seeded by the scene; never x4 (no window), x6 (a rescue), x7/x8 (an ask frame said twice).
    $steps = [];
    foreach (range(10, 21) as $n) {
        $combine = (new PhraseCards)->combine(s1pScene(null, '01J8SESS10N1APHRASES0000'.$n));
        $steps[$combine?->payload['exchange']['step']] = true;
        expect($combine?->unitRef)->not->toBe('p6');
    }
    expect(array_diff(array_keys($steps), [1, 2, 3, 5]))->toBe([])
        ->and(count($steps))->toBeGreaterThan(1);
});

it('falls back to exchange 1 when no answer frame is said once, and deals no combine without another frame with a window', function () {
    // Every answer line now stands on p1 — said four times; x1 says it with its second filler.
    $saidOften = s1pScene(static function (array $payload): array {
        foreach ([0 => 'It hurts in his neck.', 1 => 'It hurts in his shoulder.', 2 => 'It hurts in his lower back.', 4 => 'It hurts in his neck.'] as $i => $text) {
            $payload['dialogue'][$i]['messages'][1]['phrase_id'] = 'p1';
            $payload['dialogue'][$i]['messages'][1]['text_target'] = $text;
        }

        return $payload;
    });
    $combine = (new PhraseCards)->combine($saidOften);

    expect($combine?->payload['exchange'])->toBe(['ref' => 'x1', 'step' => 1, 'kind' => 'answer'])
        ->and($combine?->payload['correct_frame'])->toBe('p1')
        ->and($combine?->payload['correct_filler'])->toBe(1)
        ->and($combine?->payload['chips'])->toBe(CardObjects::fillers(s1pTerm($saidOften, 'p1')));

    $alone = s1pScene(static function (array $payload): array {
        foreach ([1, 2, 4, 5] as $i) {
            $payload['phrases'][$i]['slot'] = null;
        }

        return $payload;
    });
    $drafts = (new PhrasesStage)->build($alone, PlanLevel::Beginner);

    expect((new PhraseCards)->combine($alone))->toBeNull()
        ->and($drafts)->toHaveCount(18)
        ->and(array_filter($drafts, static fn (CardDraft $d): bool => $d->kind === CardKind::PhraseCombine))->toBe([]);
});

it('lays out the frame, every filler with the file it sounds as, and the said phrase on phrase_intro', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;

    expect($cards->intro($scene, s1pTerm($scene, 'p1'))->payload)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => [
            'ref' => 'p1', 'kind' => 'answer', 'frame_target' => 'It hurts in his ___.', 'frame_native' => 'У него болит ___.',
            'frame_pronunciation_native' => 'ит хёртс ин хиз ___',
            'slot' => ['hint_native' => 'где болит', 'fillers' => [
                ['index' => 0, 'target' => 'lower back', 'native' => 'поясница', 'pronunciation_native' => 'лоуэр бэк', 'in_dialogue' => true, 'native_line' => 'У него болит поясница.', 'audio' => Audio::of('p1')],
                ['index' => 1, 'target' => 'neck', 'native' => 'шея', 'pronunciation_native' => 'нэк', 'in_dialogue' => false, 'native_line' => 'У него болит шея.', 'audio' => Audio::of('p1.f2')],
                ['index' => 2, 'target' => 'shoulder', 'native' => 'плечо', 'pronunciation_native' => 'шоулдер', 'in_dialogue' => false, 'native_line' => 'У него болит плечо.', 'audio' => Audio::of('p1.f3')],
            ]],
        ],
        'said' => [
            'filler_index' => 0, 'text_target' => 'It hurts in his lower back.', 'text_native' => 'У него болит поясница.',
            'pronunciation_native' => 'ит хёртс ин хиз лоуэр бэк', 'audio' => Audio::of('p1'),
        ],
    ]);

    // p6 is said twice: both said fillers are marked, only the first is the phrase's own file.
    $p6 = $cards->intro($scene, s1pTerm($scene, 'p6'))->payload;
    $fillers = $p6['frame']['slot']['fillers'];
    expect(array_column(array_column($fillers, 'audio'), 'ref'))->toBe(['p6', 'p6.f2', 'p6.f3'])
        ->and(array_column($fillers, 'in_dialogue'))->toBe([true, true, false])
        ->and(array_column($fillers, 'native_line'))->toBe(['Нам нужно сделать рентген?', 'Нам нужно прийти на повторный приём?', 'Нам нужно взять справку для школы?'])
        ->and($p6['said']['text_target'])->toBe('Do we need an X-ray?')
        ->and($p6['frame']['kind'])->toBe('ask')
        ->and(CardObjects::fillers(s1pTerm($scene, 'p3'))[1]['native_line'])->toBe('Боль ноющая, когда он наклоняется.');
});

it('asks phrase_assemble for the frame\'s words and two words of the frames after it, lower-cased but «I»', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $p3 = $cards->assemble($scene, s1pTerm($scene, 'p3'))?->payload;

    expect(array_keys($p3))->toBe(['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'])
        ->and($p3['frame'])->toBe(CardObjects::frame(s1pTerm($scene, 'p3')))
        ->and($p3['target_native'])->toBe('Боль острая, когда он наклоняется.')
        // The next frame p4 gives two words p3 does not have («he» it has).
        ->and($p3['tiles'])->toBe(Shuffle::seeded($scene->seed('p3:assemble'), ['the', 'pain', 'is', 'when', 'he', 'bends', "doesn't", 'have']))
        ->and($p3['chips'])->toBe(CardObjects::fillers(s1pTerm($scene, 'p3')))
        // The answer is spelled the way the tiles are: the client matches what it assembled tile by tile.
        ->and($p3['expected'])->toBe(['words' => ['the', 'pain', 'is', 'when', 'he', 'bends'], 'slot_at' => 3, 'filler_index' => 0]);

    // p2 «It started ___.» has one word p1 lacks; the second comes from the frame after it.
    expect($cards->assemble($scene, s1pTerm($scene, 'p1'))?->payload['tiles'])
        ->toBe(Shuffle::seeded($scene->seed('p1:assemble'), ['it', 'hurts', 'in', 'his', 'started', 'the']));

    $mine = s1pScene(static function (array $payload): array {
        $payload['phrases'][4]['frame_target'] = 'I will rest ___.';
        $payload['dialogue'][4]['messages'][1]['text_target'] = 'Okay, I will rest at home.';

        return $payload;
    });
    $p5 = $cards->assemble($mine, s1pTerm($mine, 'p5'))?->payload;
    expect($p5['tiles'])->toEqualCanonicalizing(['I', 'will', 'rest', 'do', 'we'])
        ->and($p5['expected'])->toBe(['words' => ['I', 'will', 'rest'], 'slot_at' => 3, 'filler_index' => 0]);
});

it('translates the said phrase back among the frame\'s other fillers and another frame on phrase_choose_back', function () {
    $scene = s1pScene();
    $p1 = (new PhraseCards)->chooseBack($scene, s1pTerm($scene, 'p1'))->payload;
    $others = Shuffle::seeded($scene->seed('p1:choose_back:others'), array_values(array_filter($scene->phrases(), static fn (PlanTerm $t): bool => $t->ref() !== 'p1')));
    $texts = s1pTexts($p1['options']);

    expect(array_keys($p1))->toBe(['scene_id', 'prompt', 'options', 'correct'])
        ->and($p1['prompt'])->toBe([
            'text_target' => 'It hurts in his lower back.', 'pronunciation_native' => 'ит хёртс ин хиз лоуэр бэк', 'filler_index' => 0, 'audio' => Audio::of('p1'),
        ])
        ->and(array_keys($texts))->toBe(['o1', 'o2', 'o3', 'o4'])
        ->and(array_map(static fn (array $o): array => array_keys($o), $p1['options']))->each->toBe(['id', 'text'])
        ->and($texts[$p1['correct']])->toBe('У него болит поясница.')
        ->and(array_values($texts))->toEqualCanonicalizing(['У него болит поясница.', 'У него болит шея.', 'У него болит плечо.', $others[0]->textNative()]);

    // A frame of two fillers is topped up with what two other frames say.
    $twoFillers = s1pScene(static function (array $payload): array {
        array_pop($payload['phrases'][0]['slot']['fillers']);

        return $payload;
    });
    $short = (new PhraseCards)->chooseBack($twoFillers, s1pTerm($twoFillers, 'p1'))->payload;
    $shortOthers = Shuffle::seeded($twoFillers->seed('p1:choose_back:others'), array_values(array_filter($twoFillers->phrases(), static fn (PlanTerm $t): bool => $t->ref() !== 'p1')));
    expect(array_values(s1pTexts($short['options'])))
        ->toEqualCanonicalizing(['У него болит поясница.', 'У него болит шея.', $shortOthers[0]->textNative(), $shortOthers[1]->textNative()]);
});

it('plays one voiced filler of the frame on phrase_slot_listen and offers silent fillers, another frame\'s among them', function () {
    $scene = s1pScene();
    $p1 = (new PhraseCards)->slotListen($scene, s1pTerm($scene, 'p1'))?->payload;
    $heard = Rotation::pick($scene->seed('p1:slot_listen'), 0, [0, 1, 2]);

    expect(array_keys($p1))->toBe(['scene_id', 'frame', 'filler_index', 'audio', 'options', 'correct'])
        ->and($p1['frame'])->toBe(CardObjects::frame(s1pTerm($scene, 'p1')))
        ->and($p1['filler_index'])->toBe($heard)
        ->and($p1['audio'])->toBe(Audio::of(['p1', 'p1.f2', 'p1.f3'][$heard]))
        ->and(array_map(static fn (array $o): array => array_keys($o), $p1['options']))->each->toBe(['id', 'text'])
        ->and(array_values(s1pTexts($p1['options'])))->toEqualCanonicalizing(['lower back', 'neck', 'shoulder', 'three days ago'])
        ->and(s1pTexts($p1['options'])[$p1['correct']])->toBe(['lower back', 'neck', 'shoulder'][$heard]);

    // Over a dozen scenes the card does not always play the said filler.
    $played = [];
    foreach (range(10, 21) as $n) {
        $other = s1pScene(null, '01J8SESS10N1APHRASES0000'.$n);
        $played[(new PhraseCards)->slotListen($other, s1pTerm($other, 'p1'))?->payload['filler_index']] = true;
    }
    expect(count($played))->toBeGreaterThan(1);
});

it('puts the said filler among the frame\'s others and the next frame\'s on phrase_slot, each with its sound, and returns a frame as it', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $sounds = static fn (array $payload): array => array_combine(
        array_column($payload['options'], 'text'),
        array_map(static fn (array $o): ?string => $o['audio']['ref'] ?? null, $payload['options']),
    );

    $p1 = $cards->slot($scene, s1pTerm($scene, 'p1'))?->payload;
    expect(array_keys($p1))->toBe(['scene_id', 'frame', 'prompt_native', 'options', 'correct'])
        ->and($p1['frame'])->toBe(CardObjects::frame(s1pTerm($scene, 'p1')))
        ->and($p1['prompt_native'])->toBe('У него болит поясница.')
        ->and(array_map(static fn (array $o): array => array_keys($o), $p1['options']))->each->toBe(['id', 'text', 'audio'])
        ->and($sounds($p1))->toEqualCanonicalizing(['lower back' => 'p1', 'neck' => 'p1.f2', 'shoulder' => 'p1.f3', 'three days ago' => 'p2'])
        ->and(s1pTexts($p1['options'])[$p1['correct']])->toBe('lower back');

    // The next frame first: p3 skips p4 (no window) to p5, not back to p1; the last frame looks round to the first.
    expect($sounds($cards->slot($scene, s1pTerm($scene, 'p3'))?->payload))
        ->toEqualCanonicalizing(['sharp' => 'p3', 'dull' => 'p3.f2', 'constant' => 'p3.f3', 'at home' => 'p5']);
    $p6 = $cards->slot($scene, s1pTerm($scene, 'p6'))?->payload;
    expect($sounds($p6))->toEqualCanonicalizing(['an X-ray' => 'p6', 'a follow-up appointment' => 'p6.f2', 'a sick note' => 'p6.f3', 'lower back' => 'p1'])
        ->and(s1pTexts($p6['options'])[$p6['correct']])->toBe('an X-ray');

    $returned = (new PhrasesStage)->returned($scene, s1pTerm($scene, 'p1'));
    expect($returned->kind)->toBe(CardKind::PhraseSlot)
        ->and($returned->unitKind)->toBe(UnitKind::Phrase)
        ->and($returned->unitRef)->toBe('p1')
        ->and($returned->payload)->toBe($p1);
});

it('has the said phrase repeated with its server key and coverage on phrase_repeat', function () {
    $scene = s1pScene();
    $term = s1pTerm($scene, 'p1');

    expect($term->speakingKey())->not->toBeNull()
        ->and((new PhraseCards)->repeat($scene, $term)->payload)->toBe([
            'scene_id' => s1pSceneId(),
            'frame' => CardObjects::frame($term),
            'filler_index' => 0,
            'expected_text' => 'It hurts in his lower back.',
            'key' => $term->speakingKey(),
            'coverage_min' => 0.7,
            'audio' => Audio::of('p1'),
        ]);
});

it('asks for a filler other than the said one on phrase_other_slot', function () {
    $scene = s1pScene();
    $p1 = (new PhraseCards)->otherSlot($scene, s1pTerm($scene, 'p1'))?->payload;
    $other = Rotation::pick($scene->seed('p1:other'), 0, [1, 2]);
    [$target, $native] = [['neck', 'шея'], ['shoulder', 'плечо']][$other - 1];

    expect($p1)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => CardObjects::frame(s1pTerm($scene, 'p1')),
        'filler_index' => $other,
        'task_native' => $native,
        'expected_text' => "It hurts in his {$target}.",
        'slot_expected' => $target,
        'key' => s1pTerm($scene, 'p1')->speakingKey(),
        'coverage_min' => 0.7,
    ]);

    // When the dialogue says p1 with «neck», the other window is «lower back» or «shoulder» — never «neck».
    $saysNeck = s1pScene(static function (array $payload): array {
        $payload['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his neck.';

        return $payload;
    });
    $neck = (new PhraseCards)->otherSlot($saysNeck, s1pTerm($saysNeck, 'p1'))?->payload;
    $index = Rotation::pick($saysNeck->seed('p1:other'), 0, [0, 2]);
    expect($saysNeck->saidIndex(s1pTerm($saysNeck, 'p1')))->toBe(1)
        ->and($neck['filler_index'])->toBe($index)
        ->and($neck['slot_expected'])->toBe(['lower back', 'neck', 'shoulder'][$index])
        ->and($neck['expected_text'])->toBe('It hurts in his '.['lower back', 'neck', 'shoulder'][$index].'.');
});

it('has the learner\'s own value judged on phrase_own_slot, with the partner line the frame is said to', function () {
    $scene = s1pScene();
    $cards = new PhraseCards;
    $p1 = $cards->ownSlot($scene, s1pTerm($scene, 'p1'))?->payload;

    expect($p1)->toBe([
        'scene_id' => s1pSceneId(),
        'frame' => CardObjects::frame(s1pTerm($scene, 'p1')),
        'partner_line' => [
            'ref' => 'x1', 'text_target' => 'Where does it hurt: his upper back or his lower back?',
            'text_native' => 'Где болит: вверху спины или в пояснице?', 'audio' => Audio::of('x1'),
        ],
        'task_native' => 'У него болит ___.',
        'key' => s1pTerm($scene, 'p1')->speakingKey(),
        'coverage_min' => 0.7,
        'examples' => ['поясница', 'шея', 'плечо'],
        'chips' => CardObjects::fillers(s1pTerm($scene, 'p1')),
        'judge' => true,
    ])
        // «It started» — two words: all of them.
        ->and($cards->ownSlot($scene, s1pTerm($scene, 'p2'))?->payload['coverage_min'])->toBe(1.0)
        // p6 is first asked in x7: the learner speaks first, the line before it is the partner's line of x6.
        ->and($cards->ownSlot($scene, s1pTerm($scene, 'p6'))?->payload['partner_line'])->toBe([
            'ref' => 'x6', 'text_target' => 'He should rest and use a heating pad.', 'text_native' => 'Ему нужен покой и грелка.', 'audio' => Audio::of('x6'),
        ]);
});

it('gives every one of the nine kinds its exact keys over the deals of both levels', function () {
    $scene = s1pScene();
    $keys = [
        'phrase_intro' => ['scene_id', 'frame', 'said'],
        'phrase_assemble' => ['scene_id', 'frame', 'target_native', 'tiles', 'chips', 'expected'],
        'phrase_choose_back' => ['scene_id', 'prompt', 'options', 'correct'],
        'phrase_slot' => ['scene_id', 'frame', 'prompt_native', 'options', 'correct'],
        'phrase_slot_listen' => ['scene_id', 'frame', 'filler_index', 'audio', 'options', 'correct'],
        'phrase_repeat' => ['scene_id', 'frame', 'filler_index', 'expected_text', 'key', 'coverage_min', 'audio'],
        'phrase_other_slot' => ['scene_id', 'frame', 'filler_index', 'task_native', 'expected_text', 'slot_expected', 'key', 'coverage_min'],
        'phrase_combine' => ['scene_id', 'exchange', 'partner_line', 'frames', 'correct_frame', 'chips', 'correct_filler'],
        'phrase_own_slot' => ['scene_id', 'frame', 'partner_line', 'task_native', 'key', 'coverage_min', 'examples', 'chips', 'judge'],
    ];
    $seen = [];
    foreach ([PlanLevel::Beginner, PlanLevel::Intermediate] as $level) {
        foreach ((new PhrasesStage)->build($scene, $level) as $draft) {
            $seen[$draft->kind->value] = true;
            expect(array_keys($draft->payload))->toBe($keys[$draft->kind->value]);
            if (isset($draft->payload['frame'])) {
                expect(array_keys($draft->payload['frame']))->toBe(['ref', 'kind', 'frame_target', 'frame_native', 'frame_pronunciation_native', 'slot']);
            }
            foreach ($draft->payload['options'] ?? [] as $option) {
                expect(array_slice(array_keys($option), 0, 2))->toBe(['id', 'text']);
            }
            if (isset($draft->payload['correct'])) {
                expect(array_column($draft->payload['options'], 'id'))->toContain($draft->payload['correct'])
                    ->and(array_unique(array_map('mb_strtolower', array_column($draft->payload['options'], 'text'))))->toHaveCount(count($draft->payload['options']));
            }
        }
    }

    expect(array_keys($seen))->toEqualCanonicalizing(array_keys($keys))
        ->and(array_keys(CardObjects::fillers(s1pTerm($scene, 'p2'))[0]))->toBe(['index', 'target', 'native', 'pronunciation_native', 'in_dialogue', 'native_line', 'audio']);
});
