<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\ListenStage;
use App\Modules\Plan\Domain\Assembly\NumberValues;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Lesson\ListeningExchange;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «СЛУШАЮ И ОТВЕЧАЮ» ON THE CLEAN LESSON (наряд SESSION-1a, разд. 1–2; SPEC §4 Listen): the order of the stage, the
 * unit of every card, the fourth option of a question, where the review finds an answer, predict only on ask
 * exchanges, the pace line, and the number card — present when the visit says a number and has two other values,
 * absent otherwise. The scene id is fixed: every shuffle is seeded by it.
 */

/**
 * The served clean lesson of the doctor's visit as the listening stage reads it; `$payload` edits the model's answer
 * before it is served, `$lesson` the served lesson, `$native` names the learner's pack (`none` — a language without one).
 */
function s1lScene(?Closure $payload = null, ?Closure $lesson = null, string $native = 'ru'): SceneMaterial
{
    $sceneId = PlanSceneId::fromString('01J8SESS10N1A4EAR000000000');
    $packs = lessonPacks();
    $answer = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8));
    if ($payload !== null) {
        $answer = $payload($answer);
    }
    $served = LessonAssembly::serve((new LessonParser)->parse($answer), $sceneId->value, $packs->for('en'));
    if ($lesson !== null) {
        $served = $lesson($served);
    }

    return new SceneMaterial(
        $sceneId, $served, PlanTerm::fromLesson($sceneId, $served, static fn (): PlanTermId => PlanTermId::generate()),
        $packs->for('en'), $native === 'none' ? LanguagePack::none('xx') : $packs->for($native),
    );
}

/** @return list<CardDraft> */
function s1lCards(SceneMaterial $scene, CardKind $kind): array
{
    return array_values(array_filter((new ListenStage)->build($scene), static fn (CardDraft $d): bool => $d->kind === $kind));
}

/** @return array<string, mixed> the payload of the one card of that kind */
function s1lOne(SceneMaterial $scene, CardKind $kind): array
{
    $cards = s1lCards($scene, $kind);
    expect($cards)->toHaveCount(1);

    return $cards[0]->payload;
}

/**
 * @param  array<string, mixed>  $payload
 * @return list<string>
 */
function s1lTexts(array $payload): array
{
    return array_column($payload['options'], 'text');
}

/** @param array<string, mixed> $payload */
function s1lRight(array $payload): string
{
    return array_column($payload['options'], 'text', 'id')[$payload['correct']];
}

/** @return list<string> a question's wrong options in the order they are served */
function s1lWrong(ListeningQuestion $question): array
{
    return array_values(array_filter(
        $question->optionsNative,
        static fn (int $i): bool => $i !== $question->correctOptionIndex,
        ARRAY_FILTER_USE_KEY,
    ));
}

/**
 * The served lesson with the wrong options of listening question `$index` replaced, in their served order.
 *
 * @param  list<string>  $wrong
 */
function s1lPlantWrong(Lesson $lesson, int $index, array $wrong): Lesson
{
    $question = $lesson->listening[$index];
    $options = $question->optionsNative;
    $k = 0;
    foreach (array_keys($options) as $i) {
        if ($i !== $question->correctOptionIndex) {
            $options[$i] = $wrong[$k++] ?? $options[$i];
        }
    }
    $listening = $lesson->listening;
    $listening[$index] = $question->withOptions($options, $question->correctOptionIndex);

    return $lesson->withListening($listening);
}

it('deals the dialogue, every question, the review, predict per ask, the pace and the number — in that order, the same twice', function () {
    $scene = s1lScene();
    $cards = (new ListenStage)->build($scene);

    expect(array_map(static fn (CardDraft $d): string => $d->kind->value, $cards))->toBe([
        'listen_dialogue', 'listen_question', 'listen_question', 'listen_question', 'listen_review',
        'listen_predict', 'listen_predict', 'listen_pace', 'listen_number',
    ])
        ->and((new ListenStage)->build($scene))->toEqual($cards)
        ->and(array_filter($cards, static fn (CardDraft $d): bool => $d->kind->stage() !== CardKind::ListenDialogue->stage()))->toBe([])
        ->and(array_filter($cards, static fn (CardDraft $d): bool => $d->source !== CardSource::Today))->toBe([]);

    foreach ($cards as $card) {
        expect($card->payload['scene_id'])->toBe('01J8SESS10N1A4EAR000000000')
            ->and(array_key_first($card->payload))->toBe('scene_id');
    }
});

