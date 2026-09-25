<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * «STARTS LOWER-CASE» OF `options.form_mismatch`, READ ON THE FIRST CHARACTER (наряд LANG-1, валидатор; FIX-3 §5 wrote
 * the rule; the evidence is `docs/research/lang-1/baseline.md`). The fake's clean lesson with the options of its fourth
 * exchange («У него есть температура?», the right one first) written anew in the learner's language — the rule reads the
 * card's side — and nothing but the findings of `options.form_mismatch` at that check looked at. The length and the
 * «piece of the partner's line» sub-rules are not this file's: every row keeps its options within 0.5–2× of the right
 * one and out of the partner's line.
 */

/**
 * What `options.form_mismatch` says of the fourth exchange's check when its options read `$natives` in the learner's
 * language, the right one at `$right`.
 *
 * @param  list<string>  $natives
 * @return list<string> the finding's detail, or nothing
 */
function crFormMismatch(array $natives, int $right = 0): array
{
    $p = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    foreach ($natives as $index => $native) {
        $p['dialogue'][3]['check']['options'][$index]['text_native'] = $native;
    }
    $p['dialogue'][3]['check']['correct_option_index'] = $right;

    return array_values(array_map(
        static fn (LessonViolation $v): string => $v->detail,
        array_filter(
            (new LessonValidator)->run((new LessonParser)->parse($p), lessonContext('ru', 'en')),
            static fn (LessonViolation $v): bool => $v->code === LessonCodes::OPTIONS_FORM_MISMATCH && $v->address === LessonViolation::check(4),
        ),
    ));
}

// Live days LANG-1 fr-en and GEN-3 rent-day1 (ru→en): «9 h du matin» — the French write the hour so, a digit and then a
// lower-case «h» — and «1050 евро» failed as «starts lower-case», the rule having skipped the digit to the first letter.
// CATCHES a rule that still reads past a digit (or a quote, «¿», «¡») for a letter to call lower-case.
it('does not read an option that opens with a digit, a quote or an inverted mark as starting lower-case', function (array $natives, int $right) {
    expect(crFormMismatch($natives, $right))->toBe([]);
})->with([
    'the French hour, whole' => [['9 h du matin', '10 h du matin', '11 h du matin'], 1],
    'the French hour, bare' => [['9 h', '10 h', '11 h'], 1],
    'a price in figures, the right one' => [['1050 евро', 'Девятьсот евро', 'Тысяча двести евро'], 0],
    'a price in figures, a wrong one against a capital' => [['Тысяча евро', '1050 евро', 'Девятьсот евро'], 0],
    'a quoted word against a capital' => [['Сегодня', '«завтра» в десять', 'Вчера вечером'], 0],
    'a Spanish question' => [['¿A las diez?', '¿mañana a las diez?', '¿Hoy por la tarde?'], 0],
]);

// The rule's intent kept (FIX-3 §5: «ни один не начинается со строчной»): a fragment among sentences is odd by its form.
// CATCHES a first LETTER in lower case against a capital let through — a wrong option, or the right one alone (the card's
// answer given away by its form) — and options all of one case counted as not of one form.
it('still counts a first letter in lower case against the right one\'s capital, either way round, and not options all in lower case', function () {
    expect(crFormMismatch(['Сегодня', 'завтра в десять', 'Вчера вечером'], 0))
        ->toBe(['the option «завтра в десять» starts lower-case against the right «Сегодня»'])
        ->and(crFormMismatch(['Сегодня утром', 'завтра в десять', 'Вчера вечером'], 1))
        ->toBe(['the right option «завтра в десять» starts lower-case against «Сегодня утром»'])
        ->and(crFormMismatch(['сегодня утром', 'завтра в десять', 'вчера вечером'], 1))->toBe([])
        // A digit on the right is of no case: a wrong option in lower case beside it is no fragment's tell.
        ->and(crFormMismatch(['1050 евро', 'тысяча евро', 'девятьсот евро'], 0))->toBe([]);
});
