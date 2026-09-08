<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\SituationalPrompt;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\SituationalCandidate;
use App\Modules\Learning\Domain\ValueObject\SituationalSituation;
use App\Modules\Learning\Domain\ValueObject\SituationalTurn;

beforeEach(fn () => $this->prompt = new SituationalPrompt());

const SCENE_INTRO = 'Вы у стойки регистратуры. Вас спросят, что случилось. Успех — вас записали на сегодня.';
const SCENE_TITLE = 'Стойка регистратуры';

function candidate(string $id, ?string $shelf, ?string $skillRef, string $text = 'text'): SituationalCandidate
{
    return new SituationalCandidate($id, $shelf, $skillRef, $text);
}

/** The day of the кадр D-04: one role line and one reply, both serving `s1.1`. */
function scene(): array
{
    return [
        candidate('01A', 'hear', 's1.1', 'What seems to be the problem?'),
        candidate('01B', 'say', 's1.1', 'My child has a fever.'),
        candidate('01C', 'ask', 's1.2', 'Is the doctor in today?'),
        candidate('01D', 'words', 's1.1', 'fever'),
    ];
}

it('gives a speak card the scene’s first sentence and the role line that asks the same thing', function () {
    $situation = $this->prompt->for(
        ExerciseMode::SituationalSay,
        candidate('01B', 'say', 's1.1', 'My child has a fever.'),
        scene(),
        ['s1.1' => 'рассказать, что болит'],
        SCENE_INTRO,
        SCENE_TITLE,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_ROLE_LINE)
        // The FIRST sentence only: the вводка's other two say what will happen and what counts as
        // success, which over a card is the day explaining itself for the twentieth time.
        ->and($situation?->context)->toBe('Вы у стойки регистратуры.')
        ->and($situation?->roleLine)->toBe('What seems to be the problem?')
        ->and($situation?->roleLineTermId)->toBe('01A')
        ->and($situation?->task)->toBeNull();
});

// ПРАВИЛО: пару выбирает ЦЕПОЧКА разговора (канон §9), а не умение.
// ЛОВИТ: живой дефект с телефона владельца 08.09. У дня 3 его плана ДВЕ реплики роли на умение
// `s3.2` — «What were you personally responsible for?» и «Do you have any questions for me?». Пара
// искалась по `skill_ref` с тай-брейком по наименьшему id, выиграла первая, и человеку показали
// «Тех лид хочет понять, что именно ты делал» над сборкой ВОПРОСА «How is ownership split across
// the team?». Цепочка дня при этом говорила однозначно, кто с кем стоит.
it('pairs a move with the line said right before it, not with another line of the same ability', function () {
    // Полторы пары дня 3: два обмена одного умения `s3.2`, реплики роли в порядке цепочки.
    $day = [
        candidate('01AAA', 'hear', 's3.2', 'What were you personally responsible for?'),
        candidate('01BBB', 'say', 's3.2', 'I handled the backend integration.'),
        candidate('01CCC', 'hear', 's3.2', 'Do you have any questions for me?'),
        candidate('01DDD', 'ask', 's3.2', 'How is ownership split across the team?'),
    ];
    $chain = [
        new SituationalTurn(SituationalTurn::SIDE_ROLE, '01AAA'),
        new SituationalTurn(SituationalTurn::SIDE_YOU, '01BBB'),
        new SituationalTurn(SituationalTurn::SIDE_ROLE, '01CCC'),
        new SituationalTurn(SituationalTurn::SIDE_YOU, '01DDD'),
    ];

    $situation = $this->prompt->for(
        ExerciseMode::SituationalAsk,
        $day[3],
        $day,
        ['s3.2' => 'уточнить, как устроена команда'],
        SCENE_INTRO,
        SCENE_TITLE,
        $chain,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_ROLE_LINE)
        ->and($situation?->roleLine)->toBe('Do you have any questions for me?')
        ->and($situation?->roleLineTermId)->toBe('01CCC');

    // …и первый ход того же умения получает СВОЮ реплику, а не ту же самую.
    $first = $this->prompt->for(
        ExerciseMode::SituationalSay,
        $day[1],
        $day,
        ['s3.2' => 'уточнить, как устроена команда'],
        SCENE_INTRO,
        SCENE_TITLE,
        $chain,
    );

    expect($first?->roleLine)->toBe('What were you personally responsible for?');
});

// ПРАВИЛО: цепочка бьёт умение, но день без цепочки живёт по-старому (канон §9, обратная
// совместимость).
// ЛОВИТ: планы, написанные до `plan_day.dialogue`. Их карточки остались бы вовсе без ситуации, и
// экран показал бы задачу умения там, где раньше стояла реплика.
it('keeps the old pairing for a day written before the chain existed', function () {
    $situation = $this->prompt->for(
        ExerciseMode::SituationalSay,
        candidate('01B', 'say', 's1.1', 'My child has a fever.'),
        scene(),
        ['s1.1' => 'рассказать, что болит'],
        SCENE_INTRO,
        SCENE_TITLE,
        [],
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_ROLE_LINE)
        ->and($situation?->roleLine)->toBe('What seems to be the problem?');
});

