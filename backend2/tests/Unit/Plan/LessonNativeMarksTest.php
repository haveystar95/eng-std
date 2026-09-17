<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;

/**
 * A FRAME ENDS WITH ITS MARK, NOT A SPACE AND A MARK (доработка GEN-3; both languages since наряд BACK-TAILS-1 §3.1):
 * the lesson is read with the space before the closing mark of `frame_target`, of `frame_native` and of every filler's
 * `native` taken out, before anything else reads it — the validator, the seam judge, the repair, the cards.
 */

/** The fake's lesson with the natives given, parsed as the server parses a model's answer. */
function nmParsed(Closure $break): App\Modules\Plan\Domain\Lesson\Lesson
{
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays));

    return (new LessonParser)->parse($break($payload));
}

// Доработка GEN-3: «разбор урока: у frame_native и native наполнений снимать пробелы перед знаком конца („Всего ___ .“ → „Всего
// ___.“), детерминированно, до валидатора и до судьи швов». Catches the restaurant's «Всего ___ .» (GEN-3, P2R of p2) reaching
// a card and the seam judge as written, a filler's «три дня назад .» kept, a space inside the frame or before a mark that
// does not end it taken out, and a line's native text touched — the rule is the frame's and the filler's, nothing else.
// Наряд BACK-TAILS-1 §3.1: `frame_target` was left out of the first pass and «I work ___ .» reached the card as written.
it('reads a frame of either language and a filler\'s native text without the space before the mark they end with', function () {
    $lesson = nmParsed(static function (array $p): array {
        $p['phrases'][0]['frame_target'] = 'It hurts in his ___ .';
        $p['phrases'][3]['frame_target'] = "He doesn't have a fever !";
        $p['phrases'][0]['frame_native'] = 'Всего ___ .';
        $p['phrases'][1]['slot']['fillers'][0]['native'] = 'три дня назад  .';
        $p['phrases'][5]['frame_native'] = 'Нам нужно ___ ?';
        $p['phrases'][2]['frame_native'] = 'Что с ним ? — ___ !';
        $p['dialogue'][0]['messages'][1]['text_native'] = 'У него болит поясница .';

        return $p;
    });
    $seams = array_column(NativeSeams::of($lesson), 'pattern', 'id');

    expect($lesson->phrase('p1')?->frameTarget)->toBe('It hurts in his ___.')
        ->and($lesson->phrase('p1')?->toArray()['frame_target'])->toBe('It hurts in his ___.')
        ->and($lesson->phrase('p4')?->frameTarget)->toBe("He doesn't have a fever!")
        ->and($lesson->phrase('p1')?->frameNative)->toBe('Всего ___.')
        ->and($lesson->phrase('p1')?->toArray()['frame_native'])->toBe('Всего ___.')
        ->and($lesson->phrase('p2')?->fillers()[0]->native)->toBe('три дня назад.')
        ->and($lesson->phrase('p6')?->frameNative)->toBe('Нам нужно ___?')
        ->and($lesson->phrase('p3')?->frameNative)->toBe('Что с ним ? — ___!')
        ->and($seams['p1.f1'])->toBe('Всего ___.')
        ->and($lesson->exchanges[0]->learner()?->textNative)->toBe('У него болит поясница .')
        // A line's own target text is not the frame's: it is served as the model wrote it.
        ->and($lesson->exchanges[0]->learner()?->textTarget)->toBe('It hurts in his lower back.')
        ->and($lesson->phrase('p4')?->frameNative)->toBe('Температуры у него нет.');
});
