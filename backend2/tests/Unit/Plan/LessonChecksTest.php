<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\CheckReport;
use App\Modules\Plan\Domain\Check\LessonChecker;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\CheckModes;
use App\Modules\Plan\Domain\ValueObject\Finding;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * THE LESSON CHECKS (docs/plan-v2.md §5) — one broken JSON per row of the table. In `observe`
 * every check only counts; in `drop` it erases the broken mark or field; in `gate` it refuses the
 * lesson. The two «observe forever» rows count whatever mode the table names.
 */
function chkLessonPayload(): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, 6, 8, 8));
}

function chkLessonContext(): LessonContext
{
    return new LessonContext(6, 8, 8, 'ru', 'en');
}

/** @return CheckReport<Lesson> */
function chkRun(array $payload, string $check, CheckMode $mode): CheckReport
{
    $lesson = (new LessonParser)->parse($payload);

    return (new LessonChecker(CheckModes::fromArray([$check => $mode->value])))->run($lesson, chkLessonContext());
}

function chkFindingsOf(CheckReport $report, string $check): array
{
    return array_values(array_filter($report->findings, static fn (Finding $f): bool => $f->check === $check));
}

/** Every broken payload, with the check that must catch it and what `drop` must leave behind. */
dataset('broken lessons', [
    'counts: one phrase short' => [
        'counts',
        static function (array $p): array {
            array_pop($p['phrases']);

            return $p;
        },
        null,
    ],
    'exchange_shape: three messages' => [
        'exchange_shape',
        static function (array $p): array {
            $p['dialogue'][0]['messages'][] = $p['dialogue'][0]['messages'][1];

            return $p;
        },
        null,
    ],
    'exchange_shape: a step number twice' => [
        'exchange_shape',
        static function (array $p): array {
            $p['dialogue'][3]['step'] = 3;

            return $p;
        },
        null,
    ],
    'exchange_shape: first speaker is not the initiator' => [
        'exchange_shape',
        static function (array $p): array {
            $p['dialogue'][0]['initiator'] = 'B';

            return $p;
        },
        null,
    ],
    'second_message_question: the reply asks' => [
        'second_message_question',
        static function (array $p): array {
            $p['dialogue'][0]['messages'][1]['text_target'] = 'Does it hurt a lot?';

            return $p;
        },
        null,
    ],
    'speaking_key_substring: key not in the line' => [
        'speaking_key_substring',
        static function (array $p): array {
            $p['dialogue'][0]['messages'][1]['speaking_key'] = 'describe main pain';

            return $p;
        },
        null,
    ],
    'pronunciation_script: Latin letters in a reading' => [
        'pronunciation_script',
        static function (array $p): array {
            $p['vocabulary'][0]['pronunciation_native'] = 'lower бэк';

            return $p;
        },
        static fn (Lesson $l): bool => $l->vocabulary[0]->pronunciationNative === '',
    ],
    'variant_length: variant longer than the line by two words' => [
        'variant_length',
        static function (array $p): array {
            $p['dialogue'][0]['messages'][1]['simplified_variants'] = ['It hurts in his lower back a lot now.'];

            return $p;
        },
        static fn (Lesson $l): bool => $l->exchanges[0]->learner()?->simplifiedVariants === [],
    ],
    'variant_length: variant equal to the line' => [
        'variant_length',
        static function (array $p): array {
            $p['dialogue'][0]['messages'][1]['simplified_variants'] = ['It hurts in his lower back.'];

            return $p;
        },
        static fn (Lesson $l): bool => $l->exchanges[0]->learner()?->simplifiedVariants === [],
    ],
    'vocabulary_id_absent: id on a line without the term' => [
        'vocabulary_id_absent',
        static function (array $p): array {
            $p['dialogue'][1]['messages'][0]['vocabulary_ids'] = ['v4'];

            return $p;
        },
        static fn (Lesson $l): bool => $l->exchanges[1]->partner()?->vocabularyIds === [],
    ],
    'phrase_id_absent: id on a line that does not carry the phrase' => [
        'phrase_id_absent',
        static function (array $p): array {
            $p['dialogue'][1]['messages'][1]['phrase_ids'] = ['p1'];

            return $p;
        },
        static fn (Lesson $l): bool => $l->exchanges[1]->learner()?->phraseIds === [],
    ],
    'phrase_unused: a phrase nobody says' => [
        'phrase_unused',
        static function (array $p): array {
            $p['phrases'][0]['text_target'] = 'Could you write it down, please?';

            return $p;
        },
        static fn (Lesson $l): bool => $l->phrase('p1') === null && count($l->phrases) === 5,
    ],
    'vocabulary_contained: «back» inside «lower back»' => [
        'vocabulary_contained',
        static function (array $p): array {
            $p['vocabulary'][1]['term_target'] = 'back';

            return $p;
        },
        static fn (Lesson $l): bool => $l->vocabularyItem('v2') === null && $l->vocabularyItem('v1') !== null,
    ],
]);