// D-05: the listening is the day's — its unit never returns; a question is its own unit L{n}.
it('gives every card the day as its unit, and each question its own L{n}', function () {
    $units = array_map(
        static fn (CardDraft $d): string => $d->unitKind->value.'/'.$d->unitRef,
        (new ListenStage)->build(s1lScene()),
    );

    expect($units)->toBe(['day/day', 'day/L1', 'day/L2', 'day/L3', 'day/day', 'day/day', 'day/day', 'day/day', 'day/day'])
        ->and(UnitKind::Day->returns())->toBeFalse();
});

it('plays every line of the visit, both speakers in the order of the visit, each with its sound', function () {
    $scene = s1lScene();
    $payload = s1lOne($scene, CardKind::ListenDialogue);

    $expected = [];
    foreach ($scene->lesson->exchanges as $exchange) {
        foreach ($exchange->messages as $message) {
            $ref = 'x'.$exchange->step.($message->isLearner() ? 'b' : '');
            $expected[] = [
                'ref' => $ref,
                'role' => $message->isLearner() ? 'learner' : 'partner',
                'exchange_step' => $exchange->step,
                'text_target' => $message->textTarget,
                'text_native' => $message->textNative,
                'audio' => Audio::of($ref),
            ];
        }
    }

    expect(array_keys($payload))->toBe(['scene_id', 'lines', 'total_ms'])
        ->and($payload['total_ms'])->toBeNull()
        ->and($payload['lines'])->toHaveCount(16)
        ->and($payload['lines'])->toBe($expected)
        // The rescue and the asks open with the learner: x6b before x6.
        ->and(array_column($payload['lines'], 'ref'))->toBe([
            'x1', 'x1b', 'x2', 'x2b', 'x3', 'x3b', 'x4', 'x4b', 'x5', 'x5b', 'x6b', 'x6', 'x7b', 'x7', 'x8b', 'x8',
        ])
        ->and($payload['lines'][1]['audio'])->toBe(['ref' => 'x1b', 'voice' => 'learner', 'url' => null, 'duration_ms' => null])
        ->and($payload['lines'][0]['audio'])->toBe(['ref' => 'x1', 'voice' => 'partner', 'url' => null, 'duration_ms' => null]);
});

// Canon: 4 options — the question's three and one WRONG option of another question, never equal to the three.
it('asks every question with its three options and a wrong option of the next question', function () {
    $scene = s1lScene();
    $questions = s1lCards($scene, CardKind::ListenQuestion);
    $listening = $scene->lesson->listening;

    expect($questions)->toHaveCount(3);
    foreach ($questions as $i => $card) {
        $payload = $card->payload;
        $question = $listening[$i];
        $next = $listening[($i + 1) % 3];
        $fourth = array_values(array_diff(s1lTexts($payload), $question->optionsNative));

        expect(array_keys($payload))->toBe(['scene_id', 'question', 'options', 'correct', 'exchange_step'])
            ->and($payload['question'])->toBe(['ref' => 'L'.($i + 1), 'text_native' => $question->textNative])
            ->and(array_column($payload['options'], 'id'))->toBe(['o1', 'o2', 'o3', 'o4'])
            ->and(array_map(static fn (array $o): array => array_keys($o), $payload['options']))->each->toBe(['id', 'text'])
            ->and(s1lRight($payload))->toBe($question->correctOption())
            ->and(array_values(array_intersect(s1lTexts($payload), $question->optionsNative)))->toHaveCount(3)
            ->and($fourth)->toBe([s1lWrong($next)[0]])
            ->and($payload['exchange_step'])->toBe(ListeningExchange::inPack($scene->lesson, $question, $scene->native));
    }

    expect(array_column(array_column($questions, 'payload'), 'exchange_step'))->toBe([1, 5, 8]);
});