// ПРАВИЛО: ход, которого в цепочке нет, падает на запасной путь, а не остаётся без ситуации.
// ЛОВИТ: карточку ЧУЖОГО дня, выданную в шве этого: её цепочка — своя, и в цепочке текущего дня
// её хода нет.
it('falls back when this move is not in the chain at all', function () {
    $situation = $this->prompt->for(
        ExerciseMode::SituationalSay,
        candidate('01B', 'say', 's1.1', 'My child has a fever.'),
        scene(),
        ['s1.1' => 'рассказать, что болит'],
        SCENE_INTRO,
        SCENE_TITLE,
        [new SituationalTurn(SituationalTurn::SIDE_ROLE, '01ZZZ')],
    );

    expect($situation?->roleLine)->toBe('What seems to be the problem?');
});

it('falls back to the ability when no role line of the day serves it', function () {
    // «Ты спросишь» here serves `s1.2`, and nothing on the hear shelf does.
    $situation = $this->prompt->for(
        ExerciseMode::SituationalAsk,
        candidate('01C', 'ask', 's1.2', 'Is the doctor in today?'),
        scene(),
        ['s1.1' => 'рассказать, что болит', 's1.2' => 'уточнить, когда примут'],
        SCENE_INTRO,
        SCENE_TITLE,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_SKILL)
        ->and($situation?->context)->toBe('Вы у стойки регистратуры.')
        ->and($situation?->task)->toBe('уточнить, когда примут')
        ->and($situation?->roleLine)->toBeNull();
});

it('announces the SCENE on a hear card, and never the вводка', function () {
    // Канон Ч-1: «для hear — контекст „что вы сейчас услышите“ из title сцены». The вводка says what
    // will happen, which on this card is the answer to the question being asked.
    $situation = $this->prompt->for(
        ExerciseMode::SituationalHear,
        candidate('01A', 'hear', 's1.1', 'What seems to be the problem?'),
        scene(),
        ['s1.1' => 'рассказать, что болит'],
        SCENE_INTRO,
        SCENE_TITLE,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_SCENE)
        ->and($situation?->context)->toBe(SCENE_TITLE)
        ->and($situation?->roleLine)->toBeNull()
        ->and($situation?->task)->toBeNull();
});

it('picks the same paired role line every time, whatever order the day arrives in', function () {
    // Two role lines for one ability. The learner must not be answering a different question in the
    // second sitting than in the first, so the tie is broken by term id — the shelf's own order —
    // and not by however the caller happened to build the list.
    $day = [
        candidate('01Z', 'hear', 's1.1', 'Second question of the shelf.'),
        candidate('01A', 'hear', 's1.1', 'First question of the shelf.'),
        candidate('01B', 'say', 's1.1', 'My child has a fever.'),
    ];
    $card = candidate('01B', 'say', 's1.1', 'My child has a fever.');

    $first = $this->prompt->for(ExerciseMode::SituationalSay, $card, $day, [], SCENE_INTRO, SCENE_TITLE);
    $second = $this->prompt->for(ExerciseMode::SituationalSay, $card, array_reverse($day), [], SCENE_INTRO, SCENE_TITLE);

    expect($first?->roleLineTermId)->toBe('01A')
        ->and($second?->roleLineTermId)->toBe('01A');
});

it('does not pair two cards that merely both lack an ability', function () {
    // `null === null` would make every unlabelled role line of a pre-gate day the pair of every
    // unlabelled reply — a pairing made of two absences.
    $day = [candidate('01A', 'hear', null, 'What seems to be the problem?')];

    $situation = $this->prompt->for(
        ExerciseMode::SituationalSay,
        candidate('01B', 'say', null, 'My child has a fever.'),
        $day,
        [],
        SCENE_INTRO,
        SCENE_TITLE,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_SKILL)
        ->and($situation?->roleLine)->toBeNull()
        // No ability either, so the situation is the position and nothing more. Thin, and honest.
        ->and($situation?->task)->toBeNull();
});

it('never puts the card’s own answer, or a translation of it, into the situation', function () {
    // THE GUARANTEE IS THE SIGNATURE. The reply's translation is not an argument to this class, so
    // there is no path — role line or ability — on which it could reach the prompt. This test is the
    // statement of that; it is not testing a filter, because there is no filter to test.
    $answer = 'My child has a fever.';
    $translation = 'У моего ребёнка температура.';

    foreach ([ExerciseMode::SituationalSay, ExerciseMode::SituationalAsk, ExerciseMode::SituationalHear] as $mode) {
        $situation = $this->prompt->for(
            $mode,
            candidate('01B', $mode === ExerciseMode::SituationalHear ? 'hear' : 'say', 's1.1', $answer),
            scene(),
            ['s1.1' => 'рассказать, что болит'],
            SCENE_INTRO,
            SCENE_TITLE,
        );

        $printed = implode(' | ', array_filter([
            $situation?->context, $situation?->task, $situation?->roleLine,
        ]));

        expect($printed)->not->toContain($translation)
            ->and($printed)->not->toContain($answer);
    }
});

it('says nothing at all for a trainer that is not situational', function () {
    foreach ([ExerciseMode::MultipleChoice, ExerciseMode::Speaking, ExerciseMode::Intro] as $mode) {
        expect($this->prompt->for($mode, candidate('01B', 'say', 's1.1'), scene(), [], SCENE_INTRO, SCENE_TITLE))
            ->toBeNull();
    }
});

it('survives a day with no вводка and no scene name', function () {
    $situation = $this->prompt->for(
        ExerciseMode::SituationalSay,
        candidate('01B', 'say', 's9.9', 'My child has a fever.'),
        [],
        [],
        null,
        null,
    );

    expect($situation?->source)->toBe(SituationalSituation::SOURCE_SKILL)
        ->and($situation?->context)->toBeNull()
        ->and($situation?->task)->toBeNull();
});