it('counts and keeps in observe, acts in drop, refuses in gate', function (string $check, Closure $break, ?Closure $dropped) {
    $payload = $break(chkLessonPayload());

    // A clean lesson fires nothing on this check — the broken one is the only reason it fires.
    expect(chkFindingsOf(chkRun(chkLessonPayload(), $check, CheckMode::Observe), $check))->toBe([]);

    $observed = chkRun($payload, $check, CheckMode::Observe);
    $found = chkFindingsOf($observed, $check);
    expect($found)->not->toBeEmpty()
        ->and($found[0]->action)->toBe(CheckAction::Counted)
        ->and($observed->gated)->toBeFalse()
        // Observe keeps the lesson exactly as the model wrote it.
        ->and($observed->answer->toArray())->toBe((new LessonParser)->parse($payload)->toArray());

    $gated = chkRun($payload, $check, CheckMode::Gate);
    expect($gated->gated)->toBeTrue()
        ->and(chkFindingsOf($gated, $check)[0]->action)->toBe(CheckAction::Gated);

    $dropReport = chkRun($payload, $check, CheckMode::Drop);
    expect($dropReport->gated)->toBeFalse()
        ->and(chkFindingsOf($dropReport, $check)[0]->action)->toBe(CheckAction::Dropped);
    if ($dropped !== null) {
        expect($dropped($dropReport->answer))->toBeTrue();
    } else {
        // A check with nothing to erase leaves the lesson as it was even in drop.
        expect($dropReport->answer->toArray())->toBe((new LessonParser)->parse($payload)->toArray());
    }
})->with('broken lessons');

it('only ever counts a partner line over 18 words or a learner line over 10, whatever the config says', function () {
    $p = chkLessonPayload();
    $p['dialogue'][0]['messages'][0]['text_target'] = 'Where exactly does it hurt today, is it the upper back, the lower back, or somewhere around the hips and legs?';
    $p['dialogue'][0]['messages'][1]['text_target'] = 'It hurts in his lower back mostly in the evening after school.';

    $report = chkRun($p, 'message_length', CheckMode::Gate);
    $found = chkFindingsOf($report, 'message_length');

    expect(count($found))->toBe(2)
        ->and($found[0]->action)->toBe(CheckAction::Counted)
        ->and($report->gated)->toBeFalse();
});

it('only ever counts fewer than three partner statements, whatever the config says', function () {
    $p = chkLessonPayload();
    foreach ($p['dialogue'] as $i => $exchange) {
        foreach ($exchange['messages'] as $j => $message) {
            if ($message['speaker'] === 'A') {
                $p['dialogue'][$i]['messages'][$j]['text_target'] = 'Is it sharp or dull?';
            }
        }
    }

    $report = chkRun($p, 'partner_statements', CheckMode::Gate);

    expect(chkFindingsOf($report, 'partner_statements'))->toHaveCount(1)
        ->and(chkFindingsOf($report, 'partner_statements')[0]->action)->toBe(CheckAction::Counted)
        ->and($report->gated)->toBeFalse();
});

it('treats an off-schema answer as the model refusing, not as a check', function () {
    $p = chkLessonPayload();
    unset($p['dialogue']);

    expect(fn () => (new LessonParser)->parse($p))->toThrow(ModelAnswerOffSchema::class);
});

it('passes a clean lesson with no findings at all', function () {
    $report = (new LessonChecker(CheckModes::allObserve()))->run((new LessonParser)->parse(chkLessonPayload()), chkLessonContext());

    expect($report->findings)->toBe([])->and($report->gated)->toBeFalse();
});