it('skips a wrong option of another question that equals one of the three, case and spaces aside', function () {
    // The next question's first wrong option says «шея» — the first question already offers «Шея».
    $one = s1lScene(lesson: static fn (Lesson $l): Lesson => s1lPlantWrong($l, 1, [' шея ']));
    $original = s1lScene()->lesson->listening;
    $payload = s1lCards($one, CardKind::ListenQuestion)[0]->payload;

    expect($original[0]->optionsNative)->toContain('Шея')
        ->and(array_values(array_diff(s1lTexts($payload), $original[0]->optionsNative)))->toBe([s1lWrong($original[1])[1]]);

    // Both wrong options of the next question collide — one with a wrong option, one with the right answer: the fourth
    // comes from the question after it.
    $both = s1lScene(lesson: static fn (Lesson $l): Lesson => s1lPlantWrong($l, 1, ['шея', 'ПОЯСНИЦА']));
    $payload = s1lCards($both, CardKind::ListenQuestion)[0]->payload;
    $lower = array_map(mb_strtolower(...), s1lTexts($payload));

    expect(array_values(array_diff(s1lTexts($payload), $original[0]->optionsNative)))->toBe([s1lWrong($original[2])[0]])
        ->and(array_unique($lower))->toHaveCount(4)
        ->and(s1lRight($payload))->toBe('Поясница');
});

// D-20: an answer that is the value the learner said is marked inside the learner's line.
it('shows in the review where every answer was heard — the learner\'s value inside the learner\'s line', function () {
    $scene = s1lScene();
    $payload = s1lOne($scene, CardKind::ListenReview);
    $learnerLine = $scene->exchange(1)?->learner()?->textTarget;

    expect(array_keys($payload))->toBe(['scene_id', 'lines', 'total_ms', 'answers'])
        ->and($payload['lines'])->toBe(s1lOne($scene, CardKind::ListenDialogue)['lines'])
        ->and($payload['total_ms'])->toBeNull()
        ->and($payload['answers'])->toBe([
            // «Что болит у ребёнка?» — «Поясница», the filler the parent said: «lower back» in x1b.
            ['question_ref' => 'L1', 'exchange_step' => 1, 'line_ref' => 'x1b', 'span' => [16, 26]],
            ['question_ref' => 'L2', 'exchange_step' => 5, 'line_ref' => 'x5', 'span' => null],
            ['question_ref' => 'L3', 'exchange_step' => 8, 'line_ref' => 'x8', 'span' => null],
        ])
        ->and(mb_substr((string) $learnerLine, 16, 10))->toBe('lower back');

    // The right answer is no longer the value said: the answer is the partner's line of that exchange, nothing marked.
    $renamed = s1lScene(lesson: static function (Lesson $l): Lesson {
        $q = $l->listening[0];
        $options = $q->optionsNative;
        $options[$q->correctOptionIndex] = 'Болит поясница';

        return $l->withListening([$q->withOptions($options, $q->correctOptionIndex), $l->listening[1], $l->listening[2]]);
    });

    expect(s1lOne($renamed, CardKind::ListenReview)['answers'][0])
        ->toBe(['question_ref' => 'L1', 'exchange_step' => 1, 'line_ref' => 'x1', 'span' => null]);
});

it('reads no exchange for a question in a learner language without a pack, and deals no number there', function () {
    $scene = s1lScene(native: 'none');
    $cards = (new ListenStage)->build($scene);

    expect(array_map(static fn (CardDraft $d): string => $d->kind->value, $cards))->not->toContain('listen_number')
        ->and(array_column(array_column(s1lCards($scene, CardKind::ListenQuestion), 'payload'), 'exchange_step'))->toBe([null, null, null])
        ->and(s1lOne($scene, CardKind::ListenReview)['answers'][0])->toBe(['question_ref' => 'L1', 'exchange_step' => null, 'line_ref' => null, 'span' => null]);
});

// D-19: a guess at the partner's answer on every ask exchange, from that exchange's own check.
it('predicts the partner\'s answer on ask exchanges only, with the exchange\'s own check', function () {
    $scene = s1lScene();
    $predicts = s1lCards($scene, CardKind::ListenPredict);

    expect(array_map(static fn (CardDraft $d): array => $d->payload['exchange'], $predicts))->toBe([
        ['ref' => 'x7', 'step' => 7, 'kind' => 'ask'],
        ['ref' => 'x8', 'step' => 8, 'kind' => 'ask'],
    ]);
    foreach ($predicts as $card) {
        $exchange = $scene->exchange($card->payload['exchange']['step']);
        $check = $exchange->check;

        expect(array_keys($card->payload))->toBe(['scene_id', 'exchange', 'own_line', 'options', 'correct', 'partner_line'])
            ->and($card->payload['own_line'])->toBe([
                'ref' => 'x'.$exchange->step.'b', 'text_target' => $exchange->learner()->textTarget,
                'text_native' => $exchange->learner()->textNative, 'audio' => Audio::of('x'.$exchange->step.'b'),
            ])
            ->and($card->payload['partner_line'])->toBe([
                'ref' => 'x'.$exchange->step, 'text_target' => $exchange->partner()->textTarget,
                'text_native' => $exchange->partner()->textNative, 'audio' => Audio::of('x'.$exchange->step),
            ])
            ->and($card->payload['options'])->toHaveCount(3)
            ->and(s1lTexts($card->payload))->toEqualCanonicalizing(array_map(static fn ($o): string => $o->textNative, $check->options))
            ->and(s1lRight($card->payload))->toBe($check->correctOption()->textNative);
    }

    // Exchange 7 turned into an answer: one predict is left, on exchange 8.
    $answered = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][6]['kind'] = 'answer';

        return $answer;
    });

    expect(array_map(static fn (CardDraft $d): string => $d->payload['exchange']['ref'], s1lCards($answered, CardKind::ListenPredict)))->toBe(['x8']);
});

// Canon: the partner's longest line of at most ten words; between equals the lower step.
it('plays the longest partner line of at most ten words at two tempos', function () {
    $scene = s1lScene();
    $payload = s1lOne($scene, CardKind::ListenPace);
    $partner = $scene->exchange(3)->partner();

    $fitting = array_values(array_filter(
        $scene->partnerLines(),
        static fn (array $l): bool => Words::count($l['message']->textTarget) <= 10,
    ));
    $longest = max(array_map(static fn (array $l): int => Words::count($l['message']->textTarget), $fitting));

    expect(array_keys($payload))->toBe(['scene_id', 'exchange', 'partner_line', 'rates'])
        ->and($payload['exchange'])->toBe(['ref' => 'x3', 'step' => 3, 'kind' => 'answer'])
        ->and($payload['partner_line'])->toBe(['ref' => 'x3', 'text_target' => $partner->textTarget, 'text_native' => $partner->textNative, 'audio' => Audio::of('x3')])
        ->and($payload['rates'])->toBe([0.75, 1.0])
        ->and(Words::count($partner->textTarget))->toBe($longest)
        ->and($longest)->toBe(10);

    // Exchange 3's line grows to eleven words: exchange 7's ten words are the longest that fit.
    $longer = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][2]['messages'][0]['text_target'] = 'Is the pain very sharp, or more of a dull ache?';

        return $answer;
    });

    expect(s1lOne($longer, CardKind::ListenPace)['exchange']['ref'])->toBe('x7');
});

// D-21: the longest line with a number; the value marked in it; the value read in the learner's language among two others.
it('asks the number of the visit — the line, its place in the text, the value in the learner\'s language among two others', function () {
    $scene = s1lScene();
    $payload = s1lOne($scene, CardKind::ListenNumber);
    $partner = $scene->exchange(8)->partner();
    $texts = s1lTexts($payload);

    expect(array_keys($payload))->toBe(['scene_id', 'line', 'span', 'options', 'correct'])
        // «It started three days ago.» says a number too, but «Only if it still hurts after one week.» is longer.
        ->and($payload['line'])->toBe([
            'ref' => 'x8', 'role' => 'partner', 'exchange_step' => 8,
            'text_target' => $partner->textTarget, 'text_native' => $partner->textNative, 'audio' => Audio::of('x8'),
        ])
        ->and($payload['span'])->toBe([29, 32])
        ->and(mb_substr($partner->textTarget, 29, 3))->toBe('one')
        ->and($payload['options'])->toHaveCount(3)
        ->and(s1lRight($payload))->toBe('Через неделю')
        ->and(array_unique(array_map(mb_strtolower(...), $texts)))->toHaveCount(3)
        // Of its own kind first: times beside a time, not «Три дня назад» or «Два дня».
        ->and($texts)->not->toContain('Три дня назад')
        ->and($texts)->not->toContain('Два дня');
});

it('takes, between lines of one length that say a number, the lower step and in one exchange the partner', function () {
    // Exchange 7: the learner asks first, both lines say a number in five words — the partner's line is taken.
    $sameStep = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][1]['messages'][1]['text_target'] = 'It started last night.';
        $answer['dialogue'][6]['messages'][0]['text_target'] = 'Do we need two X-rays?';
        $answer['dialogue'][6]['messages'][1]['text_target'] = 'No, one X-ray is enough.';
        $answer['dialogue'][6]['messages'][1]['text_native'] = 'Нет, одного снимка хватит.';
        $answer['dialogue'][7]['messages'][1]['text_target'] = 'Only if it still hurts after a week.';

        return $answer;
    });
    $payload = s1lOne($sameStep, CardKind::ListenNumber);

    expect($payload['line']['ref'])->toBe('x7')
        ->and($payload['span'])->toBe([4, 7])
        ->and(s1lRight($payload))->toBe('Одного');

    // Exchanges 2 and 8: five words with a number each — the lower step.
    $lowerStep = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][7]['messages'][1]['text_target'] = 'Come back in one week.';

        return $answer;
    });

    expect(s1lOne($lowerStep, CardKind::ListenNumber)['line']['ref'])->toBe('x2b');
});

it('deals no number card when no line says a number', function () {
    $scene = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][1]['messages'][1]['text_target'] = 'It started last night.';
        $answer['dialogue'][7]['messages'][1]['text_target'] = 'Only if it still hurts after a week.';

        return $answer;
    });

    expect(s1lCards($scene, CardKind::ListenNumber))->toBe([])
        ->and(array_map(static fn (CardDraft $d): string => $d->kind->value, (new ListenStage)->build($scene)))->toBe([
            'listen_dialogue', 'listen_question', 'listen_question', 'listen_question', 'listen_review',
            'listen_predict', 'listen_predict', 'listen_pace',
        ]);
});

it('deals the number card with exactly two other values and none with one', function () {
    // Every value of the day but «Три дня назад» (x2b and the first filler of p2) and «Два дня» (p5) is gone.
    $fewer = static function (bool $keepTwoDays): Closure {
        return static function (array $answer) use ($keepTwoDays): array {
            $answer['dialogue'][1]['messages'][0]['text_native'] = 'Когда это началось?';
            $answer['phrases'][1]['slot']['fillers'][1]['native'] = 'накануне';
            $answer['phrases'][1]['slot']['fillers'][2]['native'] = 'на рассвете';
            if (! $keepTwoDays) {
                $answer['phrases'][4]['slot']['fillers'][1]['native'] = 'подольше';
            }

            return $answer;
        };
    };

    $two = s1lOne(s1lScene(payload: $fewer(true)), CardKind::ListenNumber);

    expect(s1lTexts($two))->toEqualCanonicalizing(['Через неделю', 'Три дня назад', 'Два дня'])
        ->and(s1lRight($two))->toBe('Через неделю')
        ->and(s1lCards(s1lScene(payload: $fewer(false)), CardKind::ListenNumber))->toBe([]);
});

it('prefers another value of the same kind even when values of the other kind come first', function () {
    // One time-only value is left («Сегодня утром») beside two with a number: it is always among the options.
    $scene = s1lScene(payload: static function (array $answer): array {
        $answer['dialogue'][1]['messages'][0]['text_native'] = 'Когда это началось?';
        $answer['phrases'][1]['slot']['fillers'][1]['native'] = 'накануне';

        return $answer;
    });

    expect(s1lTexts(s1lOne($scene, CardKind::ListenNumber)))->toContain('Сегодня утром');
});

it('reads a value as a run of number and time words standing together', function () {
    $ru = NumberValues::of(lessonPacks()->for('ru'));
    $en = NumberValues::of(lessonPacks()->for('en'));

    expect($ru?->runs('Приходите завтра в 10:30, или через два дня.'))->toBe([
        ['start' => 10, 'end' => 16, 'text' => 'завтра', 'words' => 1, 'number' => false],
        ['start' => 19, 'end' => 24, 'text' => '10:30', 'words' => 2, 'number' => true],
        ['start' => 30, 'end' => 43, 'text' => 'через два дня', 'words' => 3, 'number' => true],
    ])
        ->and($ru?->value('Приходите завтра в 10:30, или через два дня.'))->toBe(['text' => 'Через два дня', 'number' => true])
        ->and($ru?->values('два, три'))->toBe([['text' => 'Два', 'number' => true], ['text' => 'Три', 'number' => true]])
        ->and($ru?->value('Кашель'))->toBeNull()
        ->and($en?->says('after one week'))->toBeTrue()
        ->and($en?->says('last night'))->toBeFalse()
        ->and(NumberValues::of(LanguagePack::none('xx')))->toBeNull()
        ->and(NumberValues::of(lessonPacks()->for('uk')))->toBeNull();
});
